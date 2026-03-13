<?php

declare(strict_types=1);

namespace App\Messaging\Jobs;

use App\Messaging\DTO\OutboundMessage;
use App\Messaging\Workers\EmailWorker;

final class SendEmailMessageJob extends AbstractSendMessageJob
{
    protected function send(OutboundMessage $message): array
    {
        return app(EmailWorker::class)->send($message);
    }

    protected function rateLimitMetric(): string
    {
        return 'emails_per_minute';
    }
}
