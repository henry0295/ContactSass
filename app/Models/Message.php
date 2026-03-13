<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Message extends Model
{
    use HasUuids;
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'batch_id',
        'contact_id',
        'channel',
        'provider_message_id',
        'message_uuid',
        'status',
        'error_code',
        'error_message',
        'payload',
        'queued_at',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CampaignBatch::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class);
    }
}
