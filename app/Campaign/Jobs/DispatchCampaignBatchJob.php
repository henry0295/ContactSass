<?php

declare(strict_types=1);

namespace App\Campaign\Jobs;

use App\Campaign\DTO\CampaignBatchPayload;
use App\Messaging\Jobs\SendEmailMessageJob;
use App\Messaging\Jobs\SendSmsMessageJob;
use App\Messaging\Jobs\SendVoiceMessageJob;
use App\Support\TemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class DispatchCampaignBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @param array<string,mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    public function handle(TemplateRenderer $templateRenderer): void
    {
        $batch = CampaignBatchPayload::fromArray($this->payload);

        DB::table('campaign_batches')
            ->where('id', $batch->batchId)
            ->update(['status' => 'running', 'started_at' => now()]);

        // Load campaign with relationships
        $campaign = DB::table('campaigns')
            ->where('id', $batch->campaignId)
            ->where('tenant_id', $batch->tenantId)
            ->first();

        if (!$campaign) {
            throw new \RuntimeException("Campaign {$batch->campaignId} not found");
        }

        // Load tenant settings for defaults
        $tenantSettings = DB::table('tenant_settings')
            ->where('tenant_id', $batch->tenantId)
            ->first();

        foreach ($batch->contactIds as $contactId) {
            $messageId = (string) \Illuminate\Support\Str::uuid();

            // Load contact
            $contact = DB::table('contacts')
                ->where('id', $contactId)
                ->where('tenant_id', $batch->tenantId)
                ->first();

            if (!$contact) {
                continue;
            }

            // Extract contact data
            $contactData = [
                'first_name' => $contact->first_name ?? '',
                'last_name' => $contact->last_name ?? '',
                'email' => $contact->email ?? '',
                'phone' => $contact->phone_e164 ?? '',
            ];

            if ($contact->attributes) {
                $attrs = is_string($contact->attributes)
                    ? json_decode($contact->attributes, true)
                    : (array) $contact->attributes;
                $contactData = array_merge($contactData, $attrs ?? []);
            }

            // Build channel-specific payload
            $payload = $this->buildPayload(
                channel: $batch->channel->value,
                campaign: $campaign,
                contact: $contact,
                contactData: $contactData,
                tenantSettings: $tenantSettings,
                templateRenderer: $templateRenderer,
            );

            DB::table('messages')->insert([
                'id' => $messageId,
                'tenant_id' => $batch->tenantId,
                'campaign_id' => $batch->campaignId,
                'batch_id' => $batch->batchId,
                'contact_id' => $contactId,
                'channel' => $batch->channel->value,
                'message_uuid' => \Illuminate\Support\Str::uuid(),
                'status' => 'queued',
                'payload' => json_encode($payload),
                'queued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            match ($batch->channel->value) {
                'email' => SendEmailMessageJob::dispatch($messageId)->onQueue('email-send'),
                'sms' => SendSmsMessageJob::dispatch($messageId)->onQueue('sms-send'),
                'voice' => SendVoiceMessageJob::dispatch($messageId)->onQueue('voice-send'),
            };
        }

        DB::table('campaign_batches')
            ->where('id', $batch->batchId)
            ->update(['status' => 'completed', 'completed_at' => now()]);
    }

    /**
     * Build channel-specific payload with rendered templates
     *
     * @param string $channel Campaign channel
     * @param object $campaign Campaign record
     * @param object $contact Contact record
     * @param array<string, mixed> $contactData Extracted contact data
     * @param object|null $tenantSettings Tenant settings
     * @param TemplateRenderer $templateRenderer Template renderer
     * @return array<string, mixed> Channel-specific payload
     */
    private function buildPayload(
        string $channel,
        object $campaign,
        object $contact,
        array $contactData,
        ?object $tenantSettings,
        TemplateRenderer $templateRenderer,
    ): array {
        return match ($channel) {
            'email' => $this->buildEmailPayload($campaign, $contactData, $tenantSettings, $templateRenderer),
            'sms' => $this->buildSmsPayload($campaign, $contactData, $tenantSettings, $templateRenderer),
            'voice' => $this->buildVoicePayload($campaign, $contactData, $tenantSettings),
            default => [],
        };
    }

    /**
     * Build email-specific payload
     *
     * @param object $campaign Campaign record
     * @param array<string, mixed> $contactData Contact data
     * @param object|null $tenantSettings Tenant settings
     * @param TemplateRenderer $templateRenderer Template renderer
     * @return array<string, mixed> Email payload
     */
    private function buildEmailPayload(
        object $campaign,
        array $contactData,
        ?object $tenantSettings,
        TemplateRenderer $templateRenderer,
    ): array {
        $subject = $campaign->subject ?? '';
        $body = $campaign->template_body ?? '';

        // Render templates with contact data
        $renderedSubject = $subject
            ? $templateRenderer->render($subject, $contactData, escapeHtml: false)
            : '';
        $renderedBody = $body
            ? $templateRenderer->render($body, $contactData, escapeHtml: true)
            : '';

        return [
            'to' => $contactData['email'] ?? '',
            'from' => $tenantSettings->default_email_from ?? config('messaging.aws.ses_from'),
            'subject' => $renderedSubject,
            'body' => $renderedBody,
        ];
    }

    /**
     * Build SMS-specific payload
     *
     * @param object $campaign Campaign record
     * @param array<string, mixed> $contactData Contact data
     * @param object|null $tenantSettings Tenant settings
     * @param TemplateRenderer $templateRenderer Template renderer
     * @return array<string, mixed> SMS payload
     */
    private function buildSmsPayload(
        object $campaign,
        array $contactData,
        ?object $tenantSettings,
        TemplateRenderer $templateRenderer,
    ): array {
        $smsBody = $campaign->sms_body ?? '';

        // Render template with contact data
        $renderedBody = $smsBody
            ? $templateRenderer->render($smsBody, $contactData, escapeHtml: false)
            : '';

        return [
            'to' => $contactData['phone'] ?? '',
            'from' => $tenantSettings->default_sms_sender ?? config('messaging.aws.sns_sender_id'),
            'body' => $renderedBody,
        ];
    }

    /**
     * Build voice-specific payload
     *
     * @param object $campaign Campaign record
     * @param array<string, mixed> $contactData Contact data
     * @param object|null $tenantSettings Tenant settings
     * @return array<string, mixed> Voice payload
     */
    private function buildVoicePayload(
        object $campaign,
        array $contactData,
        ?object $tenantSettings,
    ): array {
        return [
            'to' => $contactData['phone'] ?? '',
            'caller_id' => $tenantSettings->default_voice_caller_id ?? '',
            'audio_url' => $campaign->voice_audio_s3_key ?? '',
            'timeout' => 30,
        ];
    }
}
