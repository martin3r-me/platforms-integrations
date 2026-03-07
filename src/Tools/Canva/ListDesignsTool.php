<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Canva Designs auflisten
 *
 * Listet die Designs des authentifizierten Canva-Users auf (paginiert).
 * Unterstuetzt Suche und Cursor-basierte Pagination.
 */
class ListDesignsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.designs.GET';
    }

    public function getDescription(): string
    {
        return 'Listet Canva-Designs auf (paginiert). Unterstuetzt optionale Suche und Cursor-basierte Pagination via continuation-Token.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'query' => ['type' => 'string', 'description' => 'Optional: Suchbegriff zum Filtern der Designs.'],
                'continuation' => ['type' => 'string', 'description' => 'Optional: Continuation-Token fuer die naechste Seite (aus vorheriger Antwort).'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: Maximale Anzahl der Ergebnisse (default: 50).'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $query = $arguments['query'] ?? null;
            $continuation = $arguments['continuation'] ?? null;
            $limit = $arguments['limit'] ?? 50;

            $result = $service->listDesigns($context->user, $query, $continuation, $limit);

            return ToolResult::success([
                'items' => array_map(fn($design) => $design->toArray(), $result['items']),
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
            'tags' => ['canva', 'designs', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
