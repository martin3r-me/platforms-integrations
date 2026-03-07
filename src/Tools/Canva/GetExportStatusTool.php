<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Status eines Canva-Exports abrufen
 *
 * Ruft den aktuellen Status eines zuvor gestarteten Design-Exports ab.
 * Der Export-Job wird asynchron verarbeitet; Status kann "in_progress",
 * "success" oder "failed" sein.
 */
class GetExportStatusTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.export.GET';
    }

    public function getDescription(): string
    {
        return 'Ruft den Status eines Canva-Exports ab. Liefert Status (in_progress/success/failed) und bei Erfolg die Download-URLs.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'export_id' => ['type' => 'string', 'description' => 'Die ID des Export-Jobs (aus der Antwort von integrations.canva.exports.POST).'],
            ],
            'required' => ['export_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $exportId = $arguments['export_id'];
            $export = $service->getExportStatus($context->user, $exportId);

            return ToolResult::success($export->toArray());
        } catch (CanvaApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CANVA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['canva', 'export', 'status'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
