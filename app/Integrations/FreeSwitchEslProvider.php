<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Messaging\Contracts\ChannelProvider;
use App\Messaging\DTO\OutboundMessage;

final readonly class FreeSwitchEslProvider implements ChannelProvider
{
    public function __construct(private FreeSwitchEslClient $client) {}

    public function send(OutboundMessage $message): array
    {
        $callResult = $this->client->originate(
            toNumber: (string) ($message->payload['to'] ?? ''),
            callerId: (string) ($message->payload['caller_id'] ?? ''),
            audioUrl: (string) ($message->payload['audio_url'] ?? ''),
            timeoutSeconds: (int) ($message->payload['timeout'] ?? 30),
        );

        return [
            'provider_message_id' => $callResult['uuid'],
            'status' => $callResult['status'],
            'meta' => $callResult,
        ];
    }
}
