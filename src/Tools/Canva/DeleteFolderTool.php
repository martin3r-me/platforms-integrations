<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class DeleteFolderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.folder.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht einen Canva-Ordner. ACHTUNG: Der Ordner und sein Inhalt werden unwiderruflich gelöscht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'folder_id' => ['type' => 'string', 'description' => 'ID des zu löschenden Canva-Ordners.'],
            ],
            'required' => ['folder_id'],
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

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $service->deleteFolder($context->user, $arguments['folder_id']);

            return ToolResult::success(['message' => 'Ordner erfolgreich gelöscht.']);
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
            'tags' => ['canva', 'folders', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
            'side_effects' => ['deletes'],
        ];
    }
}
