<?php

declare(strict_types=1);

namespace App\Messaging\Workers;

use App\Integrations\FreeSwitchEslProvider;
use App\Messaging\DTO\OutboundMessage;
use App\Messaging\Enums\MessageChannel;

final readonly class VoiceWorker
{
    public function __construct(private FreeSwitchEslProvider $provider) {}

    /** @return array{provider_message_id:string,status:string,meta?:array<string,mixed>} */
    public function send(OutboundMessage $message): array
    {
        return $this->provider->send(new OutboundMessage(
            messageId: $message->messageId,
            tenantId: $message->tenantId,
            campaignId: $message->campaignId,
            contactId: $message->contactId,
            channel: MessageChannel::Voice,
            payload: $message->payload,
        ));
    }
}
