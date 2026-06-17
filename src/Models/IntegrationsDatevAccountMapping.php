<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Konten-Mapping innerhalb einer Lexoffice↔DATEV-Bridge.
 *
 * @see database/migrations/..._create_integrations_datev_account_mappings_table.php
 */
class IntegrationsDatevAccountMapping extends Model
{
    public const TYPE_CONTACT = 'contact';
    public const TYPE_POSTING_CATEGORY = 'posting_category';
    public const TYPE_COST_CENTER = 'cost_center';

    public const KIND_DEBITOR = 'debitor';
    public const KIND_KREDITOR = 'kreditor';
    public const KIND_SACHKONTO = 'sachkonto';
    public const KIND_KOSTENSTELLE = 'kostenstelle';

    protected $table = 'integrations_datev_account_mappings';

    protected $fillable = [
        'uuid',
        'bridge_id',
        'mapping_type',
        'source_key',
        'source_label',
        'datev_account_number',
        'account_kind',
        'cost_center_1',
        'cost_center_2',
        'tax_key',
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

    public function bridge(): BelongsTo
    {
        return $this->belongsTo(IntegrationsLexofficeDatevBridge::class, 'bridge_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('mapping_type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
