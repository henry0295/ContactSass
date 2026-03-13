<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantLimit extends Model
{
    protected $table = 'tenant_limits';

    protected $fillable = [
        'tenant_id',
        'emails_per_minute',
        'sms_per_minute',
        'calls_per_minute',
        'monthly_email_limit',
        'monthly_sms_limit',
        'monthly_voice_minutes_limit',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
