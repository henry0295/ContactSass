<?php

declare(strict_types=1);

namespace App\Messaging\Jobs;

use App\Messaging\DTO\OutboundMessage;
use App\Messaging\Workers\VoiceWorker;

final class SendVoiceMessageJob extends AbstractSendMessageJob
{
    protected function send(OutboundMessage $message): array
    {
        return app(VoiceWorker::class)->send($message);
    }

    protected function rateLimitMetric(): string
    {
        return 'calls_per_minute';
    }
}
