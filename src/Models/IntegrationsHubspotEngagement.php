<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;

class IntegrationsHubspotEngagement extends Model
{
    protected $table = 'integrations_hubspot_engagements';

    protected $fillable = [
        'uuid',
        'external_id',
        'engagement_type',
        'subject',
        'body',
        'engaged_at',
        'owner_id',
        'hubspot_created_at',
        'hubspot_updated_at',
        'metadata',
        'associations',
        'integration_connection_id',
        'user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'metadata' => 'array',
        'associations' => 'array',
        'engaged_at' => 'datetime',
        'hubspot_created_at' => 'datetime',
        'hubspot_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
