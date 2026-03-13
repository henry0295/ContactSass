<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Contact extends Model
{
    use HasUuids;
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'external_ref',
        'first_name',
        'last_name',
        'email',
        'phone_e164',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_members');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
