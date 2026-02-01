<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;
use Platform\Core\Models\User;
use Platform\Core\Contracts\HasDisplayName;

/**
 * Model für Lexware Kontakte
 *
 * Kontakte werden pro User gespeichert und über eine Lexware-Connection synchronisiert.
 * Da Lexware kein OAuth hat, wird der API-Token manuell eingegeben (auth_scheme: api_key).
 */
class IntegrationsLexwareContact extends Model implements HasDisplayName
{
    protected $table = 'integrations_lexware_contacts';

    protected $fillable = [
        'uuid',
        'external_id',
        'contact_number',
        'contact_type',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'note',
        'is_archived',
        'lexware_created_at',
        'lexware_updated_at',
        'metadata',
        'integration_connection_id',
        'user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_archived' => 'boolean',
        'lexware_created_at' => 'datetime',
        'lexware_updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Die Lexware-IntegrationConnection, über die dieser Kontakt synchronisiert wurde
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function getDisplayName(): ?string
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        $parts = array_filter([$this->first_name, $this->last_name]);
        if (!empty($parts)) {
            return implode(' ', $parts);
        }

        return $this->contact_number ?? $this->external_id;
    }

    /**
     * Gibt den vollen Namen des Kontakts zurück
     */
    public function getFullName(): ?string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        return !empty($parts) ? implode(' ', $parts) : null;
    }

    /**
     * Prüft, ob es sich um einen Firmenkontakt handelt
     */
    public function isCompany(): bool
    {
        return !empty($this->company_name);
    }

    /**
     * Prüft, ob der Kontakt ein Kunde ist
     */
    public function isCustomer(): bool
    {
        return in_array($this->contact_type, ['customer', 'both']);
    }

    /**
     * Prüft, ob der Kontakt ein Lieferant ist
     */
    public function isVendor(): bool
    {
        return in_array($this->contact_type, ['vendor', 'both']);
    }
}
