<?php

declare(strict_types=1);

namespace App\Messaging\Workers;

use App\Integrations\AmazonSesProvider;
use App\Messaging\DTO\OutboundMessage;
use App\Messaging\Enums\MessageChannel;

final readonly class EmailWorker
{
    public function __construct(private AmazonSesProvider $provider) {}

    /** @return array{provider_message_id:string,status:string,meta?:array<string,mixed>} */
    public function send(OutboundMessage $message): array
    {
        return $this->provider->send(new OutboundMessage(
            messageId: $message->messageId,
            tenantId: $message->tenantId,
            campaignId: $message->campaignId,
            contactId: $message->contactId,
            channel: MessageChannel::Email,
            payload: $message->payload,
        ));
    }
}
