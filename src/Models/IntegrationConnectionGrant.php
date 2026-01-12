<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnectionGrant extends Model
{
    protected $table = 'integration_connection_grants';

    protected $fillable = [
        'connection_id',
        'grantee_type',
        'grantee_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}

