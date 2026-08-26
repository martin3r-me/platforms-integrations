<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class DownloadIncomingDocumentFileTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.incoming-document.file.download';
    }

    public function getDescription(): string
    {
        return 'GET /incoming-documents/{id}/files/{fileId}/download — Datei eines Eingangsbelegs herunterladen (base64). Die file_id stammt aus integrations.easybill.incoming-document.files.GET. Read-only.';
    }

    public function getSchema(): array
    {
        return [
          'type' => 'object',
          'properties' => [
            'connection_id' => [
              'type' => 'integer',
              'description' => 'Optional: ID einer spezifischen easybill-Connection.',
            ],
            'incoming_document_id' => [
              'type' => 'string',
              'description' => 'ID (UUID) des Eingangsbelegs, z.B. "01a03966-520f-711b-8018-19a6638d3272".',
            ],
            'file_id' => [
              'type' => 'string',
              'description' => 'ID (UUID) der herunterzuladenden Datei (aus incoming-document.files.GET).',
            ],
          ],
          'required' => [
            0 => 'incoming_document_id',
            1 => 'file_id',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['incoming_document_id', 'file_id'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->downloadIncomingDocumentFile(
                $context->user,
                (string) $arguments['incoming_document_id'],
                (string) $arguments['file_id']
            );
            return ToolResult::success($result);
        } catch (EasybillApiException $e) {
            return ToolResult::error($e->getEasybillErrorCode() ?? 'EASYBILL_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => [
              0 => 'easybill',
              1 => 'incoming-documents',
              2 => 'files',
              3 => 'download',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
