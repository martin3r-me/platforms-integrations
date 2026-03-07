<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class ExportAndWaitTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.export.wait';
    }

    public function getDescription(): string
    {
        return 'Startet einen Export und wartet auf Abschluss (synchron). Gibt die Export-URLs zurück sobald der Job fertig ist.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'design_id' => ['type' => 'string', 'description' => 'ID des Canva-Designs, das exportiert werden soll.'],
                'format_type' => ['type' => 'string', 'description' => 'Export-Format, z.B. "pdf", "png", "jpg", "svg", "gif", "pptx", "mp4".'],
                'pages' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von Seitennummern die exportiert werden sollen (0-basiert).',
                ],
                'quality' => ['type' => 'string', 'description' => 'Optional: Exportqualität, z.B. "regular", "pro".'],
            ],
            'required' => ['design_id', 'format_type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['design_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Design-ID ist erforderlich.');
        }

        if (empty($arguments['format_type'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Format-Typ ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'design_id' => $arguments['design_id'],
                'format_type' => $arguments['format_type'],
            ];

            if (!empty($arguments['pages'])) {
                $params['pages'] = $arguments['pages'];
            }

            if (!empty($arguments['quality'])) {
                $params['quality'] = $arguments['quality'];
            }

            $result = $service->exportAndWait($context->user, $params);

            return ToolResult::success($result->toArray());
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
            'tags' => ['canva', 'export', 'sync'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
