<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Messaging\Contracts\ChannelProvider;
use App\Messaging\DTO\OutboundMessage;
use Aws\Sns\SnsClient;

final readonly class AmazonSnsProvider implements ChannelProvider
{
    public function __construct(private SnsClient $client) {}

    public function send(OutboundMessage $message): array
    {
        $result = $this->client->publish([
            'PhoneNumber' => (string) ($message->payload['to'] ?? ''),
            'Message' => (string) ($message->payload['body'] ?? ''),
        ]);

        return [
            'provider_message_id' => (string) $result->get('MessageId'),
            'status' => 'sent',
        ];
    }
}
