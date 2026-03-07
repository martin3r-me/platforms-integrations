<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Canva Design-Export starten
 *
 * Startet einen asynchronen Design-Export. Der Export-Status kann
 * anschliessend mit GetExportStatusTool abgefragt werden.
 */
class CreateExportTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.exports.POST';
    }

    public function getDescription(): string
    {
        return 'Startet einen Design-Export (async). Der Export laeuft asynchron – den Status mit integrations.canva.export.GET abfragen. Unterstuetzt Formate wie pdf, png, jpg.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'design_id' => ['type' => 'string', 'description' => 'Die ID des zu exportierenden Canva-Designs.'],
                'format_type' => ['type' => 'string', 'description' => 'Export-Format, z.B. "pdf", "png", "jpg".'],
                'pages' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von Seiten-Indizes (0-basiert), die exportiert werden sollen. Wenn nicht angegeben, werden alle Seiten exportiert.',
                ],
                'quality' => ['type' => 'string', 'description' => 'Optional: Export-Qualitaet, z.B. "regular" oder "high".'],
            ],
            'required' => ['design_id', 'format_type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'design_id' => $arguments['design_id'],
                'format_type' => $arguments['format_type'],
            ];

            if (isset($arguments['pages'])) {
                $params['pages'] = $arguments['pages'];
            }

            if (isset($arguments['quality'])) {
                $params['quality'] = $arguments['quality'];
            }

            $export = $service->createExport($context->user, $params);

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
            'category' => 'action',
            'tags' => ['canva', 'export', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
