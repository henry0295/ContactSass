<?php

declare(strict_types=1);

namespace App\Campaign\Services;

use App\Campaign\DTO\CampaignBatchPayload;
use App\Campaign\Jobs\DispatchCampaignBatchJob;
use App\Messaging\Enums\MessageChannel;
use App\Support\TemplateRenderer;
use Illuminate\Support\Facades\DB;

final class CampaignEngineService
{
    private const DEFAULT_BATCH_SIZE = 1000;

    public function __construct(private readonly TemplateRenderer $templateRenderer) {}

    public function dispatchCampaign(string $tenantId, string $campaignId, int $batchSize = self::DEFAULT_BATCH_SIZE): void
    {
        $campaign = DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $channel = MessageChannel::from((string) $campaign->channel);

        DB::table('campaigns')
            ->where('id', $campaignId)
            ->update([
                'status' => 'running',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('contact_list_members')
            ->join('contact_lists', 'contact_lists.id', '=', 'contact_list_members.list_id')
            ->where('contact_lists.id', $campaign->contact_list_id)
            ->select('contact_list_members.contact_id')
            ->orderBy('contact_list_members.contact_id')
            ->chunk($batchSize, function ($rows, int $page) use ($tenantId, $campaignId, $channel): void {
                $batchId = (string) \Illuminate\Support\Str::uuid();
                $contactIds = $rows->pluck('contact_id')->map(static fn ($id): string => (string) $id)->all();

                DB::table('campaign_batches')->insert([
                    'id' => $batchId,
                    'campaign_id' => $campaignId,
                    'batch_number' => $page,
                    'total_recipients' => count($contactIds),
                    'status' => 'queued',
                    'created_at' => now(),
                ]);

                $payload = new CampaignBatchPayload(
                    tenantId: $tenantId,
                    campaignId: $campaignId,
                    batchId: $batchId,
                    batchNumber: $page,
                    channel: $channel,
                    contactIds: $contactIds,
                );

                DispatchCampaignBatchJob::dispatch($payload->toArray())->onQueue('campaign-batch');
            });
    }
}
