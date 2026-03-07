<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class CreateFolderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.folders.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen neuen Canva-Ordner. Optional kann ein übergeordneter Ordner angegeben werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'name' => ['type' => 'string', 'description' => 'Name des neuen Ordners.'],
                'parent_folder_id' => ['type' => 'string', 'description' => 'Optional: ID des übergeordneten Ordners.'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['name'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Ordnername ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'name' => $arguments['name'],
            ];

            if (!empty($arguments['parent_folder_id'])) {
                $params['parent_folder_id'] = $arguments['parent_folder_id'];
            }

            $result = $service->createFolder($context->user, $params);

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
            'tags' => ['canva', 'folders', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
            'side_effects' => ['creates'],
        ];
    }
}
