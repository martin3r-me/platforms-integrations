<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

class IntegrationsLexwareQuotationLink extends Model
{
    protected $table = 'integrations_lexware_quotation_links';

    protected $fillable = [
        'uuid',
        'linkable_type',
        'linkable_id',
        'quotation_external_id',
        'quotation_number',
        'voucher_status',
        'voucher_date',
        'expiration_date',
        'total_amount',
        'currency',
        'contact_name',
        'integration_connection_id',
        'metadata',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'expiration_date' => 'date',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

            if (empty($model->created_by_user_id) && auth()->check()) {
                $model->created_by_user_id = auth()->id();
            }

            if (empty($model->team_id) && auth()->check()) {
                $model->team_id = auth()->user()->currentTeam?->id;
            }
        });
    }

    /**
     * Verlinkte Entität (Deal, etc.)
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Integration-Connection (Lexoffice API-Verbindung)
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Erstellt von User
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    /**
     * Team
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    /**
     * Scope für aktuelles Team
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope für spezifische Linkable-Typen
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('linkable_type', $type);
    }

    /**
     * Scope für spezifische Quotation-ID
     */
    public function scopeForQuotation($query, string $quotationExternalId)
    {
        return $query->where('quotation_external_id', $quotationExternalId);
    }

    /**
     * Status-Label für UI
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->voucher_status) {
            'draft' => 'Entwurf',
            'open' => 'Offen',
            'accepted' => 'Angenommen',
            'rejected' => 'Abgelehnt',
            default => $this->voucher_status ?? 'Unbekannt',
        };
    }

    /**
     * Formatierter Betrag
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->total_amount, 2, ',', '.') . ' ' . ($this->currency ?? '€');
    }
}
