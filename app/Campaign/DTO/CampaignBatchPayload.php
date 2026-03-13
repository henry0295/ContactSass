<?php

declare(strict_types=1);

namespace App\Campaign\DTO;

use App\Messaging\Enums\MessageChannel;

final readonly class CampaignBatchPayload
{
    /** @param list<string> $contactIds */
    public function __construct(
        public string $tenantId,
        public string $campaignId,
        public string $batchId,
        public int $batchNumber,
        public MessageChannel $channel,
        public array $contactIds,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'campaign_id' => $this->campaignId,
            'batch_id' => $this->batchId,
            'batch_number' => $this->batchNumber,
            'channel' => $this->channel->value,
            'contact_ids' => $this->contactIds,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: (string) $data['tenant_id'],
            campaignId: (string) $data['campaign_id'],
            batchId: (string) $data['batch_id'],
            batchNumber: (int) $data['batch_number'],
            channel: MessageChannel::from((string) $data['channel']),
            contactIds: array_map(static fn (mixed $id): string => (string) $id, $data['contact_ids'] ?? []),
        );
    }
}
