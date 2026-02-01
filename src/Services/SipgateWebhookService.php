<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\DTOs\Sipgate\SipgateWebhookPayload;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsSipgateWebhook;
use Platform\Integrations\Models\IntegrationsSipgateWebhookEvent;

/**
 * Service für Sipgate Webhook-Verarbeitung
 *
 * Dieser Service verarbeitet eingehende Sipgate Webhooks und bietet:
 * - Signatur-Verifizierung (HMAC)
 * - Idempotency-Handling (Duplikat-Erkennung)
 * - Event-Persistierung
 * - Event-Verarbeitung mit Retry-Logik
 *
 * @see https://developer.sipgate.io/push-api
 */
class SipgateWebhookService
{
    /**
     * Cache-Prefix für Idempotency-Keys
     */
    protected const IDEMPOTENCY_CACHE_PREFIX = 'sipgate_webhook_idempotency:';

    /**
     * TTL für Idempotency-Cache (24 Stunden)
     */
    protected const IDEMPOTENCY_TTL = 86400;

    /**
     * Verifiziert die Webhook-Signatur
     *
     * Sipgate verwendet eine einfache Header-basierte Signatur.
     * Der Header 'X-Sipgate-Signature' enthält einen HMAC-SHA256 Hash
     * des Request-Bodies mit dem Webhook-Secret.
     *
     * HINWEIS: Falls Sipgate keine Signatur sendet, kann diese
     * Prüfung über die Config deaktiviert werden.
     */
    public function verifySignature(Request $request): bool
    {
        // Prüfe, ob Signatur-Verifizierung aktiviert ist
        if (!config('integrations.sipgate.webhook.signature_enabled', true)) {
            Log::debug('Sipgate webhook signature verification disabled');
            return true;
        }

        $secret = config('integrations.sipgate.webhook.secret');

        // Wenn kein Secret konfiguriert ist, Verifizierung überspringen
        if (!$secret) {
            Log::warning('Sipgate webhook secret not configured, skipping verification');
            return true;
        }

        // Signatur aus Header lesen
        $signature = $request->header('X-Sipgate-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('Signature');

        if (!$signature) {
            Log::warning('Sipgate webhook signature header missing');
            return false;
        }

        // Erwartete Signatur berechnen
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Signatur vergleichen (timing-safe)
        $isValid = hash_equals($expectedSignature, $signature)
            || hash_equals('sha256=' . $expectedSignature, $signature); // Manchmal mit Prefix

        if (!$isValid) {
            Log::warning('Sipgate webhook signature mismatch', [
                'expected_prefix' => substr($expectedSignature, 0, 10) . '...',
                'received_prefix' => substr($signature, 0, 10) . '...',
            ]);
        }

        return $isValid;
    }

    /**
     * Prüft, ob ein Webhook-Event bereits verarbeitet wurde (Idempotency)
     *
     * Verwendet sowohl Cache als auch Datenbank für zuverlässige Duplikat-Erkennung.
     */
    public function isDuplicate(SipgateWebhookPayload $payload): bool
    {
        $idempotencyKey = $payload->getIdempotencyKey();

        // 1. Schnelle Prüfung über Cache
        $cacheKey = self::IDEMPOTENCY_CACHE_PREFIX . $idempotencyKey;
        if (Cache::has($cacheKey)) {
            Log::debug('Sipgate webhook duplicate detected (cache)', [
                'idempotency_key' => $idempotencyKey,
            ]);
            return true;
        }

        // 2. Prüfung in Datenbank
        $exists = IntegrationsSipgateWebhookEvent::where('idempotency_key', $idempotencyKey)->exists();
        if ($exists) {
            // In Cache eintragen für schnellere zukünftige Prüfungen
            Cache::put($cacheKey, true, self::IDEMPOTENCY_TTL);

            Log::debug('Sipgate webhook duplicate detected (database)', [
                'idempotency_key' => $idempotencyKey,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Markiert ein Event als verarbeitet (für Idempotency)
     */
    public function markAsProcessed(SipgateWebhookPayload $payload): void
    {
        $cacheKey = self::IDEMPOTENCY_CACHE_PREFIX . $payload->getIdempotencyKey();
        Cache::put($cacheKey, true, self::IDEMPOTENCY_TTL);
    }

    /**
     * Verarbeitet einen Webhook
     *
     * @return array|null Response-Daten (z.B. XML für IVR)
     */
    public function processWebhook(
        SipgateWebhookPayload $payload,
        Request $request,
        string $requestId
    ): ?array {
        // 1. Connection finden (optional, basierend auf User-ID im Payload)
        $connection = $this->resolveConnection($payload);

        // 2. Webhook-Registrierung finden (optional)
        $webhook = $this->resolveWebhook($payload, $connection);

        // 3. Event persistieren
        $event = IntegrationsSipgateWebhookEvent::createFromPayload(
            $payload,
            $request,
            $webhook,
            $connection,
            $requestId
        );

        // 4. Signatur-Status speichern
        $signatureValid = $this->verifySignature($request);
        $event->update([
            'signature_valid' => $signatureValid,
            'signature_header' => $request->header('X-Sipgate-Signature'),
        ]);

        // 5. Webhook-Counter aktualisieren
        if ($webhook) {
            $webhook->incrementTriggerCount();
        }

        // 6. Idempotency-Key cachen
        $this->markAsProcessed($payload);

        // 7. Event verarbeiten
        try {
            $result = $this->handleEvent($payload, $event);

            // Event als verarbeitet markieren
            $event->markAsProcessed();

            return $result;
        } catch (\Exception $e) {
            Log::error('Sipgate webhook processing error', [
                'event_id' => $event->id,
                'event_type' => $payload->event,
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ]);

            // Event als fehlgeschlagen markieren
            $event->markAsFailed($e->getMessage());

            // Trotzdem 200 zurückgeben, um Retries zu vermeiden
            return null;
        }
    }

    /**
     * Verarbeitet ein spezifisches Event
     *
     * @return array|null Response-Daten
     */
    protected function handleEvent(
        SipgateWebhookPayload $payload,
        IntegrationsSipgateWebhookEvent $event
    ): ?array {
        Log::info('Sipgate webhook event received', [
            'event' => $payload->event,
            'call_id' => $payload->callId,
            'direction' => $payload->direction,
            'from' => $payload->from,
            'to' => $payload->to,
        ]);

        return match ($payload->event) {
            SipgateWebhookPayload::EVENT_NEW_CALL => $this->handleNewCall($payload),
            SipgateWebhookPayload::EVENT_ON_ANSWER => $this->handleOnAnswer($payload),
            SipgateWebhookPayload::EVENT_ON_HANGUP => $this->handleOnHangup($payload),
            SipgateWebhookPayload::EVENT_DTMF => $this->handleDtmf($payload),
            default => null,
        };
    }

    /**
     * Behandelt ein newCall-Event
     *
     * Hier können z.B. Benachrichtigungen ausgelöst oder
     * IVR-Anweisungen zurückgegeben werden.
     */
    protected function handleNewCall(SipgateWebhookPayload $payload): ?array
    {
        Log::info('New call', [
            'direction' => $payload->direction,
            'from' => $payload->from,
            'to' => $payload->to,
            'call_id' => $payload->callId,
        ]);

        // Event-System triggern (falls vorhanden)
        // event(new \Platform\Integrations\Events\SipgateNewCall($payload));

        // Optional: XML-Response für IVR
        // return ['xml' => $this->generateIvrResponse($payload)];

        return null;
    }

    /**
     * Behandelt ein onAnswer-Event
     */
    protected function handleOnAnswer(SipgateWebhookPayload $payload): ?array
    {
        Log::info('Call answered', [
            'call_id' => $payload->callId,
            'answering_number' => $payload->answeringNumber,
        ]);

        return null;
    }

    /**
     * Behandelt ein onHangup-Event
     */
    protected function handleOnHangup(SipgateWebhookPayload $payload): ?array
    {
        Log::info('Call hung up', [
            'call_id' => $payload->callId,
            'cause' => $payload->cause,
        ]);

        return null;
    }

    /**
     * Behandelt ein dtmf-Event
     */
    protected function handleDtmf(SipgateWebhookPayload $payload): ?array
    {
        Log::info('DTMF received', [
            'call_id' => $payload->callId,
            'dtmf' => $payload->dtmf,
        ]);

        // Optional: IVR-Response basierend auf DTMF-Eingabe
        // return ['xml' => $this->generateDtmfResponse($payload)];

        return null;
    }

    /**
     * Findet die passende Connection basierend auf dem Webhook-Payload
     */
    protected function resolveConnection(SipgateWebhookPayload $payload): ?IntegrationConnection
    {
        // Versuche anhand der User-ID zu finden
        if ($payload->userId || $payload->fullUserId) {
            $sipgateUserId = $payload->fullUserId ?? $payload->userId;

            // Suche Connection mit passendem Sipgate-User
            $connection = IntegrationConnection::query()
                ->whereHas('integration', fn($q) => $q->where('key', 'sipgate'))
                ->where('status', 'active')
                ->whereRaw("JSON_EXTRACT(credentials, '$.oauth.sipgate_sub') = ?", [$sipgateUserId])
                ->first();

            if ($connection) {
                return $connection;
            }
        }

        // Alternativ: Anhand der Telefonnummer
        // (falls Phone-Number-Mapping implementiert ist)

        return null;
    }

    /**
     * Findet die passende Webhook-Registrierung
     */
    protected function resolveWebhook(
        SipgateWebhookPayload $payload,
        ?IntegrationConnection $connection
    ): ?IntegrationsSipgateWebhook {
        $query = IntegrationsSipgateWebhook::query()
            ->where('event_type', $payload->event)
            ->where('status', IntegrationsSipgateWebhook::STATUS_ACTIVE);

        if ($connection) {
            $query->where('integration_connection_id', $connection->id);
        }

        return $query->first();
    }

    /**
     * Generiert eine IVR-Ansage-Response (XML)
     *
     * Beispiel für automatische Ansage bei eingehenden Anrufen.
     */
    public function generatePlayResponse(string $audioUrl): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Play><Url>' . htmlspecialchars($audioUrl) . '</Url></Play>'
            . '</Response>';
    }

    /**
     * Generiert eine IVR-Gather-Response (XML)
     *
     * Für DTMF-Eingaben des Anrufers.
     */
    public function generateGatherResponse(
        string $prompt,
        int $maxDigits = 1,
        int $timeout = 10
    ): string {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Gather maxDigits="' . $maxDigits . '" timeout="' . $timeout . '">'
            . '<Play><Url>' . htmlspecialchars($prompt) . '</Url></Play>'
            . '</Gather>'
            . '</Response>';
    }

    /**
     * Generiert eine Weiterleitungs-Response (XML)
     */
    public function generateDialResponse(string $targetNumber): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Dial><Number>' . htmlspecialchars($targetNumber) . '</Number></Dial>'
            . '</Response>';
    }

    /**
     * Generiert eine Auflege-Response (XML)
     */
    public function generateHangupResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Response>'
            . '<Hangup />'
            . '</Response>';
    }

    /**
     * Retry-Verarbeitung für fehlgeschlagene Webhooks
     *
     * Kann z.B. über einen Scheduled Job aufgerufen werden.
     */
    public function processRetries(): int
    {
        $events = IntegrationsSipgateWebhookEvent::needsRetry()
            ->limit(50)
            ->get();

        $processed = 0;

        foreach ($events as $event) {
            try {
                $event->incrementRetry();

                // Payload rekonstruieren
                $payload = SipgateWebhookPayload::fromRaw($event->payload ?? []);

                // Event erneut verarbeiten
                $this->handleEvent($payload, $event);

                $event->markAsProcessed();
                $processed++;

                Log::info('Sipgate webhook retry successful', [
                    'event_id' => $event->id,
                    'retry_count' => $event->retry_count,
                ]);
            } catch (\Exception $e) {
                $event->markAsFailed($e->getMessage());

                Log::warning('Sipgate webhook retry failed', [
                    'event_id' => $event->id,
                    'retry_count' => $event->retry_count,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Bereinigt alte Webhook-Events
     *
     * Events älter als die angegebene Anzahl Tage werden gelöscht.
     */
    public function cleanupOldEvents(int $daysToKeep = 30): int
    {
        $cutoff = now()->subDays($daysToKeep);

        $deleted = IntegrationsSipgateWebhookEvent::where('created_at', '<', $cutoff)
            ->where('processing_status', IntegrationsSipgateWebhookEvent::STATUS_PROCESSED)
            ->delete();

        Log::info('Sipgate webhook events cleaned up', [
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoff->toDateString(),
        ]);

        return $deleted;
    }
}
