<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\MossApiService;
use Platform\Integrations\Exceptions\MossApiException;

class ListDimensionItemsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.dimension_items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /v1/dimensions/{id}/items - Listet Items einer Moss Dimension auf.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Moss-Verbindung.',
                ],
                'dimension_id' => [
                    'type' => 'string',
                    'description' => 'ID der Dimension.',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Seitennummer für Paginierung.',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Anzahl Ergebnisse pro Seite.',
                ],
            ],
            'required' => ['dimension_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(MossApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $filters = array_filter([
                'page' => $arguments['page'] ?? null,
                'per_page' => $arguments['per_page'] ?? null,
            ], fn ($v) => $v !== null);

            $result = $service->getDimensionItems($context->user, $arguments['dimension_id'], $filters);

            return ToolResult::success($result);
        } catch (MossApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'MOSS_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['moss', 'spend-management', 'dimensions', 'items', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
