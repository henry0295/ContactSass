<?php

declare(strict_types=1);

namespace App\Messaging\Jobs;

use App\Messaging\DTO\OutboundMessage;
use App\Messaging\Workers\SmsWorker;

final class SendSmsMessageJob extends AbstractSendMessageJob
{
    protected function send(OutboundMessage $message): array
    {
        return app(SmsWorker::class)->send($message);
    }

    protected function rateLimitMetric(): string
    {
        return 'sms_per_minute';
    }
}
