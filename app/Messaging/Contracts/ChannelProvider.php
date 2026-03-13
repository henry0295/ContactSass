<?php

declare(strict_types=1);

namespace App\Messaging\Contracts;

use App\Messaging\DTO\OutboundMessage;

interface ChannelProvider
{
    /** @return array{provider_message_id: string, status: string, meta?: array<string,mixed>} */
    public function send(OutboundMessage $message): array;
}
