<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WebhookFreeswitchController
{
    public function handle(Request $request): Response
    {
        $data = $request->json()->all();

        Log::info('FreeSWITCH webhook received', $data);

        $callId = $data['call_uuid'] ?? $data['uuid'] ?? null;
        $status = $data['call_status'] ?? $data['status'] ?? null;

        if (!$callId || !$status) {
            Log::warning('Invalid FreeSWITCH webhook - missing call_id or status');
            return response('Bad Request', 400);
        }

        match ($status) {
            'answered', 'ANSWERED' => $this->handleAnswered($data),
            'completed', 'COMPLETED' => $this->handleCompleted($data),
            'failed', 'FAILED' => $this->handleFailed($data),
            'busy', 'BUSY' => $this->handleBusy($data),
            'no_answer', 'NO_ANSWER' => $this->handleNoAnswer($data),
            default => Log::warning('Unknown FreeSWITCH status', ['status' => $status]),
        };

        return response('OK', 200);
    }

    private function handleAnswered(array $data): void
    {
        $callId = $data['call_uuid'] ?? $data['uuid'];
        $destination = $data['destination_number'] ?? null;
        $timestamp = $data['timestamp'] ?? now();

        Log::info('Voice call answered', [
            'call_id' => $callId,
            'destination' => $destination,
            'timestamp' => $timestamp,
        ]);

        // Update message status
        DB::table('delivery_events')
            ->where('provider_event_id', $callId)
            ->updateOrInsert(
                ['provider_event_id' => $callId],
                [
                    'event_type' => 'answered',
                    'occurred_at' => $timestamp,
                    'created_at' => now(),
                ]
            );
    }

    private function handleCompleted(array $data): void
    {
        $callId = $data['call_uuid'] ?? $data['uuid'];
        $duration = $data['duration'] ?? 0;
        $timestamp = $data['timestamp'] ?? now();

        Log::info('Voice call completed', [
            'call_id' => $callId,
            'duration' => $duration,
            'timestamp' => $timestamp,
        ]);

        DB::table('delivery_events')
            ->where('provider_event_id', $callId)
            ->whereIn('event_type', ['answered', 'completed'])
            ->orderBy('created_at', 'desc')
            ->limit(1)
            ->update([
                'event_type' => 'completed',
                'event_payload' => json_encode(['duration_seconds' => $duration]),
                'occurred_at' => $timestamp,
            ]);
    }

    private function handleFailed(array $data): void
    {
        $callId = $data['call_uuid'] ?? $data['uuid'];
        $reason = $data['failure_reason'] ?? $data['hangup_cause'] ?? 'Unknown';
        $timestamp = $data['timestamp'] ?? now();

        Log::error('Voice call failed', [
            'call_id' => $callId,
            'reason' => $reason,
            'timestamp' => $timestamp,
        ]);

        DB::table('delivery_events')
            ->where('provider_event_id', $callId)
            ->updateOrInsert(
                ['provider_event_id' => $callId],
                [
                    'event_type' => 'failed',
                    'event_payload' => json_encode(['reason' => $reason]),
                    'occurred_at' => $timestamp,
                    'created_at' => now(),
                ]
            );
    }

    private function handleBusy(array $data): void
    {
        $callId = $data['call_uuid'] ?? $data['uuid'];
        $destination = $data['destination_number'] ?? null;
        $timestamp = $data['timestamp'] ?? now();

        Log::warning('Voice call - line busy', [
            'call_id' => $callId,
            'destination' => $destination,
        ]);

        DB::table('delivery_events')
            ->where('provider_event_id', $callId)
            ->updateOrInsert(
                ['provider_event_id' => $callId],
                [
                    'event_type' => 'busy',
                    'occurred_at' => $timestamp,
                    'created_at' => now(),
                ]
            );
    }

    private function handleNoAnswer(array $data): void
    {
        $callId = $data['call_uuid'] ?? $data['uuid'];
        $destination = $data['destination_number'] ?? null;
        $timestamp = $data['timestamp'] ?? now();

        Log::warning('Voice call - no answer', [
            'call_id' => $callId,
            'destination' => $destination,
        ]);

        DB::table('delivery_events')
            ->where('provider_event_id', $callId)
            ->updateOrInsert(
                ['provider_event_id' => $callId],
                [
                    'event_type' => 'no_answer',
                    'occurred_at' => $timestamp,
                    'created_at' => now(),
                ]
            );
    }
}
