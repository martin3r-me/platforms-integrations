<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class UploadAssetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.assets.upload';
    }

    public function getDescription(): string
    {
        return 'Lädt ein Asset zu Canva hoch. Das Asset wird über eine URL bereitgestellt und in Canva importiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'name' => ['type' => 'string', 'description' => 'Name des Assets in Canva.'],
                'url' => ['type' => 'string', 'description' => 'URL der Datei zum Upload.'],
            ],
            'required' => ['name', 'url'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['name'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Asset-Name ist erforderlich.');
        }

        if (empty($arguments['url'])) {
            return ToolResult::error('VALIDATION_ERROR', 'URL ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'name' => $arguments['name'],
                'url' => $arguments['url'],
            ];

            $result = $service->uploadAsset($context->user, $params);

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
            'tags' => ['canva', 'assets', 'upload'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
            'side_effects' => ['creates'],
        ];
    }
}
