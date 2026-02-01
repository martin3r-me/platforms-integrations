<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für empfangene Sipgate Webhook-Events
 *
 * Speichert empfangene Webhook-Events für:
 * - Idempotency-Prüfung (Duplikat-Erkennung)
 * - Audit-Trail
 * - Retry-Handling bei Fehlern
 */
class IntegrationsSipgateWebhookEvent extends Model
{
    protected $table = 'integrations_sipgate_webhook_events';

    protected $fillable = [
        'webhook_id',
        'integration_connection_id',
        'event_id',
        'idempotency_key',
        'event_type',
        'direction',
        'call_id',
        'caller',
        'callee',
        'caller_name',
        'payload',
        'processing_status',
        'processing_error',
        'processed_at',
        'retry_count',
        'next_retry_at',
        'signature_valid',
        'signature_header',
        'ip_address',
        'headers',
        'request_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'signature_valid' => 'boolean',
    ];

    /**
     * Verarbeitungsstatus
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    /**
     * Maximale Retry-Versuche
     */
    public const MAX_RETRIES = 3;

    /**
     * Beziehung zum Webhook
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(IntegrationsSipgateWebhook::class, 'webhook_id');
    }

    /**
     * Beziehung zur IntegrationConnection
     */
    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * Prüft, ob das Event erfolgreich verarbeitet wurde
     */
    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_PROCESSED;
    }

    /**
     * Prüft, ob das Event fehlgeschlagen ist
     */
    public function isFailed(): bool
    {
        return $this->processing_status === self::STATUS_FAILED;
    }

    /**
     * Prüft, ob ein Retry möglich ist
     */
    public function canRetry(): bool
    {
        return $this->retry_count < self::MAX_RETRIES
            && $this->processing_status === self::STATUS_FAILED;
    }

    /**
     * Scope für ausstehende Events
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', self::STATUS_PENDING);
    }

    /**
     * Scope für Events die retried werden sollen
     */
    public function scopeNeedsRetry($query)
    {
        return $query->where('processing_status', self::STATUS_FAILED)
            ->where('retry_count', '<', self::MAX_RETRIES)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Markiert das Event als verarbeitet
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Markiert das Event als fehlgeschlagen
     */
    public function markAsFailed(string $errorMessage): void
    {
        $nextRetryDelay = $this->calculateNextRetryDelay();

        $this->update([
            'processing_status' => self::STATUS_FAILED,
            'processing_error' => $errorMessage,
            'next_retry_at' => $nextRetryDelay ? now()->addSeconds($nextRetryDelay) : null,
        ]);
    }

    /**
     * Erhöht den Retry-Counter
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update([
            'processing_status' => self::STATUS_PROCESSING,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Berechnet die Wartezeit bis zum nächsten Retry (exponentieller Backoff)
     */
    protected function calculateNextRetryDelay(): ?int
    {
        if ($this->retry_count >= self::MAX_RETRIES - 1) {
            return null; // Keine weiteren Retries
        }

        // Exponentieller Backoff: 30s, 120s, 480s
        return 30 * (4 ** $this->retry_count);
    }

    /**
     * Erstellt ein neues Event aus einem Webhook-Payload
     */
    public static function createFromPayload(
        \Platform\Integrations\DTOs\Sipgate\SipgateWebhookPayload $payload,
        \Illuminate\Http\Request $request,
        ?IntegrationsSipgateWebhook $webhook = null,
        ?IntegrationConnection $connection = null,
        ?string $requestId = null
    ): self {
        return self::create([
            'webhook_id' => $webhook?->id,
            'integration_connection_id' => $connection?->id,
            'event_id' => $payload->getEventId(),
            'idempotency_key' => $payload->getIdempotencyKey(),
            'event_type' => $payload->event,
            'direction' => $payload->direction,
            'call_id' => $payload->callId,
            'caller' => $payload->from,
            'callee' => $payload->to,
            'payload' => $payload->rawPayload,
            'processing_status' => self::STATUS_PENDING,
            'ip_address' => $request->ip(),
            'headers' => json_encode($request->headers->all()),
            'request_id' => $requestId,
        ]);
    }
}
