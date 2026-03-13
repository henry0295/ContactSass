<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Campaign\Services\CampaignEngineService;
use App\Models\Campaign;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CampaignController
{
    public function __construct(private readonly CampaignEngineService $engine) {}

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $campaigns = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('tenant_id', $tenantId)
            ->paginate(20);

        return response()->json($campaigns);
    }

    public function store(Request $request, string $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'channel' => 'required|in:email,sms,voice',
            'contact_list_id' => 'required|uuid|exists:contact_lists,id',
            'subject' => 'nullable|string|max:255',
            'template_body' => 'nullable|string',
            'sms_body' => 'nullable|string',
            'voice_audio_s3_key' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $campaignId = (string) \Illuminate\Support\Str::uuid();

        \Illuminate\Support\Facades\DB::table('campaigns')->insert([
            'id' => $campaignId,
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'channel' => $validated['channel'],
            'status' => 'draft',
            'contact_list_id' => $validated['contact_list_id'],
            'subject' => $validated['subject'] ?? null,
            'template_body' => $validated['template_body'] ?? null,
            'sms_body' => $validated['sms_body'] ?? null,
            'voice_audio_s3_key' => $validated['voice_audio_s3_key'] ?? null,
            'metadata' => json_encode($validated['metadata'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $campaignId], 201);
    }

    public function show(Request $request, string $tenantId, string $campaignId): JsonResponse
    {
        $campaign = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        return response()->json($campaign);
    }

    public function update(Request $request, string $tenantId, string $campaignId): JsonResponse
    {
        $campaign = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        if ($campaign->status !== 'draft') {
            return response()->json(['error' => 'Can only edit draft campaigns'], 400);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'template_body' => 'nullable|string',
            'sms_body' => 'nullable|string',
            'voice_audio_s3_key' => 'nullable|string',
        ]);

        \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->update(array_filter(array_merge($validated, ['updated_at' => now()])));

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $tenantId, string $campaignId): JsonResponse
    {
        $campaign = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        if ($campaign->status !== 'draft') {
            return response()->json(['error' => 'Can only delete draft campaigns'], 400);
        }

        \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->delete();

        return response()->json(['success' => true]);
    }

    public function start(Request $request, string $tenantId, string $campaignId): JsonResponse
    {
        $campaign = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        if ($campaign->status !== 'draft') {
            return response()->json(['error' => 'Campaign must be in draft status'], 400);
        }

        try {
            $this->engine->dispatchCampaign($tenantId, $campaignId, (int) config('messaging.batch_size', 1000));

            return response()->json([
                'status' => 'accepted',
                'campaign_id' => $campaignId,
            ], 202);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function pause(Request $request, string $tenantId, string $campaignId): JsonResponse
    {
        $campaign = \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        if ($campaign->status !== 'running') {
            return response()->json(['error' => 'Campaign must be running'], 400);
        }

        \Illuminate\Support\Facades\DB::table('campaigns')
            ->where('id', $campaignId)
            ->update(['status' => 'paused', 'updated_at' => now()]);

        return response()->json(['success' => true]);
    }
}
