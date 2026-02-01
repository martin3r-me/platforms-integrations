<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

/**
 * Model für Sipgate Account-Daten
 *
 * Speichert synchronisierte Sipgate Account-Informationen wie:
 * - User-Profile
 * - Telefonnummern
 * - Geräte/Devices
 * - Voicemail-Einstellungen
 * - Guthaben
 */
class IntegrationsSipgateAccount extends Model
{
    use SoftDeletes;

    protected $table = 'integrations_sipgate_accounts';

    protected $fillable = [
        'sipgate_user_id',
        'sipgate_sub_id',
        'integration_connection_id',
        'user_id',
        'email',
        'firstname',
        'lastname',
        'company',
        'locale',
        'timezone',
        'admin',
        'active',
        'phone_numbers',
        'fax_numbers',
        'devices',
        'voicemail_settings',
        'balance',
        'balance_currency',
        'meta',
        'last_synced_at',
        'sync_status',
    ];

    protected $casts = [
        'admin' => 'boolean',
        'active' => 'boolean',
        'phone_numbers' => 'array',
        'fax_numbers' => 'array',
        'devices' => 'array',
        'voicemail_settings' => 'array',
        'meta' => 'array',
        'balance' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Beziehung zur IntegrationConnection
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Beziehung zum Platform User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Gibt den vollständigen Namen zurück
     */
    public function getFullNameAttribute(): string
    {
        $name = trim($this->firstname . ' ' . $this->lastname);
        return $name ?: $this->email ?? 'Unbekannt';
    }

    /**
     * Gibt die primäre Telefonnummer zurück
     */
    public function getPrimaryPhoneAttribute(): ?string
    {
        if (!$this->phone_numbers || empty($this->phone_numbers)) {
            return null;
        }

        // Erste Nummer als primär
        return $this->phone_numbers[0]['number'] ?? $this->phone_numbers[0] ?? null;
    }

    /**
     * Gibt das formatierte Guthaben zurück
     */
    public function getFormattedBalanceAttribute(): string
    {
        if ($this->balance === null) {
            return 'N/A';
        }

        return number_format($this->balance, 2, ',', '.') . ' ' . $this->balance_currency;
    }

    /**
     * Prüft, ob der Account aktiv und synchronisiert ist
     */
    public function isHealthy(): bool
    {
        return $this->active && $this->sync_status === 'synced';
    }

    /**
     * Scope für aktive Accounts
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope für synchronisierte Accounts
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * Scope für Accounts mit Fehlern
     */
    public function scopeWithErrors($query)
    {
        return $query->where('sync_status', 'error');
    }

    /**
     * Aktualisiert aus API-Daten
     */
    public function updateFromApi(array $apiData): self
    {
        $this->fill([
            'email' => $apiData['email'] ?? $this->email,
            'firstname' => $apiData['firstname'] ?? $this->firstname,
            'lastname' => $apiData['lastname'] ?? $this->lastname,
            'company' => $apiData['company'] ?? $this->company,
            'locale' => $apiData['locale'] ?? $this->locale,
            'timezone' => $apiData['timezone'] ?? $this->timezone,
            'admin' => $apiData['admin'] ?? $this->admin,
            'active' => $apiData['active'] ?? $this->active,
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ]);

        // Optionale Felder
        if (isset($apiData['phoneNumbers'])) {
            $this->phone_numbers = $apiData['phoneNumbers'];
        }

        if (isset($apiData['faxNumbers'])) {
            $this->fax_numbers = $apiData['faxNumbers'];
        }

        if (isset($apiData['devices'])) {
            $this->devices = $apiData['devices'];
        }

        $this->save();

        return $this;
    }
}
