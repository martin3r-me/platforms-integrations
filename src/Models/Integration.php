<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $table = 'integrations';

    protected $fillable = [
        'key',
        'name',
        'is_enabled',
        'supported_auth_schemes',
        'meta',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'supported_auth_schemes' => 'array',
        'meta' => 'array',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class, 'integration_id');
    }
}

