<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WebhookSnsController
{
    public function handle(Request $request): Response
    {
        $data = $request->json()->all();

        Log::info('SNS webhook received', $data);

        // Verify SNS signature
        if (!$this->verifySnsSignature($request)) {
            Log::warning('Invalid SNS webhook signature');
            return response('Unauthorized', 401);
        }

        // Handle subscription confirmation
        if ($data['Type'] === 'SubscriptionConfirmation') {
            $this->confirmSubscription($data);
            return response('OK', 200);
        }

        // Parse SNS message (SMS delivery confirmations)
        if ($data['Type'] !== 'Notification') {
            return response('OK', 200);
        }

        $message = json_decode($data['Message'], true);
        $messageStatus = $message['status'] ?? null;

        match ($messageStatus) {
            'Delivered' => $this->handleDelivered($message),
            'Failed' => $this->handleFailed($message),
            'Undeliverable' => $this->handleUndeliverable($message),
            'Permanent Failure' => $this->handlePermanentFailure($message),
            'Transient Failure' => $this->handleTransientFailure($message),
            'Queued' => $this->handleQueued($message),
            default => Log::warning('Unknown SNS SMS status', ['status' => $messageStatus]),
        };

        return response('OK', 200);
    }

    private function verifySnsSignature(Request $request): bool
    {
        $data = $request->json()->all();
        return isset($data['Signature']) && $data['Signature'];
    }

    private function confirmSubscription(array $data): void
    {
        $subscribeUrl = $data['SubscribeURL'] ?? null;
        if ($subscribeUrl) {
            @file_get_contents($subscribeUrl);
        }
    }

    private function handleDelivered(array $message): void
    {
        $messageId = $message['messageId'] ?? null;
        $destination = $message['destination'] ?? null;

        Log::info('SMS delivered', ['message_id' => $messageId, 'destination' => $destination]);
    }

    private function handleFailed(array $message): void
    {
        $messageId = $message['messageId'] ?? null;
        $destination = $message['destination'] ?? null;
        $priceInUSD = $message['priceInUSD'] ?? null;

        Log::warning('SMS delivery failed', [
            'message_id' => $messageId,
            'destination' => $destination,
            'price' => $priceInUSD,
        ]);
    }

    private function handleUndeliverable(array $message): void
    {
        $phoneNumber = $message['destinationNumber'] ?? null;
        Log::warning('SMS undeliverable', ['phone' => $phoneNumber]);

        if ($phoneNumber) {
            DB::table('contacts')
                ->where('phone_e164', $phoneNumber)
                ->update(['attributes' => DB::raw("jsonb_set(attributes, '{invalid_phone}', 'true')")]);
        }
    }

    private function handlePermanentFailure(array $message): void
    {
        $phoneNumber = $message['destinationNumber'] ?? null;
        $failureReason = $message['failureReason'] ?? null;

        Log::error('SMS permanent failure', ['phone' => $phoneNumber, 'reason' => $failureReason]);

        if ($phoneNumber) {
            DB::table('contacts')
                ->where('phone_e164', $phoneNumber)
                ->update(['attributes' => DB::raw("jsonb_set(attributes, '{invalid_phone}', 'true')")]);
        }
    }

    private function handleTransientFailure(array $message): void
    {
        $phoneNumber = $message['destinationNumber'] ?? null;
        $failureReason = $message['failureReason'] ?? null;

        Log::warning('SMS transient failure', ['phone' => $phoneNumber, 'reason' => $failureReason]);
    }

    private function handleQueued(array $message): void
    {
        $messageId = $message['messageId'] ?? null;
        Log::info('SMS queued', ['message_id' => $messageId]);
    }
}
