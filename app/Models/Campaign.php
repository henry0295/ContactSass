<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Campaign extends Model
{
    use HasUuids;
    use HasTenantScope;
    
    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'channel',
        'status',
        'contact_list_id',
        'subject',
        'template_body',
        'sms_body',
        'voice_audio_s3_key',
        'scheduled_at',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }
    
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
    
    public function batches(): HasMany
    {
        return $this->hasMany(CampaignBatch::class);
    }
}
