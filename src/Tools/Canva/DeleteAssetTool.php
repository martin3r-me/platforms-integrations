<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class DeleteAssetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.asset.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht ein Canva-Asset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'asset_id' => ['type' => 'string', 'description' => 'ID des zu löschenden Assets.'],
            ],
            'required' => ['asset_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['asset_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Asset-ID ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $service->deleteAsset($context->user, $arguments['asset_id']);

            return ToolResult::success([
                'message' => 'Asset erfolgreich gelöscht.',
                'asset_id' => $arguments['asset_id'],
            ]);
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
            'tags' => ['canva', 'asset', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
            'side_effects' => ['deletes'],
        ];
    }
}
