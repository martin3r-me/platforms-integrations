<?php

namespace Platform\Integrations\DTOs\Sipgate;

use Carbon\Carbon;

/**
 * DTO für Sipgate Webhook-Payloads
 *
 * Kapselt und normalisiert die Daten von eingehenden Webhooks.
 *
 * Sipgate Webhook-Events:
 * - newCall: Neuer eingehender/ausgehender Anruf
 * - onAnswer: Anruf wurde angenommen
 * - onHangup: Anruf wurde beendet
 * - dtmf: DTMF-Tasten wurden gedrückt
 *
 * @see https://developer.sipgate.io/push-api
 */
class SipgateWebhookPayload
{
    public const EVENT_NEW_CALL = 'newCall';
    public const EVENT_ON_ANSWER = 'onAnswer';
    public const EVENT_ON_HANGUP = 'onHangup';
    public const EVENT_DTMF = 'dtmf';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public function __construct(
        public readonly string $event,
        public readonly ?string $callId = null,
        public readonly ?string $direction = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $user = null,
        public readonly ?array $users = null,
        public readonly ?string $userId = null,
        public readonly ?string $fullUserId = null,
        public readonly ?string $originalCallId = null,
        public readonly ?string $diversion = null,
        public readonly ?Carbon $timestamp = null,
        public readonly ?string $cause = null,
        public readonly ?string $answeringNumber = null,
        public readonly ?string $dtmf = null,
        public readonly array $rawPayload = [],
    ) {
    }

    /**
     * Erstellt eine Instanz aus den rohen Webhook-Daten
     */
    public static function fromRaw(array $data): self
    {
        return new self(
            event: $data['event'] ?? 'unknown',
            callId: $data['callId'] ?? null,
            direction: $data['direction'] ?? null,
            from: $data['from'] ?? null,
            to: $data['to'] ?? null,
            user: $data['user'] ?? null,
            users: $data['users'] ?? null,
            userId: $data['userId'] ?? null,
            fullUserId: $data['fullUserId'] ?? null,
            originalCallId: $data['originalCallId'] ?? null,
            diversion: $data['diversion'] ?? null,
            timestamp: isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : null,
            cause: $data['cause'] ?? null,
            answeringNumber: $data['answeringNumber'] ?? null,
            dtmf: $data['dtmf'] ?? null,
            rawPayload: $data,
        );
    }

    /**
     * Prüft, ob es ein eingehender Anruf ist
     */
    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }

    /**
     * Prüft, ob es ein ausgehender Anruf ist
     */
    public function isOutgoing(): bool
    {
        return $this->direction === self::DIRECTION_OUT;
    }

    /**
     * Prüft, ob es ein neuer Anruf ist
     */
    public function isNewCall(): bool
    {
        return $this->event === self::EVENT_NEW_CALL;
    }

    /**
     * Prüft, ob der Anruf angenommen wurde
     */
    public function isAnswered(): bool
    {
        return $this->event === self::EVENT_ON_ANSWER;
    }

    /**
     * Prüft, ob der Anruf beendet wurde
     */
    public function isHangup(): bool
    {
        return $this->event === self::EVENT_ON_HANGUP;
    }

    /**
     * Prüft, ob DTMF-Eingaben empfangen wurden
     */
    public function isDtmf(): bool
    {
        return $this->event === self::EVENT_DTMF;
    }

    /**
     * Gibt die Anrufer-Nummer zurück (normalisiert)
     */
    public function getCallerNumber(): ?string
    {
        return $this->isIncoming() ? $this->from : $this->to;
    }

    /**
     * Gibt die angerufene Nummer zurück (normalisiert)
     */
    public function getCalleeNumber(): ?string
    {
        return $this->isIncoming() ? $this->to : $this->from;
    }

    /**
     * Generiert eine Idempotency-Key für Duplikat-Erkennung
     */
    public function getIdempotencyKey(): string
    {
        $parts = [
            $this->event,
            $this->callId ?? 'unknown',
            $this->timestamp?->timestamp ?? time(),
        ];

        // Für DTMF auch die gedrückten Tasten berücksichtigen
        if ($this->isDtmf() && $this->dtmf) {
            $parts[] = $this->dtmf;
        }

        return hash('sha256', implode('-', $parts));
    }

    /**
     * Gibt eine eindeutige Event-ID zurück
     */
    public function getEventId(): string
    {
        return 'sipgate-' . ($this->callId ?? bin2hex(random_bytes(8))) . '-' . $this->event;
    }

    /**
     * Konvertiert zu Array
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'call_id' => $this->callId,
            'direction' => $this->direction,
            'from' => $this->from,
            'to' => $this->to,
            'user' => $this->user,
            'users' => $this->users,
            'user_id' => $this->userId,
            'full_user_id' => $this->fullUserId,
            'original_call_id' => $this->originalCallId,
            'diversion' => $this->diversion,
            'timestamp' => $this->timestamp?->toIso8601String(),
            'cause' => $this->cause,
            'answering_number' => $this->answeringNumber,
            'dtmf' => $this->dtmf,
        ];
    }

    /**
     * Gibt eine menschenlesbare Beschreibung des Events zurück
     */
    public function getDescription(): string
    {
        $direction = $this->isIncoming() ? 'Eingehender' : 'Ausgehender';

        return match ($this->event) {
            self::EVENT_NEW_CALL => "{$direction} Anruf von {$this->from} an {$this->to}",
            self::EVENT_ON_ANSWER => "Anruf angenommen: {$this->from} -> {$this->to}",
            self::EVENT_ON_HANGUP => "Anruf beendet: {$this->from} -> {$this->to}" . ($this->cause ? " ({$this->cause})" : ''),
            self::EVENT_DTMF => "DTMF-Eingabe: {$this->dtmf}",
            default => "Unbekanntes Event: {$this->event}",
        };
    }
}
