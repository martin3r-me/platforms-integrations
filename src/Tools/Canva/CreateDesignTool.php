<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Neues Canva-Design erstellen
 *
 * Erstellt ein neues Design in Canva mit dem angegebenen Typ und optionalen Parametern.
 */
class CreateDesignTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.designs.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt ein neues Canva-Design. Erfordert einen Design-Typ (z.B. "Presentation", "Poster", "A4Document"). Optional: Titel, Abmessungen, Asset-ID.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'design_type' => ['type' => 'string', 'description' => 'Der Design-Typ, z.B. "Presentation", "Poster", "A4Document", "InstagramPost", "Logo" etc.'],
                'title' => ['type' => 'string', 'description' => 'Optional: Titel des neuen Designs.'],
                'width' => ['type' => 'integer', 'description' => 'Optional: Breite in Pixel (fuer benutzerdefinierte Abmessungen).'],
                'height' => ['type' => 'integer', 'description' => 'Optional: Hoehe in Pixel (fuer benutzerdefinierte Abmessungen).'],
                'asset_id' => ['type' => 'string', 'description' => 'Optional: Asset-ID eines bestehenden Canva-Assets als Grundlage.'],
            ],
            'required' => ['design_type'],
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
                'design_type' => $arguments['design_type'],
            ];

            if (isset($arguments['title'])) {
                $params['title'] = $arguments['title'];
            }

            if (isset($arguments['width']) && isset($arguments['height'])) {
                $params['width'] = $arguments['width'];
                $params['height'] = $arguments['height'];
            }

            if (isset($arguments['asset_id'])) {
                $params['asset_id'] = $arguments['asset_id'];
            }

            $design = $service->createDesign($context->user, $params);

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
            'category' => 'action',
            'tags' => ['canva', 'design', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
