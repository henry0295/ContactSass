<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WebhookSesController
{
    public function handle(Request $request): Response
    {
        $data = $request->json()->all();

        Log::info('SES webhook received', $data);

        // Verify AWS SNS signature
        if (!$this->verifySnsSignature($request)) {
            Log::warning('Invalid SES webhook signature');
            return response('Unauthorized', 401);
        }

        // Handle subscription confirmation
        if ($data['Type'] === 'SubscriptionConfirmation') {
            $this->confirmSubscription($data);
            return response('OK', 200);
        }

        // Parse SNS message (SES sends through SNS)
        if ($data['Type'] !== 'Notification') {
            return response('OK', 200);
        }

        $message = json_decode($data['Message'], true);
        $eventType = $message['eventType'] ?? null;

        match ($eventType) {
            'Send' => $this->handleSend($message),
            'Bounce' => $this->handleBounce($message),
            'Complaint' => $this->handleComplaint($message),
            'Delivery' => $this->handleDelivery($message),
            'Open' => $this->handleOpen($message),
            'Click' => $this->handleClick($message),
            default => Log::warning('Unknown SES event type', ['type' => $eventType]),
        };

        return response('OK', 200);
    }

    private function verifySnsSignature(Request $request): bool
    {
        $data = $request->json()->all();

        // Simple verification - in production use AWS SNS verification library
        return isset($data['Signature']) && $data['Signature'];
    }

    private function confirmSubscription(array $data): void
    {
        $subscribeUrl = $data['SubscribeURL'] ?? null;
        if ($subscribeUrl) {
            @file_get_contents($subscribeUrl);
        }
    }

    private function handleSend(array $message): void
    {
        $messageId = $message['mail']['messageId'] ?? null;
        $source = $message['mail']['source'] ?? null;
        $timestamp = $message['mail']['timestamp'] ?? now();

        if ($messageId) {
            Log::info('SES email sent', ['message_id' => $messageId, 'source' => $source]);
        }
    }

    private function handleBounce(array $message): void
    {
        $bounceType = $message['bounce']['bounceType'] ?? null;
        $bounceSubType = $message['bounce']['bounceSubType'] ?? null;
        $timestamps = $message['bounce']['bounceSubType'] === 'Permanent'
            ? $message['bounce']['bouncedRecipients'] ?? []
            : [];

        foreach ($timestamps as $recipient) {
            $email = $recipient['emailAddress'] ?? null;
            if ($email) {
                Log::warning("Email bounce: {$bounceType} - {$bounceSubType}", ['email' => $email]);

                // Mark contact email as invalid if permanent bounce
                if ($bounceType === 'Permanent') {
                    DB::table('contacts')
                        ->where('email', $email)
                        ->update(['attributes' => DB::raw("jsonb_set(attributes, '{invalid_email}', 'true')")]);
                }
            }
        }
    }

    private function handleComplaint(array $message): void
    {
        $complaintRecipients = $message['complaint']['complainedRecipients'] ?? [];

        foreach ($complaintRecipients as $recipient) {
            $email = $recipient['emailAddress'] ?? null;
            if ($email) {
                Log::warning('Email complaint received', ['email' => $email]);

                // Mark contact as complained
                DB::table('contacts')
                    ->where('email', $email)
                    ->update(['attributes' => DB::raw("jsonb_set(attributes, '{complained}', 'true')")]);
            }
        }
    }

    private function handleDelivery(array $message): void
    {
        $recipients = $message['delivery']['recipients'] ?? [];
        $timestamp = $message['delivery']['timestamp'] ?? now();

        foreach ($recipients as $email) {
            Log::info('Email delivered', ['email' => $email]);
        }
    }

    private function handleOpen(array $message): void
    {
        $timestamp = $message['open']['timestamp'] ?? now();
        Log::info('Email opened', ['timestamp' => $timestamp]);
    }

    private function handleClick(array $message): void
    {
        $link = $message['click']['link'] ?? null;
        $timestamp = $message['click']['timestamp'] ?? now();
        Log::info('Email link clicked', ['link' => $link, 'timestamp' => $timestamp]);
    }
}
