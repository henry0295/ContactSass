<?php

declare(strict_types=1);

namespace App\Messaging\DTO;

use App\Messaging\Enums\MessageChannel;

final readonly class OutboundMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $messageId,
        public string $tenantId,
        public string $campaignId,
        public string $contactId,
        public MessageChannel $channel,
        public array $payload,
    ) {}
}
