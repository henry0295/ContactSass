<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeliveryEvent extends Model
{
    use HasUuids;

    protected $table = 'delivery_events';

    protected $fillable = [
        'tenant_id',
        'message_id',
        'channel',
        'event_type',
        'provider_event_id',
        'event_payload',
        'occurred_at',
    ];

    protected $casts = [
        'event_payload' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
