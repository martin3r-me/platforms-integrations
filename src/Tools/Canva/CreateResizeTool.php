<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Resize-Job erstellen
 *
 * Erstellt einen Resize-Job fuer ein Canva-Design.
 */
class CreateResizeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.resizes.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen Resize-Job fuer ein Design.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'design_id' => ['type' => 'string', 'description' => 'ID des Canva-Designs, das resized werden soll.'],
                'width' => ['type' => 'integer', 'description' => 'Zielbreite in Pixel.'],
                'height' => ['type' => 'integer', 'description' => 'Zielhoehe in Pixel.'],
                'title' => ['type' => 'string', 'description' => 'Optional: Titel fuer das resultierende Design.'],
            ],
            'required' => ['design_id', 'width', 'height'],
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

        if (empty($arguments['width'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Breite (width) ist erforderlich.');
        }

        if (empty($arguments['height'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Hoehe (height) ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'design_id' => $arguments['design_id'],
                'width' => $arguments['width'],
                'height' => $arguments['height'],
            ];

            if (!empty($arguments['title'])) {
                $params['title'] = $arguments['title'];
            }

            $result = $service->createResize($context->user, $params);

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
            'tags' => ['canva', 'resize', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
