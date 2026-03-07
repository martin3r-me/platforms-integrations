<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Einzelnes Canva-Design abrufen
 *
 * Ruft die Details eines einzelnen Canva-Designs anhand seiner ID ab.
 */
class GetDesignTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.design.GET';
    }

    public function getDescription(): string
    {
        return 'Ruft ein einzelnes Canva-Design anhand seiner ID ab. Liefert Details wie Titel, Typ, Thumbnail, URLs etc.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'design_id' => ['type' => 'string', 'description' => 'Die ID des Canva-Designs.'],
            ],
            'required' => ['design_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $designId = $arguments['design_id'];
            $design = $service->getDesign($context->user, $designId);

            return ToolResult::success($design->toArray());
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
            'tags' => ['canva', 'design', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
