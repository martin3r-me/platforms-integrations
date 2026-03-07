<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class ListFolderItemsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.folder.items.GET';
    }

    public function getDescription(): string
    {
        return 'Listet Elemente in einem Canva-Ordner auf. Gibt Designs, Ordner und andere Items zurück. Paginiert via continuation-Token.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'folder_id' => ['type' => 'string', 'description' => 'ID des Canva-Ordners.'],
                'continuation' => ['type' => 'string', 'description' => 'Optional: Continuation-Token für Pagination.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: Maximale Anzahl der Ergebnisse (default: 50).'],
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

            $folderId = $arguments['folder_id'];
            $continuation = $arguments['continuation'] ?? null;
            $limit = $arguments['limit'] ?? 50;

            $result = $service->listFolderItems($context->user, $folderId, $continuation, $limit);

            return ToolResult::success([
                'items' => array_map(fn($item) => $item->toArray(), $result['items']),
                'continuation' => $result['continuation'] ?? null,
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
            'category' => 'query',
            'tags' => ['canva', 'folders', 'items', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
