<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class ListIncomingDocumentFilesTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.incoming-document.files.GET';
    }

    public function getDescription(): string
    {
        return 'GET /incoming-documents/{id}/files — Dateien (Scans/Anhänge) eines Eingangsbelegs listen. Zum Herunterladen einer Datei anschließend integrations.easybill.incoming-document.file.download nutzen. Read-only.';
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
            'query' => [
              'type' => 'object',
              'description' => 'Optionale Query-Parameter (z.B. limit, page).',
            ],
          ],
          'required' => [
            0 => 'incoming_document_id',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['incoming_document_id'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->listIncomingDocumentFiles(
                $context->user,
                (string) $arguments['incoming_document_id'],
                $arguments['query'] ?? []
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
              3 => 'list',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
