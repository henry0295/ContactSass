<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReportController
{
    public function campaignAnalytics(Request $request, string $tenantId, string $campaignId): JsonResponse
    {
        $campaign = DB::table('campaigns')
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        $totalMessages = DB::table('messages')
            ->where('campaign_id', $campaignId)
            ->count();

        $sentMessages = DB::table('messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'sent')
            ->count();

        $failedMessages = DB::table('messages')
            ->where('campaign_id', $campaignId)
            ->where('status', 'failed')
            ->count();

        $deliveryEvents = DB::table('delivery_events')
            ->where('campaign_id', $campaignId)
            ->groupBy('event_type')
            ->selectRaw('event_type, count(*) as count')
            ->get();

        return response()->json([
            'campaign_id' => $campaignId,
            'total_messages' => $totalMessages,
            'sent_messages' => $sentMessages,
            'failed_messages' => $failedMessages,
            'delivery_events' => $deliveryEvents,
            'success_rate' => $totalMessages > 0 ? ($sentMessages / $totalMessages * 100) : 0,
        ]);
    }

    public function usage(Request $request, string $tenantId): JsonResponse
    {
        $period = $request->query('period', 'month');

        $startDate = match ($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };

        $usage = DB::table('usage_records')
            ->where('tenant_id', $tenantId)
            ->where('recorded_at', '>=', $startDate)
            ->groupBy('metric')
            ->selectRaw('metric, sum(quantity) as total')
            ->get();

        return response()->json([
            'period' => $period,
            'usage' => $usage,
        ]);
    }
}
