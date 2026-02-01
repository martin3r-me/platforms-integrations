<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

/**
 * Model für Sipgate Token-Audit-Events
 *
 * Speichert Token-Events für Audit-Zwecke:
 * - Token-Erstellung (OAuth)
 * - Token-Refresh
 * - Token-Revoke
 * - Token-Fehler
 *
 * WICHTIG: Sensible Token-Werte werden NICHT gespeichert,
 * nur gehashte Referenzen für Audit/Debugging.
 */
class IntegrationsSipgateToken extends Model
{
    protected $table = 'integrations_sipgate_tokens';

    protected $fillable = [
        'integration_connection_id',
        'user_id',
        'event_type',
        'token_hash',
        'refresh_token_hash',
        'expires_in',
        'expires_at',
        'issued_at',
        'scopes',
        'trigger_source',
        'error_message',
        'error_code',
        'ip_address',
        'user_agent',
        'request_id',
        'meta',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'scopes' => 'array',
        'meta' => 'array',
    ];

    /**
     * Event-Typen
     */
    public const EVENT_CREATED = 'created';
    public const EVENT_REFRESHED = 'refreshed';
    public const EVENT_REVOKED = 'revoked';
    public const EVENT_ROTATED = 'rotated';
    public const EVENT_EXPIRED = 'expired';
    public const EVENT_ERROR = 'error';

    /**
     * Trigger-Quellen
     */
    public const TRIGGER_OAUTH = 'oauth_callback';
    public const TRIGGER_REFRESH = 'refresh_job';
    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_API = 'api_call';
    public const TRIGGER_SYSTEM = 'system';

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
     * Prüft, ob es ein Fehler-Event ist
     */
    public function isError(): bool
    {
        return $this->event_type === self::EVENT_ERROR;
    }

    /**
     * Prüft, ob es ein erfolgreicher Token-Event ist
     */
    public function isSuccess(): bool
    {
        return in_array($this->event_type, [
            self::EVENT_CREATED,
            self::EVENT_REFRESHED,
            self::EVENT_ROTATED,
        ]);
    }

    /**
     * Scope für Events eines bestimmten Typs
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope für Fehler-Events
     */
    public function scopeErrors($query)
    {
        return $query->where('event_type', self::EVENT_ERROR);
    }

    /**
     * Scope für erfolgreiche Events
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('event_type', [
            self::EVENT_CREATED,
            self::EVENT_REFRESHED,
            self::EVENT_ROTATED,
        ]);
    }

    /**
     * Scope für Events der letzten N Tage
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Gibt eine menschenlesbare Beschreibung des Events zurück
     */
    public function getDescriptionAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_CREATED => 'OAuth-Token erstellt',
            self::EVENT_REFRESHED => 'Token erneuert',
            self::EVENT_REVOKED => 'Token widerrufen',
            self::EVENT_ROTATED => 'Token rotiert',
            self::EVENT_EXPIRED => 'Token abgelaufen',
            self::EVENT_ERROR => 'Fehler: ' . ($this->error_message ?? 'Unbekannt'),
            default => 'Unbekanntes Event: ' . $this->event_type,
        };
    }

    /**
     * Erstellt einen Fehler-Eintrag
     */
    public static function logError(
        IntegrationConnection $connection,
        string $errorMessage,
        ?string $errorCode = null,
        ?string $triggerSource = null,
        ?string $requestId = null
    ): self {
        return self::create([
            'integration_connection_id' => $connection->id,
            'user_id' => $connection->owner_user_id,
            'event_type' => self::EVENT_ERROR,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'trigger_source' => $triggerSource ?? self::TRIGGER_SYSTEM,
            'request_id' => $requestId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
