<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der easybill REST API.
 *
 * Bietet ein generisches Gerüst (get/post/put/delete) mit Bearer-Token-Auth.
 * Konkrete Resource-Wrapper (Customers, Documents, Positions, Projects) werden
 * bei Bedarf in folgenden Tickets ergänzt.
 *
 * Auth-Header: Authorization: Bearer <api_key>
 * Base URL:    https://api.easybill.de/rest/v1
 *
 * Rate Limits laut easybill:
 *  - PLUS:     10 req/min
 *  - BUSINESS: 60 req/min
 * Bei Überschreitung HTTP 429.
 *
 * Default-Listenlimit: 100, max 1000 (Query-Param `limit`).
 *
 * @see https://www.easybill.de/api/
 */
class EasybillApiService
{
    protected const BASE_URL = 'https://api.easybill.de/rest/v1';

    protected EasybillIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(EasybillIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Gibt eine Kopie dieses Services zurück, die eine spezifische Connection verwendet.
     */
    public function forConnection(?int $connectionId): static
    {
        if ($connectionId === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->connectionIdOverride = $connectionId;

        return $clone;
    }

    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $resolver = app(IntegrationConnectionResolver::class);
            $connection = $resolver->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('easybill API: Keine Connection für User', ['user_id' => $user->id]);
            throw EasybillApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // CUSTOMERS
    // =========================================================================

    /** GET /customers — Listet Kunden (paginiert). Filter z.B. `limit`, `page`, `number`, `company_name`. */
    public function listCustomers(User $user, array $query = []): array
    {
        return $this->get($user, '/customers', $query);
    }

    /** GET /customers/{id} — Einzelnen Kunden abrufen. */
    public function getCustomer(User $user, int $customerId): array
    {
        return $this->get($user, "/customers/{$customerId}");
    }

    /** POST /customers — Neuen Kunden anlegen. */
    public function createCustomer(User $user, array $data): array
    {
        return $this->post($user, '/customers', $data);
    }

    /** PUT /customers/{id} — Kunden aktualisieren. */
    public function updateCustomer(User $user, int $customerId, array $data): array
    {
        return $this->put($user, "/customers/{$customerId}", $data);
    }

    /** DELETE /customers/{id} — Kunden löschen. */
    public function deleteCustomer(User $user, int $customerId): array
    {
        return $this->delete($user, "/customers/{$customerId}");
    }

    // =========================================================================
    // CUSTOMER CONTACTS (Subressource je Kunde)
    // =========================================================================

    public function listCustomerContacts(User $user, int $customerId, array $query = []): array
    {
        return $this->get($user, "/customers/{$customerId}/contacts", $query);
    }

    public function getCustomerContact(User $user, int $customerId, int $contactId): array
    {
        return $this->get($user, "/customers/{$customerId}/contacts/{$contactId}");
    }

    public function createCustomerContact(User $user, int $customerId, array $data): array
    {
        return $this->post($user, "/customers/{$customerId}/contacts", $data);
    }

    public function updateCustomerContact(User $user, int $customerId, int $contactId, array $data): array
    {
        return $this->put($user, "/customers/{$customerId}/contacts/{$contactId}", $data);
    }

    public function deleteCustomerContact(User $user, int $customerId, int $contactId): array
    {
        return $this->delete($user, "/customers/{$customerId}/contacts/{$contactId}");
    }

    // =========================================================================
    // CUSTOMER GROUPS
    // =========================================================================

    public function listCustomerGroups(User $user, array $query = []): array
    {
        return $this->get($user, '/customer-groups', $query);
    }

    public function getCustomerGroup(User $user, int $groupId): array
    {
        return $this->get($user, "/customer-groups/{$groupId}");
    }

    public function createCustomerGroup(User $user, array $data): array
    {
        return $this->post($user, '/customer-groups', $data);
    }

    public function updateCustomerGroup(User $user, int $groupId, array $data): array
    {
        return $this->put($user, "/customer-groups/{$groupId}", $data);
    }

    public function deleteCustomerGroup(User $user, int $groupId): array
    {
        return $this->delete($user, "/customer-groups/{$groupId}");
    }

    // =========================================================================
    // DOCUMENTS (Belege — alle Typen: INVOICE, OFFER, CREDIT, DELIVERY_NOTE,
    // ORDER_CONFIRMATION, PAID, REMINDER, STORNO, … über `type`-Feld)
    // =========================================================================

    /**
     * GET /documents — Listet Belege. Wichtige Filter:
     *   - `type` (z.B. INVOICE, OFFER, CREDIT)
     *   - `customer_id`, `document_date_from`, `document_date_to`
     *   - `is_archive`, `status`, `number`
     *   - `limit` (default 100, max 1000), `page`
     */
    public function listDocuments(User $user, array $query = []): array
    {
        return $this->get($user, '/documents', $query);
    }

    public function getDocument(User $user, int $documentId): array
    {
        return $this->get($user, "/documents/{$documentId}");
    }

    /**
     * POST /documents — Beleg erstellen. `type` ist Pflicht.
     * Default-Status ist DRAFT, mit `is_draft=false` direkt finalisiert.
     */
    public function createDocument(User $user, array $data): array
    {
        return $this->post($user, '/documents', $data);
    }

    public function updateDocument(User $user, int $documentId, array $data): array
    {
        return $this->put($user, "/documents/{$documentId}", $data);
    }

    public function deleteDocument(User $user, int $documentId): array
    {
        return $this->delete($user, "/documents/{$documentId}");
    }

    /** PUT /documents/{id}/done — Beleg als erledigt markieren. */
    public function completeDocument(User $user, int $documentId): array
    {
        return $this->put($user, "/documents/{$documentId}/done");
    }

    /** POST /documents/{id}/cancel — Beleg stornieren (erzeugt Storno-Beleg). */
    public function cancelDocument(User $user, int $documentId): array
    {
        return $this->post($user, "/documents/{$documentId}/cancel");
    }

    /**
     * POST /documents/{id}/send/{type} — Beleg versenden.
     * $type: 'email' | 'fax' | 'post' | 'sms' | 'whatsapp'.
     * $data: optionale Override-Felder (subject, message, to_emails, …).
     */
    public function sendDocument(User $user, int $documentId, string $type, array $data = []): array
    {
        return $this->post($user, "/documents/{$documentId}/send/{$type}", $data);
    }

    /**
     * POST /documents/{id}/{type} — Beleg in anderen Belegtyp umwandeln
     * (z.B. Angebot → Rechnung). $targetType ist der Ziel-Belegtyp.
     */
    public function convertDocument(User $user, int $documentId, string $targetType, array $data = []): array
    {
        return $this->post($user, "/documents/{$documentId}/{$targetType}", $data);
    }

    /** GET /documents/{id}/pdf — Beleg als PDF holen (Binary). */
    public function getDocumentPdf(User $user, int $documentId): array
    {
        return $this->getBinary($user, "/documents/{$documentId}/pdf", 'application/pdf');
    }

    /** GET /documents/{id}/jpg — Beleg als JPG holen (Binary). */
    public function getDocumentJpg(User $user, int $documentId): array
    {
        return $this->getBinary($user, "/documents/{$documentId}/jpg", 'image/jpeg');
    }

    /** GET /documents/{id}/download — Beleg-Download (üblicherweise ZIP mit PDF + Anhängen). */
    public function downloadDocument(User $user, int $documentId): array
    {
        return $this->getBinary($user, "/documents/{$documentId}/download", 'application/octet-stream');
    }

    // =========================================================================
    // DOCUMENT PAYMENTS (Zahlungseingänge zu Belegen)
    // =========================================================================

    public function listDocumentPayments(User $user, array $query = []): array
    {
        return $this->get($user, '/document-payments', $query);
    }

    public function getDocumentPayment(User $user, int $paymentId): array
    {
        return $this->get($user, "/document-payments/{$paymentId}");
    }

    public function createDocumentPayment(User $user, array $data): array
    {
        return $this->post($user, '/document-payments', $data);
    }

    public function deleteDocumentPayment(User $user, int $paymentId): array
    {
        return $this->delete($user, "/document-payments/{$paymentId}");
    }

    // =========================================================================
    // DOCUMENT VERSIONS (read-only)
    // =========================================================================

    public function listDocumentVersions(User $user, int $documentId, array $query = []): array
    {
        return $this->get($user, "/documents/{$documentId}/versions", $query);
    }

    public function getDocumentVersion(User $user, int $documentId, int $versionId): array
    {
        return $this->get($user, "/documents/{$documentId}/versions/{$versionId}");
    }

    // =========================================================================
    // INCOMING DOCUMENTS (Eingangsbelege — Lieferantenrechnungen & -gutschriften)
    // easybill REST v1 seit 2026-08-11 (v1.100.0), ausschließlich read-only.
    // =========================================================================

    /**
     * GET /incoming-documents — Listet Eingangsbelege (paginiert). Filter u.a.:
     *   - `created_at` (Zeitraum-Filter)
     *   - Sortierung via `ASC`/`DESC`
     *   - `limit` (default 100, max 1000), `page`
     */
    public function listIncomingDocuments(User $user, array $query = []): array
    {
        return $this->get($user, '/incoming-documents', $query);
    }

    /** GET /incoming-documents/{id} — Einen Eingangsbeleg abrufen. */
    public function getIncomingDocument(User $user, int $incomingDocumentId): array
    {
        return $this->get($user, "/incoming-documents/{$incomingDocumentId}");
    }

    /** GET /incoming-documents/{id}/files — Dateien (Anhänge/Scans) eines Eingangsbelegs listen. */
    public function listIncomingDocumentFiles(User $user, int $incomingDocumentId, array $query = []): array
    {
        return $this->get($user, "/incoming-documents/{$incomingDocumentId}/files", $query);
    }

    /** GET /incoming-documents/{id}/files/{fileId}/download — Datei eines Eingangsbelegs herunterladen (Binary, base64). */
    public function downloadIncomingDocumentFile(User $user, int $incomingDocumentId, int $fileId): array
    {
        return $this->getBinary($user, "/incoming-documents/{$incomingDocumentId}/files/{$fileId}/download", 'application/octet-stream');
    }

    // =========================================================================
    // POSITIONS (Artikel)
    // =========================================================================

    public function listPositions(User $user, array $query = []): array
    {
        return $this->get($user, '/positions', $query);
    }

    public function getPosition(User $user, int $positionId): array
    {
        return $this->get($user, "/positions/{$positionId}");
    }

    public function createPosition(User $user, array $data): array
    {
        return $this->post($user, '/positions', $data);
    }

    public function updatePosition(User $user, int $positionId, array $data): array
    {
        return $this->put($user, "/positions/{$positionId}", $data);
    }

    public function deletePosition(User $user, int $positionId): array
    {
        return $this->delete($user, "/positions/{$positionId}");
    }

    // =========================================================================
    // POSITION GROUPS
    // =========================================================================

    public function listPositionGroups(User $user, array $query = []): array
    {
        return $this->get($user, '/position-groups', $query);
    }

    public function getPositionGroup(User $user, int $groupId): array
    {
        return $this->get($user, "/position-groups/{$groupId}");
    }

    public function createPositionGroup(User $user, array $data): array
    {
        return $this->post($user, '/position-groups', $data);
    }

    public function updatePositionGroup(User $user, int $groupId, array $data): array
    {
        return $this->put($user, "/position-groups/{$groupId}", $data);
    }

    public function deletePositionGroup(User $user, int $groupId): array
    {
        return $this->delete($user, "/position-groups/{$groupId}");
    }

    // =========================================================================
    // DISCOUNTS (Position-Rabatte + Position-Group-Rabatte)
    // =========================================================================

    public function listPositionDiscounts(User $user, array $query = []): array
    {
        return $this->get($user, '/discounts/position', $query);
    }

    public function getPositionDiscount(User $user, int $id): array
    {
        return $this->get($user, "/discounts/position/{$id}");
    }

    public function createPositionDiscount(User $user, array $data): array
    {
        return $this->post($user, '/discounts/position', $data);
    }

    public function updatePositionDiscount(User $user, int $id, array $data): array
    {
        return $this->put($user, "/discounts/position/{$id}", $data);
    }

    public function deletePositionDiscount(User $user, int $id): array
    {
        return $this->delete($user, "/discounts/position/{$id}");
    }

    public function listPositionGroupDiscounts(User $user, array $query = []): array
    {
        return $this->get($user, '/discounts/position-group', $query);
    }

    public function getPositionGroupDiscount(User $user, int $id): array
    {
        return $this->get($user, "/discounts/position-group/{$id}");
    }

    public function createPositionGroupDiscount(User $user, array $data): array
    {
        return $this->post($user, '/discounts/position-group', $data);
    }

    public function updatePositionGroupDiscount(User $user, int $id, array $data): array
    {
        return $this->put($user, "/discounts/position-group/{$id}", $data);
    }

    public function deletePositionGroupDiscount(User $user, int $id): array
    {
        return $this->delete($user, "/discounts/position-group/{$id}");
    }

    // =========================================================================
    // PROJECTS
    // =========================================================================

    public function listProjects(User $user, array $query = []): array
    {
        return $this->get($user, '/projects', $query);
    }

    public function getProject(User $user, int $projectId): array
    {
        return $this->get($user, "/projects/{$projectId}");
    }

    public function createProject(User $user, array $data): array
    {
        return $this->post($user, '/projects', $data);
    }

    public function updateProject(User $user, int $projectId, array $data): array
    {
        return $this->put($user, "/projects/{$projectId}", $data);
    }

    public function deleteProject(User $user, int $projectId): array
    {
        return $this->delete($user, "/projects/{$projectId}");
    }

    // =========================================================================
    // TASKS
    // =========================================================================

    public function listTasks(User $user, array $query = []): array
    {
        return $this->get($user, '/tasks', $query);
    }

    public function getTask(User $user, int $taskId): array
    {
        return $this->get($user, "/tasks/{$taskId}");
    }

    public function createTask(User $user, array $data): array
    {
        return $this->post($user, '/tasks', $data);
    }

    public function updateTask(User $user, int $taskId, array $data): array
    {
        return $this->put($user, "/tasks/{$taskId}", $data);
    }

    public function deleteTask(User $user, int $taskId): array
    {
        return $this->delete($user, "/tasks/{$taskId}");
    }

    // =========================================================================
    // TIME TRACKINGS
    // =========================================================================

    public function listTimeTrackings(User $user, array $query = []): array
    {
        return $this->get($user, '/time-trackings', $query);
    }

    public function getTimeTracking(User $user, int $id): array
    {
        return $this->get($user, "/time-trackings/{$id}");
    }

    public function createTimeTracking(User $user, array $data): array
    {
        return $this->post($user, '/time-trackings', $data);
    }

    public function updateTimeTracking(User $user, int $id, array $data): array
    {
        return $this->put($user, "/time-trackings/{$id}", $data);
    }

    public function deleteTimeTracking(User $user, int $id): array
    {
        return $this->delete($user, "/time-trackings/{$id}");
    }

    // =========================================================================
    // TEXT TEMPLATES
    // =========================================================================

    public function listTextTemplates(User $user, array $query = []): array
    {
        return $this->get($user, '/text-templates', $query);
    }

    public function getTextTemplate(User $user, int $id): array
    {
        return $this->get($user, "/text-templates/{$id}");
    }

    public function createTextTemplate(User $user, array $data): array
    {
        return $this->post($user, '/text-templates', $data);
    }

    public function updateTextTemplate(User $user, int $id, array $data): array
    {
        return $this->put($user, "/text-templates/{$id}", $data);
    }

    public function deleteTextTemplate(User $user, int $id): array
    {
        return $this->delete($user, "/text-templates/{$id}");
    }

    // =========================================================================
    // ATTACHMENTS
    // =========================================================================

    public function listAttachments(User $user, array $query = []): array
    {
        return $this->get($user, '/attachments', $query);
    }

    public function getAttachment(User $user, int $attachmentId): array
    {
        return $this->get($user, "/attachments/{$attachmentId}");
    }

    public function createAttachment(User $user, array $data): array
    {
        return $this->post($user, '/attachments', $data);
    }

    public function updateAttachment(User $user, int $attachmentId, array $data): array
    {
        return $this->put($user, "/attachments/{$attachmentId}", $data);
    }

    public function deleteAttachment(User $user, int $attachmentId): array
    {
        return $this->delete($user, "/attachments/{$attachmentId}");
    }

    /** GET /attachments/{id}/content — Binary-Inhalt des Anhangs. */
    public function getAttachmentContent(User $user, int $attachmentId): array
    {
        return $this->getBinary($user, "/attachments/{$attachmentId}/content");
    }

    // =========================================================================
    // POST BOXES (Brief-Versand-Historie, read-only + delete)
    // =========================================================================

    public function listPostBoxes(User $user, array $query = []): array
    {
        return $this->get($user, '/post-boxes', $query);
    }

    public function getPostBox(User $user, int $id): array
    {
        return $this->get($user, "/post-boxes/{$id}");
    }

    public function deletePostBox(User $user, int $id): array
    {
        return $this->delete($user, "/post-boxes/{$id}");
    }

    // =========================================================================
    // SEPA PAYMENTS
    // =========================================================================

    public function listSepaPayments(User $user, array $query = []): array
    {
        return $this->get($user, '/sepa-payments', $query);
    }

    public function getSepaPayment(User $user, int $id): array
    {
        return $this->get($user, "/sepa-payments/{$id}");
    }

    public function createSepaPayment(User $user, array $data): array
    {
        return $this->post($user, '/sepa-payments', $data);
    }

    public function updateSepaPayment(User $user, int $id, array $data): array
    {
        return $this->put($user, "/sepa-payments/{$id}", $data);
    }

    public function deleteSepaPayment(User $user, int $id): array
    {
        return $this->delete($user, "/sepa-payments/{$id}");
    }

    // =========================================================================
    // STOCKS
    // =========================================================================

    public function listStocks(User $user, array $query = []): array
    {
        return $this->get($user, '/stocks', $query);
    }

    public function getStock(User $user, int $id): array
    {
        return $this->get($user, "/stocks/{$id}");
    }

    public function createStock(User $user, array $data): array
    {
        return $this->post($user, '/stocks', $data);
    }

    // =========================================================================
    // SERIAL NUMBERS
    // =========================================================================

    public function listSerialNumbers(User $user, array $query = []): array
    {
        return $this->get($user, '/serial-numbers', $query);
    }

    public function getSerialNumber(User $user, int $id): array
    {
        return $this->get($user, "/serial-numbers/{$id}");
    }

    public function createSerialNumber(User $user, array $data): array
    {
        return $this->post($user, '/serial-numbers', $data);
    }

    public function deleteSerialNumber(User $user, int $id): array
    {
        return $this->delete($user, "/serial-numbers/{$id}");
    }

    // =========================================================================
    // LOGINS (read-only)
    // =========================================================================

    public function listLogins(User $user, array $query = []): array
    {
        return $this->get($user, '/logins', $query);
    }

    public function getLogin(User $user, int $id): array
    {
        return $this->get($user, "/logins/{$id}");
    }

    // =========================================================================
    // WEBHOOKS
    // =========================================================================

    public function listWebhooks(User $user, array $query = []): array
    {
        return $this->get($user, '/webhooks', $query);
    }

    public function getWebhook(User $user, int $id): array
    {
        return $this->get($user, "/webhooks/{$id}");
    }

    public function createWebhook(User $user, array $data): array
    {
        return $this->post($user, '/webhooks', $data);
    }

    public function updateWebhook(User $user, int $id, array $data): array
    {
        return $this->put($user, "/webhooks/{$id}", $data);
    }

    public function deleteWebhook(User $user, int $id): array
    {
        return $this->delete($user, "/webhooks/{$id}");
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function get(User $user, string $endpoint, array $query = []): array
    {
        return $this->request($user, 'GET', $endpoint, $query);
    }

    /**
     * POST Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function post(User $user, string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request($user, 'POST', $endpoint, $query, $data);
    }

    /**
     * PUT Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function put(User $user, string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request($user, 'PUT', $endpoint, $query, $data);
    }

    /**
     * DELETE Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function delete(User $user, string $endpoint): array
    {
        return $this->request($user, 'DELETE', $endpoint);
    }

    /**
     * @throws EasybillApiException
     */
    protected function request(
        User $user,
        string $method,
        string $endpoint,
        array $query = [],
        array $data = []
    ): array {
        $connection = $this->resolveConnection($user);

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            Log::warning('easybill API: Kein Token für User', ['user_id' => $user->id]);
            throw EasybillApiException::unauthorized();
        }

        $url = self::BASE_URL . $endpoint;

        try {
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url . $this->buildQueryString($query), $data),
                'PUT' => $request->put($url . $this->buildQueryString($query), $data),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (EasybillApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('easybill API: Verbindungsfehler', [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw EasybillApiException::connectionError($e->getMessage());
        }
    }

    /**
     * @throws EasybillApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');
            return $data;
        }

        $errorMsg = $data['message'] ?? $data['error'] ?? null;
        if (is_array($errorMsg)) {
            // easybill liefert bei Validierungsfehlern manchmal Arrays —
            // wir flatten für die last_error-Spalte zu JSON.
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        } elseif ($errorMsg !== null && !is_string($errorMsg)) {
            $errorMsg = (string) $errorMsg;
        }

        $this->updateConnectionStatus(
            $connection,
            $statusCode === 401 ? 'error' : 'active',
            $errorMsg
        );

        Log::warning('easybill API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw EasybillApiException::fromResponse($statusCode, $data);
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

    protected function buildQueryString(array $query): string
    {
        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }

    // =========================================================================
    // FREITEXT-SUCHE (client-seitig)
    //
    // easybill bietet auf den Listen-Endpunkten KEINEN Freitext-Filter, nur
    // exakte Feld-Filter (company_name, number, emails, …). Wird ein Suchbegriff
    // erwartet, paginieren wir die Ressource (server-seitige Feld-Filter aus
    // $query werden respektiert) und filtern client-seitig per Substring, damit
    // der Aufrufer nur Treffer statt der ganzen Liste erhält.
    // =========================================================================

    /**
     * Freitext-Suche über Kunden (company_name, Name, Nummer, E-Mail …).
     *
     * @return array{search: string, total_matched: int, scanned: int, truncated: bool, items: array<int, mixed>, note: string}
     * @throws EasybillApiException
     */
    public function searchCustomers(User $user, string $search, array $query = [], int $maxScan = 5000): array
    {
        return $this->searchListResource(
            $user,
            '/customers',
            $search,
            ['number', 'company_name', 'first_name', 'last_name', 'display_name', 'suffix_1', 'suffix_2', 'emails', 'notes'],
            $query,
            $maxScan
        );
    }

    /**
     * Freitext-Suche über Belege (Nummer, Titel, Textfelder …).
     * Hinweis: Belege lassen sich meist präziser server-seitig filtern
     * (customer_id, number, type, document_date) — nutze dafür direkt die Liste.
     *
     * @return array{search: string, total_matched: int, scanned: int, truncated: bool, items: array<int, mixed>, note: string}
     * @throws EasybillApiException
     */
    public function searchDocuments(User $user, string $search, array $query = [], int $maxScan = 5000): array
    {
        return $this->searchListResource(
            $user,
            '/documents',
            $search,
            ['number', 'title', 'text', 'external_id', 'order_number', 'ref_id'],
            $query,
            $maxScan
        );
    }

    /**
     * Generische client-seitige Substring-Suche über eine paginierte
     * easybill-Listenressource.
     *
     * @param array<int, string> $fields Felder, über die per Substring gesucht wird
     * @return array{search: string, total_matched: int, scanned: int, truncated: bool, items: array<int, mixed>, note: string}
     * @throws EasybillApiException
     */
    protected function searchListResource(
        User $user,
        string $endpoint,
        string $search,
        array $fields,
        array $query = [],
        int $maxScan = 5000
    ): array {
        $needle = mb_strtolower(trim($search));
        $limit = 1000; // easybill-Maximum pro Seite
        $page = 1;
        $scanned = 0;
        $matched = [];
        $truncated = false;

        do {
            $response = $this->get($user, $endpoint, array_merge($query, [
                'limit' => $limit,
                'page' => $page,
            ]));

            $items = $this->extractListItems($response);
            $totalPages = (int) ($response['pages'] ?? 1);

            foreach ($items as $item) {
                $scanned++;
                if (is_array($item) && ($needle === '' || $this->itemMatchesSearch($item, $needle, $fields))) {
                    $matched[] = $item;
                }
            }

            if ($scanned >= $maxScan) {
                $truncated = $page < $totalPages;
                break;
            }

            $page++;
        } while ($page <= $totalPages && !empty($items));

        return [
            'search' => $search,
            'total_matched' => count($matched),
            'scanned' => $scanned,
            'truncated' => $truncated,
            'items' => $matched,
            'note' => 'Client-seitige Substring-Suche über ' . implode(', ', $fields)
                . '. easybill hat server-seitig keinen Freitext-Filter — für exakte Filter query nutzen '
                . '(z.B. company_name, number, emails, customer_id).'
                . ($truncated ? ' ACHTUNG: maxScan erreicht, Ergebnis evtl. unvollständig — Suche per query eingrenzen.' : ''),
        ];
    }

    /**
     * @param array<int, mixed>|array<string, mixed> $response
     * @return array<int, mixed>
     */
    protected function extractListItems(array $response): array
    {
        if (isset($response['items']) && is_array($response['items'])) {
            return $response['items'];
        }

        return array_is_list($response) ? $response : [];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $fields
     */
    protected function itemMatchesSearch(array $item, string $needle, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $item) || $item[$field] === null) {
                continue;
            }

            $value = $item[$field];
            if (is_array($value)) {
                $value = implode(' ', array_map(
                    static fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE),
                    $value
                ));
            }

            if (str_contains(mb_strtolower((string) $value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Holt eine Binary-Response (PDF/JPG/Download) und gibt sie base64-kodiert zurück.
     *
     * @return array{mime: string, data_base64: string, size: int}
     * @throws EasybillApiException
     */
    protected function getBinary(User $user, string $endpoint, string $fallbackMime = 'application/octet-stream'): array
    {
        $connection = $this->resolveConnection($user);

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            throw EasybillApiException::unauthorized();
        }

        $url = self::BASE_URL . $endpoint;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => '*/*',
            ])->get($url);

            if (!$response->successful()) {
                $data = $response->json() ?? [];
                $errorMsg = $data['message'] ?? $data['error'] ?? null;
                if (is_array($errorMsg)) {
                    $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                } elseif ($errorMsg !== null && !is_string($errorMsg)) {
                    $errorMsg = (string) $errorMsg;
                }
                $this->updateConnectionStatus(
                    $connection,
                    $response->status() === 401 ? 'error' : 'active',
                    $errorMsg
                );

                throw EasybillApiException::fromResponse($response->status(), $data);
            }

            $this->updateConnectionStatus($connection, 'active');

            $body = $response->body();
            $mime = $response->header('Content-Type') ?: $fallbackMime;
            $mime = trim(explode(';', $mime)[0]);

            return [
                'mime' => $mime,
                'data_base64' => base64_encode($body),
                'size' => strlen($body),
            ];
        } catch (EasybillApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('easybill API: Verbindungsfehler (Binary)', [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw EasybillApiException::connectionError($e->getMessage());
        }
    }
}
