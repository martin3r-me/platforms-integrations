<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\DTOs\Canva\CanvaAssetDto;
use Platform\Integrations\DTOs\Canva\CanvaAssetUploadJobDto;
use Platform\Integrations\DTOs\Canva\CanvaAutofillJobDto;
use Platform\Integrations\DTOs\Canva\CanvaBrandTemplateDto;
use Platform\Integrations\DTOs\Canva\CanvaCommentReplyDto;
use Platform\Integrations\DTOs\Canva\CanvaCommentThreadDto;
use Platform\Integrations\DTOs\Canva\CanvaDesignDto;
use Platform\Integrations\DTOs\Canva\CanvaDesignPageDto;
use Platform\Integrations\DTOs\Canva\CanvaExportJobDto;
use Platform\Integrations\DTOs\Canva\CanvaFolderDto;
use Platform\Integrations\DTOs\Canva\CanvaFolderItemDto;
use Platform\Integrations\DTOs\Canva\CanvaImportJobDto;
use Platform\Integrations\DTOs\Canva\CanvaResizeJobDto;
use Platform\Integrations\DTOs\Canva\CanvaUserDto;
use Platform\Integrations\Exceptions\CanvaApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Canva Connect API
 *
 * Stellt authentifizierte HTTP-Requests an die Canva API bereit.
 * Credentials (OAuth2 Bearer Token) werden aus der IntegrationConnection gelesen.
 * Token-Refresh wird automatisch durchgeführt.
 *
 * @see https://www.canva.dev/docs/connect/
 */
class CanvaApiService
{
    protected CanvaIntegrationService $integrationService;
    protected ?int $connectionIdOverride = null;

    public function __construct(CanvaIntegrationService $integrationService)
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

    // =========================================================================
    // DESIGNS
    // =========================================================================

    /**
     * @return array{items: CanvaDesignDto[], continuation?: string}
     */
    public function listDesigns(User $user, ?string $query = null, ?string $continuation = null, int $limit = 50): array
    {
        $params = ['limit' => $limit];
        if ($query) {
            $params['query'] = $query;
        }
        if ($continuation) {
            $params['continuation'] = $continuation;
        }

        $data = $this->get($user, '/rest/v1/designs', $params);
        $items = CanvaDesignDto::fromApiResults($data['items'] ?? []);

        return [
            'items' => $items,
            'continuation' => $data['continuation'] ?? null,
        ];
    }

    public function getDesign(User $user, string $designId): CanvaDesignDto
    {
        $data = $this->get($user, "/rest/v1/designs/{$designId}");
        return CanvaDesignDto::fromApiResult($data['design'] ?? $data);
    }

    public function createDesign(User $user, array $params): CanvaDesignDto
    {
        $data = $this->post($user, '/rest/v1/designs', $params);
        return CanvaDesignDto::fromApiResult($data['design'] ?? $data);
    }

    /**
     * @return CanvaDesignPageDto[]
     */
    public function getDesignPages(User $user, string $designId): array
    {
        $data = $this->get($user, "/rest/v1/designs/{$designId}/pages");
        return CanvaDesignPageDto::fromApiResults($data['items'] ?? $data['pages'] ?? []);
    }

    public function getDesignExportFormats(User $user, string $designId): array
    {
        return $this->get($user, "/rest/v1/designs/{$designId}/export_formats");
    }

    // =========================================================================
    // EXPORTS
    // =========================================================================

    public function createExport(User $user, array $params): CanvaExportJobDto
    {
        $data = $this->post($user, '/rest/v1/exports', $params);
        return CanvaExportJobDto::fromApiResult($data['job'] ?? $data);
    }

    public function getExportStatus(User $user, string $exportId): CanvaExportJobDto
    {
        $data = $this->get($user, "/rest/v1/exports/{$exportId}");
        return CanvaExportJobDto::fromApiResult($data['job'] ?? $data);
    }

    /**
     * Erstellt einen Export und pollt bis zum Abschluss.
     */
    public function exportAndWait(User $user, array $params): CanvaExportJobDto
    {
        $job = $this->createExport($user, $params);
        return $this->pollExportJob($user, $job->id);
    }

    protected function pollExportJob(User $user, string $exportId): CanvaExportJobDto
    {
        $interval = config('integrations.canva.async_job.poll_interval', 2);
        $maxAttempts = config('integrations.canva.async_job.max_poll_attempts', 30);

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($interval);
            $job = $this->getExportStatus($user, $exportId);

            if ($job->status === 'success' || $job->status === 'failed') {
                if ($job->status === 'failed') {
                    throw CanvaApiException::jobFailed($exportId, json_encode($job->errors));
                }
                return $job;
            }
        }

        throw CanvaApiException::jobTimeout("exports/{$exportId}");
    }

    // =========================================================================
    // FOLDERS
    // =========================================================================

    /**
     * @return array{items: CanvaFolderItemDto[], continuation?: string}
     */
    public function listFolderItems(User $user, string $folderId, ?string $continuation = null, int $limit = 50): array
    {
        $params = ['limit' => $limit];
        if ($continuation) {
            $params['continuation'] = $continuation;
        }

        $data = $this->get($user, "/rest/v1/folders/{$folderId}/items", $params);
        $items = CanvaFolderItemDto::fromApiResults($data['items'] ?? []);

        return [
            'items' => $items,
            'continuation' => $data['continuation'] ?? null,
        ];
    }

    public function getFolder(User $user, string $folderId): CanvaFolderDto
    {
        $data = $this->get($user, "/rest/v1/folders/{$folderId}");
        return CanvaFolderDto::fromApiResult($data['folder'] ?? $data);
    }

    public function createFolder(User $user, array $params): CanvaFolderDto
    {
        $data = $this->post($user, '/rest/v1/folders', $params);
        return CanvaFolderDto::fromApiResult($data['folder'] ?? $data);
    }

    public function deleteFolder(User $user, string $folderId): bool
    {
        $this->delete($user, "/rest/v1/folders/{$folderId}");
        return true;
    }

    public function moveItem(User $user, string $folderId, array $params): array
    {
        return $this->post($user, "/rest/v1/folders/{$folderId}/items", $params);
    }

    // =========================================================================
    // ASSETS
    // =========================================================================

    public function uploadAsset(User $user, array $params): CanvaAssetUploadJobDto
    {
        $data = $this->post($user, '/rest/v1/asset-uploads', $params);
        return CanvaAssetUploadJobDto::fromApiResult($data['job'] ?? $data);
    }

    public function getAsset(User $user, string $assetId): CanvaAssetDto
    {
        $data = $this->get($user, "/rest/v1/assets/{$assetId}");
        return CanvaAssetDto::fromApiResult($data['asset'] ?? $data);
    }

    public function deleteAsset(User $user, string $assetId): bool
    {
        $this->delete($user, "/rest/v1/assets/{$assetId}");
        return true;
    }

    // =========================================================================
    // COMMENTS
    // =========================================================================

    public function createCommentThread(User $user, array $params): CanvaCommentThreadDto
    {
        $data = $this->post($user, '/rest/v1/comments', $params);
        return CanvaCommentThreadDto::fromApiResult($data['comment'] ?? $data);
    }

    public function getCommentThread(User $user, string $threadId): CanvaCommentThreadDto
    {
        $data = $this->get($user, "/rest/v1/comments/{$threadId}");
        return CanvaCommentThreadDto::fromApiResult($data['comment'] ?? $data);
    }

    public function createReply(User $user, string $threadId, array $params): CanvaCommentReplyDto
    {
        $data = $this->post($user, "/rest/v1/comments/{$threadId}/replies", $params);
        return CanvaCommentReplyDto::fromApiResult($data['reply'] ?? $data);
    }

    public function getReply(User $user, string $threadId, string $replyId): CanvaCommentReplyDto
    {
        $data = $this->get($user, "/rest/v1/comments/{$threadId}/replies/{$replyId}");
        return CanvaCommentReplyDto::fromApiResult($data['reply'] ?? $data);
    }

    /**
     * @return array{items: CanvaCommentReplyDto[], continuation?: string}
     */
    public function listReplies(User $user, string $threadId, ?string $continuation = null): array
    {
        $params = [];
        if ($continuation) {
            $params['continuation'] = $continuation;
        }

        $data = $this->get($user, "/rest/v1/comments/{$threadId}/replies", $params);
        $items = CanvaCommentReplyDto::fromApiResults($data['items'] ?? $data['replies'] ?? []);

        return [
            'items' => $items,
            'continuation' => $data['continuation'] ?? null,
        ];
    }

    // =========================================================================
    // BRAND TEMPLATES
    // =========================================================================

    /**
     * @return array{items: CanvaBrandTemplateDto[], continuation?: string}
     */
    public function listBrandTemplates(User $user, ?string $query = null, ?string $continuation = null, int $limit = 50): array
    {
        $params = ['limit' => $limit];
        if ($query) {
            $params['query'] = $query;
        }
        if ($continuation) {
            $params['continuation'] = $continuation;
        }

        $data = $this->get($user, '/rest/v1/brand-templates', $params);
        $items = CanvaBrandTemplateDto::fromApiResults($data['items'] ?? []);

        return [
            'items' => $items,
            'continuation' => $data['continuation'] ?? null,
        ];
    }

    public function getBrandTemplate(User $user, string $brandTemplateId): CanvaBrandTemplateDto
    {
        $data = $this->get($user, "/rest/v1/brand-templates/{$brandTemplateId}");
        return CanvaBrandTemplateDto::fromApiResult($data['brand_template'] ?? $data);
    }

    public function getBrandTemplateDataset(User $user, string $brandTemplateId): array
    {
        return $this->get($user, "/rest/v1/brand-templates/{$brandTemplateId}/dataset");
    }

    // =========================================================================
    // AUTOFILL
    // =========================================================================

    public function createAutofill(User $user, array $params): CanvaAutofillJobDto
    {
        $data = $this->post($user, '/rest/v1/autofills', $params);
        return CanvaAutofillJobDto::fromApiResult($data['job'] ?? $data);
    }

    // =========================================================================
    // RESIZES
    // =========================================================================

    public function createResize(User $user, array $params): CanvaResizeJobDto
    {
        $data = $this->post($user, '/rest/v1/resizes', $params);
        return CanvaResizeJobDto::fromApiResult($data['job'] ?? $data);
    }

    public function getResizeStatus(User $user, string $resizeId): CanvaResizeJobDto
    {
        $data = $this->get($user, "/rest/v1/resizes/{$resizeId}");
        return CanvaResizeJobDto::fromApiResult($data['job'] ?? $data);
    }

    // =========================================================================
    // DESIGN IMPORTS
    // =========================================================================

    public function importDesign(User $user, array $params): CanvaImportJobDto
    {
        $data = $this->post($user, '/rest/v1/imports', $params);
        return CanvaImportJobDto::fromApiResult($data['job'] ?? $data);
    }

    // =========================================================================
    // USERS
    // =========================================================================

    public function getMe(User $user): CanvaUserDto
    {
        $data = $this->get($user, '/rest/v1/users/me');
        return CanvaUserDto::fromApiResult($data);
    }

    public function getProfile(User $user, string $userId): CanvaUserDto
    {
        $data = $this->get($user, "/rest/v1/users/{$userId}");
        return CanvaUserDto::fromApiResult($data);
    }

    public function getCapabilities(User $user): array
    {
        return $this->get($user, '/rest/v1/connect/capabilities');
    }

    // =========================================================================
    // INTERNAL HTTP METHODS
    // =========================================================================

    /**
     * GET Request an die Canva API
     *
     * @throws CanvaApiException
     */
    protected function get(User $user, string $endpoint, array $query = []): array
    {
        return $this->request($user, $endpoint, [], 'GET', $query);
    }

    /**
     * POST Request an die Canva API
     *
     * @throws CanvaApiException
     */
    protected function post(User $user, string $endpoint, array $data = []): array
    {
        return $this->request($user, $endpoint, $data, 'POST');
    }

    /**
     * DELETE Request an die Canva API
     *
     * @throws CanvaApiException
     */
    protected function delete(User $user, string $endpoint): array
    {
        return $this->request($user, $endpoint, [], 'DELETE');
    }

    /**
     * Führt einen HTTP Request an die Canva API aus
     *
     * @throws CanvaApiException
     */
    protected function request(User $user, string $endpoint, array $data = [], string $method = 'POST', array $query = []): array
    {
        $connection = $this->resolveConnection($user);
        $accessToken = $this->getAccessTokenWithRefresh($connection);

        if (!$accessToken) {
            throw CanvaApiException::unauthorized();
        }

        $baseUrl = config('integrations.canva.api_base_url', 'https://api.canva.com');
        $url = $baseUrl . $endpoint;
        $timeout = config('integrations.canva.timeout.default', 30);
        $connectTimeout = config('integrations.canva.timeout.connect', 10);

        try {
            $http = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->timeout($timeout)->connectTimeout($connectTimeout);

            $response = match ($method) {
                'GET' => $http->get($url, $query),
                'POST' => $http->asJson()->post($url, $data),
                'DELETE' => $http->delete($url),
                default => $http->asJson()->post($url, $data),
            };

            return $this->handleResponse($response, $connection);
        } catch (CanvaApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Canva API: Verbindungsfehler', [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw CanvaApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws CanvaApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiger oder abgelaufener Token');
            throw CanvaApiException::unauthorized();
        }

        if ($statusCode === 429) {
            throw CanvaApiException::rateLimitExceeded();
        }

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');
            return $data;
        }

        $this->updateConnectionStatus($connection, 'active', $data['message'] ?? null);

        Log::warning('Canva API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw CanvaApiException::fromResponse($statusCode, $data);
    }

    /**
     * Löst die IntegrationConnection für den User auf.
     */
    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $resolver = app(IntegrationConnectionResolver::class);
            $connection = $resolver->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('Canva API: Keine Connection für User', ['user_id' => $user->id]);
            throw CanvaApiException::noConnection();
        }

        return $connection;
    }

    /**
     * Holt den Access-Token mit automatischem Refresh bei Bedarf.
     */
    protected function getAccessTokenWithRefresh(IntegrationConnection $connection): ?string
    {
        $accessToken = $this->integrationService->getAccessToken($connection);
        if (!$accessToken) {
            return null;
        }

        if ($this->integrationService->isTokenExpired($connection)) {
            try {
                $connection = $this->integrationService->refreshToken($connection);
                return $this->integrationService->getAccessToken($connection);
            } catch (CanvaApiException $e) {
                Log::error('Canva API: Token-Refresh fehlgeschlagen', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
                $this->integrationService->markConnectionAsError($connection, $e->getMessage());
                return null;
            }
        }

        return $accessToken;
    }

    /**
     * Aktualisiert den Status der IntegrationConnection
     */
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
