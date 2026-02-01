<?php

namespace Platform\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\DTOs\Sipgate\SipgateCallRequest;
use Platform\Integrations\DTOs\Sipgate\SipgateContactRequest;
use Platform\Integrations\DTOs\Sipgate\SipgateFaxRequest;
use Platform\Integrations\DTOs\Sipgate\SipgateHistoryFilter;
use Platform\Integrations\DTOs\Sipgate\SipgateSmsRequest;
use Platform\Integrations\DTOs\Sipgate\SipgateWebhookPayload;
use Platform\Integrations\Exceptions\SipgateApiException;
use Platform\Integrations\Services\SipgateApiService;
use Platform\Integrations\Services\SipgateIntegrationService;
use Platform\Integrations\Services\SipgateWebhookService;

/**
 * Controller für Sipgate API Endpunkte
 *
 * Dieser Controller stellt alle Sipgate-Funktionen über eine REST-API bereit:
 * - OAuth-Flow (connect, callback, disconnect)
 * - Account & User Management
 * - Anrufe (Click-to-Call)
 * - SMS
 * - Fax
 * - Anrufhistorie
 * - Webhooks
 * - Health-Checks
 */
class SipgateController extends Controller
{
    public function __construct(
        protected SipgateApiService $sipgateApiService,
        protected SipgateIntegrationService $integrationService,
        protected SipgateWebhookService $webhookService,
    ) {
    }

    // =========================================================================
    // CONNECTION MANAGEMENT
    // =========================================================================

    /**
     * Testet die Sipgate-Verbindung
     *
     * GET /api/integrations/sipgate/test
     */
    public function test(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $connection = $this->integrationService->getConnectionForUser($user);

            if (!$connection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keine Sipgate-Verbindung vorhanden.',
                    'connected' => false,
                ], 404);
            }

            $result = $this->integrationService->testConnection($connection);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'connected' => $result['success'],
                'data' => $result['data'] ?? null,
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Trennt die Sipgate-Verbindung (revoke + delete)
     *
     * DELETE /api/integrations/sipgate/disconnect
     */
    public function disconnect(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->integrationService->deleteConnectionForUser($user);

            if ($result) {
                Log::info('Sipgate connection disconnected', [
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Sipgate-Verbindung wurde getrennt.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Keine Sipgate-Verbindung gefunden.',
            ], 404);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // ACCOUNT & USER INFO
    // =========================================================================

    /**
     * Ruft die Benutzerinformationen ab
     *
     * GET /api/integrations/sipgate/userinfo
     */
    public function userinfo(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getUserInfo($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft die Account-Informationen ab
     *
     * GET /api/integrations/sipgate/account
     */
    public function account(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getAccount($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft das Guthaben ab
     *
     * GET /api/integrations/sipgate/balance
     */
    public function balance(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getBalance($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft alle Users des Accounts ab
     *
     * GET /api/integrations/sipgate/users
     */
    public function users(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getUsers($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // TELEFONNUMMERN & GERÄTE
    // =========================================================================

    /**
     * Ruft alle Telefonnummern ab
     *
     * GET /api/integrations/sipgate/numbers
     */
    public function numbers(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getNumbers($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft alle Geräte ab
     *
     * GET /api/integrations/sipgate/devices
     */
    public function devices(Request $request): JsonResponse
    {
        try {
            $userId = $request->query('userId');
            $result = $this->sipgateApiService->getDevices($request->user(), $userId);
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // ANRUFE (CALLS)
    // =========================================================================

    /**
     * Initiiert einen Anruf (Click-to-Call)
     *
     * POST /api/integrations/sipgate/calls
     */
    public function initiateCall(Request $request): JsonResponse
    {
        try {
            $dto = SipgateCallRequest::fromRequest($request->all());

            $result = $this->sipgateApiService->initiateCall(
                $request->user(),
                $dto->caller,
                $dto->callee,
                $dto->callerId
            );

            Log::info('Sipgate call initiated', [
                'user_id' => $request->user()->id,
                'callee' => $dto->callee,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anruf wurde initiiert.',
                'data' => $result,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Beendet einen aktiven Anruf
     *
     * DELETE /api/integrations/sipgate/calls/{sessionId}
     */
    public function hangupCall(Request $request, string $sessionId): JsonResponse
    {
        try {
            $this->sipgateApiService->hangupCall($request->user(), $sessionId);

            Log::info('Sipgate call hung up', [
                'user_id' => $request->user()->id,
                'session_id' => $sessionId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anruf wurde beendet.',
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // ANRUFHISTORIE (HISTORY)
    // =========================================================================

    /**
     * Ruft die Anrufhistorie ab
     *
     * GET /api/integrations/sipgate/history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $filter = SipgateHistoryFilter::fromRequest($request->all());
            $result = $this->sipgateApiService->getHistory($request->user(), $filter->toArray());
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft einen einzelnen History-Eintrag ab
     *
     * GET /api/integrations/sipgate/history/{id}
     */
    public function historyEntry(Request $request, string $id): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getHistoryEntry($request->user(), $id);
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Archiviert einen History-Eintrag
     *
     * PUT /api/integrations/sipgate/history/{id}/archive
     */
    public function archiveHistoryEntry(Request $request, string $id): JsonResponse
    {
        try {
            $archived = (bool) $request->input('archived', true);
            $result = $this->sipgateApiService->archiveHistoryEntry($request->user(), $id, $archived);
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Löscht einen History-Eintrag
     *
     * DELETE /api/integrations/sipgate/history/{id}
     */
    public function deleteHistoryEntry(Request $request, string $id): JsonResponse
    {
        try {
            $this->sipgateApiService->deleteHistoryEntry($request->user(), $id);
            return response()->json([
                'success' => true,
                'message' => 'Eintrag wurde gelöscht.',
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // SMS
    // =========================================================================

    /**
     * Sendet eine SMS
     *
     * POST /api/integrations/sipgate/sms
     */
    public function sendSms(Request $request): JsonResponse
    {
        try {
            $dto = SipgateSmsRequest::fromRequest($request->all());

            $result = $this->sipgateApiService->sendSms(
                $request->user(),
                $dto->smsId,
                $dto->recipient,
                $dto->message
            );

            Log::info('Sipgate SMS sent', [
                'user_id' => $request->user()->id,
                'recipient' => $dto->recipient,
                'parts' => $dto->getPartCount(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SMS wurde gesendet.',
                'data' => $result,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft die SMS-Erweiterungen ab
     *
     * GET /api/integrations/sipgate/sms/extensions
     */
    public function smsExtensions(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getSmsExtensions($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // FAX
    // =========================================================================

    /**
     * Sendet ein Fax
     *
     * POST /api/integrations/sipgate/fax
     */
    public function sendFax(Request $request): JsonResponse
    {
        try {
            $dto = SipgateFaxRequest::fromRequest($request->all());

            $result = $this->sipgateApiService->sendFax(
                $request->user(),
                $dto->faxlineId,
                $dto->recipient,
                $dto->base64Content,
                $dto->filename
            );

            Log::info('Sipgate Fax sent', [
                'user_id' => $request->user()->id,
                'recipient' => $dto->recipient,
                'size' => $dto->getFileSizeFormatted(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fax wird gesendet.',
                'data' => $result,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft die Faxlines ab
     *
     * GET /api/integrations/sipgate/faxlines
     */
    public function faxlines(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getFaxlines($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // VOICEMAIL
    // =========================================================================

    /**
     * Ruft die Voicemails ab
     *
     * GET /api/integrations/sipgate/voicemails
     */
    public function voicemails(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getVoicemails($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // KONTAKTE
    // =========================================================================

    /**
     * Ruft alle Kontakte ab
     *
     * GET /api/integrations/sipgate/contacts
     */
    public function contacts(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query('limit', 100);
            $offset = (int) $request->query('offset', 0);

            $result = $this->sipgateApiService->getContacts($request->user(), $limit, $offset);
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Ruft einen einzelnen Kontakt ab
     *
     * GET /api/integrations/sipgate/contacts/{id}
     */
    public function contact(Request $request, string $id): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getContact($request->user(), $id);
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Erstellt einen neuen Kontakt
     *
     * POST /api/integrations/sipgate/contacts
     */
    public function createContact(Request $request): JsonResponse
    {
        try {
            $dto = SipgateContactRequest::fromRequest($request->all());
            $result = $this->sipgateApiService->createContact($request->user(), $dto->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Kontakt wurde erstellt.',
                'data' => $result,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Aktualisiert einen Kontakt
     *
     * PUT /api/integrations/sipgate/contacts/{id}
     */
    public function updateContact(Request $request, string $id): JsonResponse
    {
        try {
            $dto = SipgateContactRequest::fromRequest($request->all());
            $result = $this->sipgateApiService->updateContact($request->user(), $id, $dto->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Kontakt wurde aktualisiert.',
                'data' => $result,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Löscht einen Kontakt
     *
     * DELETE /api/integrations/sipgate/contacts/{id}
     */
    public function deleteContact(Request $request, string $id): JsonResponse
    {
        try {
            $this->sipgateApiService->deleteContact($request->user(), $id);

            return response()->json([
                'success' => true,
                'message' => 'Kontakt wurde gelöscht.',
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // WEBHOOKS
    // =========================================================================

    /**
     * Empfängt Sipgate Webhooks (Push-API)
     *
     * POST /api/integrations/sipgate/webhook
     *
     * Diese Route ist NICHT authentifiziert!
     */
    public function webhook(Request $request): Response
    {
        $requestId = 'webhook-' . bin2hex(random_bytes(8));

        try {
            // Signatur verifizieren
            if (!$this->webhookService->verifySignature($request)) {
                Log::warning('Sipgate webhook signature verification failed', [
                    'request_id' => $requestId,
                    'ip' => $request->ip(),
                ]);

                return response('Signature verification failed', 401);
            }

            // Payload parsen
            $payload = SipgateWebhookPayload::fromRaw($request->all());

            // Idempotency prüfen
            if ($this->webhookService->isDuplicate($payload)) {
                Log::debug('Sipgate webhook duplicate ignored', [
                    'event_id' => $payload->getEventId(),
                    'request_id' => $requestId,
                ]);

                return response('OK', 200);
            }

            // Event verarbeiten
            $result = $this->webhookService->processWebhook($payload, $request, $requestId);

            Log::info('Sipgate webhook processed', [
                'event' => $payload->event,
                'call_id' => $payload->callId,
                'request_id' => $requestId,
            ]);

            // Response im XML-Format (für IVR/DTMF)
            if ($result && isset($result['xml'])) {
                return response($result['xml'], 200)
                    ->header('Content-Type', 'application/xml');
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Sipgate webhook error', [
                'error' => $e->getMessage(),
                'request_id' => $requestId,
            ]);

            // Bei Fehlern 200 zurückgeben, um Retries zu vermeiden
            return response('OK', 200);
        }
    }

    /**
     * Ruft die aktuellen Webhook-Einstellungen ab
     *
     * GET /api/integrations/sipgate/webhooks
     */
    public function getWebhooks(Request $request): JsonResponse
    {
        try {
            $result = $this->sipgateApiService->getWebhooks($request->user());
            return response()->json($result);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Konfiguriert die Webhook-Einstellungen
     *
     * PUT /api/integrations/sipgate/webhooks
     */
    public function setWebhooks(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'incomingUrl' => ['nullable', 'url', 'max:2048'],
                'outgoingUrl' => ['nullable', 'url', 'max:2048'],
                'log' => ['nullable', 'boolean'],
            ]);

            $result = $this->sipgateApiService->setWebhooks($request->user(), $validated);

            Log::info('Sipgate webhooks configured', [
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook-Einstellungen wurden aktualisiert.',
                'data' => $result,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validierungsfehler',
                'errors' => $e->errors(),
            ], 422);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Löscht alle Webhook-Einstellungen
     *
     * DELETE /api/integrations/sipgate/webhooks
     */
    public function deleteWebhooks(Request $request): JsonResponse
    {
        try {
            $this->sipgateApiService->deleteWebhooks($request->user());

            Log::info('Sipgate webhooks deleted', [
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook-Einstellungen wurden gelöscht.',
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // HEALTH & METRICS
    // =========================================================================

    /**
     * Health-Check für die Sipgate-Integration
     *
     * GET /api/integrations/sipgate/health
     */
    public function health(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $connection = $this->integrationService->getConnectionForUser($user);

            if (!$connection) {
                return response()->json([
                    'healthy' => false,
                    'status' => 'not_connected',
                    'message' => 'Keine Sipgate-Verbindung vorhanden.',
                ]);
            }

            // API Health-Check
            $apiHealth = $this->sipgateApiService->healthCheck($user);

            // Token-Metriken
            $tokenMetrics = $this->integrationService->getTokenMetrics($connection);

            // Circuit-Breaker-Status
            $circuitStatus = $this->sipgateApiService->getCircuitBreakerStatus();

            return response()->json([
                'healthy' => $apiHealth['healthy'] && $tokenMetrics['is_healthy'],
                'status' => $apiHealth['healthy'] ? 'healthy' : 'degraded',
                'api' => $apiHealth,
                'token' => $tokenMetrics,
                'circuit_breaker' => $circuitStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'healthy' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Token-Metriken abrufen
     *
     * GET /api/integrations/sipgate/metrics/tokens
     */
    public function tokenMetrics(Request $request): JsonResponse
    {
        try {
            $connection = $this->integrationService->getConnectionForUser($request->user());

            if (!$connection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keine Sipgate-Verbindung vorhanden.',
                ], 404);
            }

            $metrics = $this->integrationService->getTokenMetrics($connection);

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    /**
     * Token-History abrufen
     *
     * GET /api/integrations/sipgate/tokens/history
     */
    public function tokenHistory(Request $request): JsonResponse
    {
        try {
            $connection = $this->integrationService->getConnectionForUser($request->user());

            if (!$connection) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keine Sipgate-Verbindung vorhanden.',
                ], 404);
            }

            $limit = (int) $request->query('limit', 50);
            $history = $this->integrationService->getTokenHistory($connection, $limit);

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (SipgateApiException $e) {
            return $this->handleSipgateException($e);
        }
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    /**
     * Behandelt Sipgate API Exceptions einheitlich
     */
    protected function handleSipgateException(SipgateApiException $e): JsonResponse
    {
        $response = $e->toArray();

        // Logging
        if ($e->isServerError()) {
            Log::error('Sipgate API server error', [
                'code' => $e->getSipgateErrorCode(),
                'message' => $e->getMessage(),
                'request_id' => $e->getRequestId(),
            ]);
        } elseif (!$e->isClientError() || $e->isRateLimited()) {
            Log::warning('Sipgate API error', [
                'code' => $e->getSipgateErrorCode(),
                'message' => $e->getMessage(),
                'request_id' => $e->getRequestId(),
            ]);
        }

        return response()->json($response, $e->getHttpStatusCode());
    }
}
