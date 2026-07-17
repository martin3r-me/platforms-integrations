<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/invoices
 * Alle Eingangsrechnungen zu einem Mandanten
 */
class InvoicesGetTool implements ToolContract, ToolMetadataContract
{
    /** Query-Parameter-Namen dieses Endpunkts (Top-Level-Argumente). */
    private const QUERY_KEYS = ['page', 'size', 'sort', 'sortDir', 'search', 'filters', 'changedSince'];

    public function getName(): string
    {
        return 'integrations.necta.v1.invoices.GET';
    }

    public function getDescription(): string
    {
        return 'Alle Eingangsrechnungen zu einem Mandanten
Parameter sind TOP-LEVEL-Argumente (kein query-Wrapper).

Query-Parameter:
- page: integer [REQUIRED] — Seitennummer (0-basiert)
- size: integer [REQUIRED] — Anzahl der Einträge pro Seite
- sort: string — Name des Sortierfeldes (z. B. \'name\' oder \'createdAt\')
- sortDir: string
- search: string
- filters: string — Spaltenfilter als JSON-String (PrimeNG-Format) {"name": { "value": "Max", "matchMode": "contains" },"age":  { "value": 30,    "matchMode": "equals" }}
- changedSince: string — Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.
';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seitennummer (0-basiert)'],
                'size' => ['type' => 'integer', 'description' => 'Anzahl der Einträge pro Seite'],
                'sort' => ['type' => 'string', 'description' => 'Name des Sortierfeldes (z. B. \'name\' oder \'createdAt\')'],
                'sortDir' => ['type' => 'string', 'description' => 'Query-Parameter sortDir'],
                'search' => ['type' => 'string', 'description' => 'Query-Parameter search'],
                'filters' => ['type' => 'string', 'description' => 'Spaltenfilter als JSON-String (PrimeNG-Format) {"name": { "value": "Max", "matchMode": "contains" },"age":  { "value": 30,    "matchMode": "equals" }}'],
                'changedSince' => ['type' => 'string', 'description' => 'Gibt nur Datensätze zurück, deren ChangeDate größer oder gleich dem angegebenen Zeitpunkt ist (inklusiv). Format: ISO 8601, z. B. 2024-01-15T08:30:00Z. Optional.'],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen necta-Connection.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }


        $path = '/api/v1/{tenantId}/invoices';

        $query = [];
        foreach (self::QUERY_KEYS as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }
        if (!array_key_exists('page', $query)) { $query['page'] = 1; }
        if (!array_key_exists('size', $query)) { $query['size'] = 100; }

        $data = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->callSpec($context->user, 'GET', $path, $query, $data);
            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = NectaApiV1Service::projectFields($result, $arguments['fields']);
            }
            return ToolResult::success($result);
        } catch (NectaApiException $e) {
            return ToolResult::error($e->getNectaErrorCode() ?? 'NECTA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['necta', 'v1', 'invoices'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
