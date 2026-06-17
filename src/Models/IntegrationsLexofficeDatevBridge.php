<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Pairing zwischen einer Lexoffice-Connection und einer DATEV-Connection (+ Mandant).
 *
 * @property string $name
 * @property int $lexoffice_connection_id
 * @property int $datev_connection_id
 * @property string $datev_client_id
 * @property bool $is_active
 */
class IntegrationsLexofficeDatevBridge extends Model
{
    protected $table = 'integrations_lexoffice_datev_bridges';

    protected $fillable = [
        'uuid',
        'name',
        'lexoffice_connection_id',
        'datev_connection_id',
        'datev_client_id',
        'owner_user_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = (string) UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }
        });
    }

    public function lexofficeConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'lexoffice_connection_id');
    }

    public function datevConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'datev_connection_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(IntegrationsDatevAccountMapping::class, 'bridge_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->id);
    }
}
