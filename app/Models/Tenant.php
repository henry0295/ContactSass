<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Tenant extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo_url',
        'status',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('role')
            ->withTimestamps();
    }
    
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
    
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
    
    public function contactLists(): HasMany
    {
        return $this->hasMany(ContactList::class);
    }
    
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
    
    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }
    
    public function limits(): HasOne
    {
        return $this->hasOne(TenantLimit::class);
    }
}
