<?php

declare(strict_types=1);

namespace App\Messaging\Jobs;

use App\Messaging\DTO\OutboundMessage;
use App\Support\SlidingWindowRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractSendMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 8;
    public int $backoff = 60;

    public function __construct(protected readonly string $messageId)
    {
    }

    abstract protected function send(OutboundMessage $message): array;

    abstract protected function rateLimitMetric(): string;

    public function handle(SlidingWindowRateLimiter $rateLimiter): void
    {
        try {
            $message = DB::table('messages')->where('id', $this->messageId)->firstOrFail();

            // Check idempotency - skip if already sent
            if ($message->status === 'sent') {
                Log::info('Message already sent', [
                    'message_id' => $this->messageId,
                    'tenant_id' => $message->tenant_id,
                ]);
                return;
            }

            $limit = DB::table('tenant_limits')
                ->where('tenant_id', $message->tenant_id)
                ->value($this->rateLimitMetric()) ?? 1000;

            // Check rate limit
            if (!$rateLimiter->acquire($message->tenant_id . ':' . $this->rateLimitMetric(), (int) $limit)) {
                Log::warning('Rate limit exceeded, retrying later', [
                    'message_id' => $this->messageId,
                    'tenant_id' => $message->tenant_id,
                    'metric' => $this->rateLimitMetric(),
                ]);
                $this->release(5);
                return;
            }

            // Update status to sending
            DB::table('messages')
                ->where('id', $this->messageId)
                ->update(['status' => 'sending', 'updated_at' => now()]);

            // Build outbound message DTO
            $outbound = new OutboundMessage(
                messageId: (string) $message->id,
                tenantId: (string) $message->tenant_id,
                campaignId: (string) $message->campaign_id,
                contactId: (string) $message->contact_id,
                channel: \App\Messaging\Enums\MessageChannel::from((string) $message->channel),
                payload: json_decode((string) $message->payload, true) ?: [],
            );

            // Send via provider
            $result = $this->send($outbound);

            // Update message status and log delivery event in transaction
            DB::transaction(function () use ($message, $result): void {
                DB::table('messages')->where('id', $this->messageId)->update([
                    'provider_message_id' => $result['provider_message_id'] ?? null,
                    'status' => ($result['status'] ?? 'failed') === 'failed' ? 'failed' : 'sent',
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('delivery_events')->insert([
                    'tenant_id' => $message->tenant_id,
                    'message_id' => $this->messageId,
                    'channel' => $message->channel,
                    'event_type' => $result['status'] ?? 'failed',
                    'provider_event_id' => $result['provider_message_id'] ?? null,
                    'event_payload' => json_encode($result),
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);
            });

            Log::info('Message sent successfully', [
                'message_id' => $this->messageId,
                'tenant_id' => $message->tenant_id,
                'provider_message_id' => $result['provider_message_id'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $this->handleSendFailure($exception);
        }
    }

    /**
     * Handle send failure with proper error classification and logging
     *
     * @param Throwable $exception The exception that occurred
     * @return void
     */
    private function handleSendFailure(Throwable $exception): void
    {
        try {
            $message = DB::table('messages')->where('id', $this->messageId)->first();

            if (!$message) {
                Log::error('Message not found for error handling', [
                    'message_id' => $this->messageId,
                    'exception' => $exception::class,
                ]);
                return;
            }

            $errorCode = $this->extractErrorCode($exception);
            $errorMessage = $exception->getMessage();
            $isTransient = $this->isTransientError($exception);

            Log::error('Message send failed', [
                'message_id' => $this->messageId,
                'tenant_id' => $message->tenant_id,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'transient' => $isTransient,
                'attempt' => $this->attempts(),
            ]);

            DB::transaction(function () use ($message, $errorCode, $errorMessage): void {
                DB::table('messages')->where('id', $this->messageId)->update([
                    'status' => 'failed',
                    'error_code' => $errorCode,
                    'error_message' => substr($errorMessage, 0, 500),
                    'updated_at' => now(),
                ]);

                DB::table('delivery_events')->insert([
                    'tenant_id' => $message->tenant_id,
                    'message_id' => $this->messageId,
                    'channel' => $message->channel,
                    'event_type' => 'failed',
                    'provider_event_id' => null,
                    'event_payload' => json_encode([
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                    ]),
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);
            });

            // If transient, allow retry; otherwise fail permanently
            if ($isTransient && $this->attempts() < $this->tries) {
                throw $exception;
            }
        } catch (Throwable $e) {
            Log::critical('Error handling send failure', [
                'message_id' => $this->messageId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract error code from exception
     *
     * @param Throwable $exception The exception
     * @return string Error code
     */
    private function extractErrorCode(Throwable $exception): string
    {
        // AWS SDK exceptions
        if (method_exists($exception, 'getAwsErrorCodes')) {
            $codes = $exception->getAwsErrorCodes();
            return is_array($codes) && !empty($codes) ? $codes[0] : 'AWS_ERROR';
        }

        if (method_exists($exception, 'getAwsErrorMessage')) {
            $msg = $exception->getAwsErrorMessage();
            if (str_contains($msg, 'ThrottlingException')) {
                return 'THROTTLED';
            }
            if (str_contains($msg, 'InvalidParameter')) {
                return 'INVALID_PARAM';
            }
        }

        // Generic classification
        $message = $exception->getMessage();
        if (str_contains($message, 'timeout')) {
            return 'TIMEOUT';
        }
        if (str_contains($message, 'connection')) {
            return 'CONNECTION_ERROR';
        }

        return 'UNKNOWN_ERROR';
    }

    /**
     * Determine if error is transient (retryable)
     *
     * @param Throwable $exception The exception
     * @return bool True if transient
     */
    private function isTransientError(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        // Network errors - transient
        if (str_contains($message, 'timeout') ||
            str_contains($message, 'connection') ||
            str_contains($message, 'refused') ||
            str_contains($message, 'unreachable')) {
            return true;
        }

        // Rate limiting - transient
        if (str_contains($message, 'throttl') ||
            str_contains($message, 'rate limit') ||
            str_contains($message, 'TooManyRequests')) {
            return true;
        }

        // AWS SDK transient errors
        if (method_exists($exception, 'getAwsErrorCodes')) {
            $codes = $exception->getAwsErrorCodes();
            if (is_array($codes)) {
                foreach ($codes as $code) {
                    if (in_array($code, ['RequestLimitExceeded', 'ServiceUnavailable', 'Throttling'], true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
