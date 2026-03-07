<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class MoveItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.folder.items.move';
    }

    public function getDescription(): string
    {
        return 'Verschiebt ein Element in einen Canva-Ordner. Das Element kann ein Design, Ordner oder anderer Item-Typ sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'folder_id' => ['type' => 'string', 'description' => 'ID des Ziel-Ordners.'],
                'item_id' => ['type' => 'string', 'description' => 'ID des zu verschiebenden Elements.'],
                'item_type' => ['type' => 'string', 'description' => 'Typ des Elements, z.B. "design", "folder", "image".'],
            ],
            'required' => ['folder_id', 'item_id', 'item_type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['folder_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Ordner-ID ist erforderlich.');
        }

        if (empty($arguments['item_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Element-ID ist erforderlich.');
        }

        if (empty($arguments['item_type'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Element-Typ ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $folderId = $arguments['folder_id'];
            $params = [
                'item_id' => $arguments['item_id'],
                'item_type' => $arguments['item_type'],
            ];

            $result = $service->moveItem($context->user, $folderId, $params);

            return ToolResult::success($result);
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
            'tags' => ['canva', 'folders', 'items', 'move'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
            'side_effects' => ['updates'],
        ];
    }
}
