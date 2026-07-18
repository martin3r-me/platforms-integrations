<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Exceptions\FlynkApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der FLYNK REST API (/api/*).
 *
 * Auth:      Laravel Sanctum — Authorization: Bearer <api_key>
 * Base-URL:  pro Connection (credentials.base_url), API-Prefix /api wird hier gesetzt
 * IDs:       UUID (string)
 *
 * Dieser Service ist connection-zentriert: jede Methode bekommt die
 * IntegrationConnection übergeben, gegen die gearbeitet wird. Damit funktioniert
 * er sowohl im User-Kontext (UI) als auch in team-scoped Jobs (kein User nötig).
 *
 * Ressourcen-Abdeckung v1: projects (list, show, store, update, destroy).
 * Weitere Ressourcen (tasks, documents, …) werden bei Bedarf ergänzt.
 */
class FlynkApiService
{
    protected const API_PREFIX = '/api';

    protected FlynkIntegrationService $integrationService;

    public function __construct(FlynkIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    // =========================================================================
    // PROJECTS  (= unser "Container" auf FLYNK-Seite)
    // =========================================================================

    /** GET /api/projects — Projects auflisten (zum Verknüpfen bestehender Container). */
    public function listProjects(IntegrationConnection $connection, array $query = []): array
    {
        return $this->get($connection, '/projects', $query);
    }

    /** GET /api/projects/{uuid} — Einzelnes Project abrufen. */
    public function getProject(IntegrationConnection $connection, string $projectUuid): array
    {
        return $this->get($connection, "/projects/{$projectUuid}");
    }

    /** POST /api/projects — Neues Project anlegen ("anlegen"). */
    public function createProject(IntegrationConnection $connection, array $data): array
    {
        return $this->post($connection, '/projects', $data);
    }

    /** PUT /api/projects/{uuid} — Project aktualisieren ("update mit Daten"). */
    public function updateProject(IntegrationConnection $connection, string $projectUuid, array $data): array
    {
        return $this->put($connection, "/projects/{$projectUuid}", $data);
    }

    /** DELETE /api/projects/{uuid} — Project entfernen ("abmelden"). */
    public function deleteProject(IntegrationConnection $connection, string $projectUuid): array
    {
        return $this->delete($connection, "/projects/{$projectUuid}");
    }

    // =========================================================================
    // CONTEXT PUSH + FEEDBACK
    // =========================================================================

    /**
     * POST /api/projects/{uuid}/context — Kontext-Envelope an FLYNK (Ingest).
     * Erwartet die push-UUID zurück, z.B. { "push": "<uuid>", "status": "accepted" }.
     */
    public function pushProjectContext(IntegrationConnection $connection, string $projectUuid, array $envelope): array
    {
        return $this->post($connection, "/projects/{$projectUuid}/context", $envelope);
    }

    /**
     * GET /api/pushes/{uuid} — Feedback zu einem Push: Status + was FLYNK erzeugt hat.
     */
    public function getPush(IntegrationConnection $connection, string $pushUuid): array
    {
        return $this->get($connection, "/pushes/{$pushUuid}");
    }

    // =========================================================================
    // TASKS  (inbound: Rückfragen = Tasks vom Typ "question")
    // =========================================================================

    /** GET /api/tasks — Tasks auflisten (Filter z.B. project_id, type, status). */
    public function listTasks(IntegrationConnection $connection, array $query = []): array
    {
        return $this->get($connection, '/tasks', $query);
    }

    /** GET /api/tasks/{uuid} — Einzelnen Task abrufen. */
    public function getTask(IntegrationConnection $connection, string $taskUuid): array
    {
        return $this->get($connection, "/tasks/{$taskUuid}");
    }

    /** PATCH /api/tasks/{uuid} — Task aktualisieren (z.B. Status nach Antwort). */
    public function updateTask(IntegrationConnection $connection, string $taskUuid, array $data): array
    {
        return $this->patch($connection, "/tasks/{$taskUuid}", $data);
    }

    /** POST /api/tasks/{uuid}/comments — Kommentar/Antwort an einen Task. */
    public function addTaskComment(IntegrationConnection $connection, string $taskUuid, array $data): array
    {
        return $this->post($connection, "/tasks/{$taskUuid}/comments", $data);
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /** @throws FlynkApiException */
    public function get(IntegrationConnection $connection, string $endpoint, array $query = []): array
    {
        return $this->request($connection, 'GET', $endpoint, $query);
    }

    /** @throws FlynkApiException */
    public function post(IntegrationConnection $connection, string $endpoint, array $data = []): array
    {
        return $this->request($connection, 'POST', $endpoint, [], $data);
    }

    /** @throws FlynkApiException */
    public function put(IntegrationConnection $connection, string $endpoint, array $data = []): array
    {
        return $this->request($connection, 'PUT', $endpoint, [], $data);
    }

    /** @throws FlynkApiException */
    public function patch(IntegrationConnection $connection, string $endpoint, array $data = []): array
    {
        return $this->request($connection, 'PATCH', $endpoint, [], $data);
    }

    /** @throws FlynkApiException */
    public function delete(IntegrationConnection $connection, string $endpoint): array
    {
        return $this->request($connection, 'DELETE', $endpoint);
    }

    /**
     * @throws FlynkApiException
     */
    protected function request(
        IntegrationConnection $connection,
        string $method,
        string $endpoint,
        array $query = [],
        array $data = []
    ): array {
        $apiToken = $this->integrationService->getApiToken($connection);
        if (!$apiToken) {
            throw FlynkApiException::unauthorized();
        }

        $baseUrl = $this->integrationService->getBaseUrl($connection);
        if (!$baseUrl) {
            throw FlynkApiException::missingBaseUrl();
        }

        $url = $baseUrl . self::API_PREFIX . $endpoint;

        try {
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (FlynkApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('FLYNK API: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw FlynkApiException::connectionError($e->getMessage());
        }
    }

    /**
     * @throws FlynkApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');

            return is_array($data) ? $data : [];
        }

        $errorMsg = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
        if (is_array($errorMsg)) {
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        } elseif ($errorMsg !== null && !is_string($errorMsg)) {
            $errorMsg = (string) $errorMsg;
        }

        // Nur 401 markiert die Connection als defekt; fachliche Fehler (422 etc.)
        // lassen die Connection aktiv.
        $this->updateConnectionStatus(
            $connection,
            $statusCode === 401 ? 'error' : 'active',
            $errorMsg
        );

        Log::warning('FLYNK API: Fehler-Response', [
            'connection_id' => $connection->id,
            'status_code' => $statusCode,
        ]);

        throw FlynkApiException::fromResponse($statusCode, is_array($data) ? $data : []);
    }

    protected function updateConnectionStatus(
        IntegrationConnection $connection,
        string $status,
        ?string $error = null
    ): void {
        $connection->status = $status;
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();
    }
}
