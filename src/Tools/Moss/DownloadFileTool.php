<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\MossApiException;
use Platform\Integrations\Services\MossApiService;

class DownloadFileTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.file.content';
    }

    public function getDescription(): string
    {
        return 'GET /v1/files/{fileId}/content - Lädt eine Moss-Beleg-Datei (PDF/Bild) herunter. Rückgabe: mime, data_base64, size, filename. file_id via integrations.moss.files.search ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Moss-Verbindung.',
                ],
                'file_id' => [
                    'type' => 'string',
                    'description' => 'UUID der Beleg-Datei.',
                ],
            ],
            'required' => ['file_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $fileId = $arguments['file_id'] ?? null;
        if (!$fileId) {
            return ToolResult::error('VALIDATION_ERROR', 'file_id ist erforderlich.');
        }

        try {
            $service = app(MossApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->downloadFile($context->user, (string) $fileId);

            return ToolResult::success($result);
        } catch (MossApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'MOSS_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['moss', 'spend-management', 'files', 'receipts', 'belege', 'download'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
