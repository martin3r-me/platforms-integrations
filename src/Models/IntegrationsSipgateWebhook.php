<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\User;

/**
 * Model für Sipgate Webhook-Registrierungen
 *
 * Speichert registrierte Webhooks und deren Konfiguration.
 */
class IntegrationsSipgateWebhook extends Model
{
    use SoftDeletes;

    protected $table = 'integrations_sipgate_webhooks';

    protected $fillable = [
        'integration_connection_id',
        'user_id',
        'sipgate_webhook_id',
        'event_type',
        'direction',
        'callback_url',
        'secret_hash',
        'verified',
        'verified_at',
        'status',
        'last_error',
        'last_triggered_at',
        'trigger_count',
        'meta',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_triggered_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Status-Konstanten
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ERROR = 'error';

    /**
     * Event-Typen
     */
    public const EVENT_NEW_CALL = 'newCall';
    public const EVENT_ON_ANSWER = 'onAnswer';
    public const EVENT_ON_HANGUP = 'onHangup';
    public const EVENT_DTMF = 'dtmf';

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
     * Beziehung zu den empfangenen Webhook-Events
     */
    public function events(): HasMany
    {
        return $this->hasMany(IntegrationsSipgateWebhookEvent::class, 'webhook_id');
    }

    /**
     * Prüft, ob der Webhook aktiv ist
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Scope für aktive Webhooks
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope für verifizierte Webhooks
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Erhöht den Trigger-Counter
     */
    public function incrementTriggerCount(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Markiert den Webhook als fehlerhaft
     */
    public function markAsError(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'last_error' => $errorMessage,
        ]);
    }

    /**
     * Markiert den Webhook als verifiziert
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified' => true,
            'verified_at' => now(),
            'status' => self::STATUS_ACTIVE,
        ]);
    }
}
