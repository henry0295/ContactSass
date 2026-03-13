<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Messaging\Contracts\ChannelProvider;
use App\Messaging\DTO\OutboundMessage;
use Aws\Ses\SesClient;

final readonly class AmazonSesProvider implements ChannelProvider
{
    public function __construct(private SesClient $client) {}

    public function send(OutboundMessage $message): array
    {
        $result = $this->client->sendEmail([
            'Destination' => ['ToAddresses' => [(string) ($message->payload['to'] ?? '')]],
            'Message' => [
                'Subject' => ['Data' => (string) ($message->payload['subject'] ?? '')],
                'Body' => ['Html' => ['Data' => (string) ($message->payload['body'] ?? '')]],
            ],
            'Source' => (string) ($message->payload['from'] ?? ''),
        ]);

        return [
            'provider_message_id' => (string) $result->get('MessageId'),
            'status' => 'sent',
        ];
    }
}
