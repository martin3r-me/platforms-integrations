<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * Generisches RPC-Tool für DedeFleet: ruft eine beliebige {Resource}/{Action}-
 * Kombination der Swagger-Spec (v2) auf. Deckt alle Endpunkte ab, die kein
 * dediziertes Tool haben (z.B. Order/Create, Tour/Optimize, Location/Update).
 */
class CallTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.call';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Generischer DedeFleet-API-Aufruf (RPC). endpoint = "{Resource}/{Action}", z.B. "Order/Create",
        "Tour/Optimize", "Location/Update". method = GET oder POST (Default POST — die meisten Aktionen
        sind POST; nur List-Endpunkte wie Customer/List, Location/List, Order/ListUnassigned sind GET).

        payload: bei GET Query-Parameter, bei POST der JSON-Body. Feld-/Schemadetails siehe
        https://ortung.dedefleet.de/swagger (Spec /swagger/data/api/2) bzw. integrations.dedefleet.overview.

        SCHREIBEND: Create/Update/Delete/Assign/Optimize verändern Daten in DedeFleet — sorgfältig nutzen.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'endpoint' => [
                    'type' => 'string',
                    'description' => 'RPC-Pfad "{Resource}/{Action}", z.B. "Order/Create", "Tour/List". Ohne Base-URL/Version.',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST'],
                    'description' => 'HTTP-Methode. Default: POST. GET nur für List-Endpunkte.',
                ],
                'payload' => [
                    'type' => 'object',
                    'description' => 'GET: Query-Parameter. POST: JSON-Body. Struktur je Endpunkt — siehe Swagger.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
                ],
            ],
            'required' => ['endpoint'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $endpoint = trim((string) ($arguments['endpoint'] ?? ''));
        if ($endpoint === '') {
            return ToolResult::error('VALIDATION_ERROR', 'Parameter "endpoint" ("{Resource}/{Action}", z.B. "Tour/List") ist erforderlich.');
        }

        $method = strtoupper((string) ($arguments['method'] ?? 'POST'));
        if (!in_array($method, ['GET', 'POST'], true)) {
            return ToolResult::error('VALIDATION_ERROR', 'method muss GET oder POST sein.');
        }

        $payload = is_array($arguments['payload'] ?? null) ? $arguments['payload'] : [];

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->call($context->user, $method, $endpoint, $payload);

            return ToolResult::success($result);
        } catch (DedefleetApiException $e) {
            return ToolResult::error($e->getDedefleetErrorCode() ?? 'DEDEFLEET_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dedefleet', 'rpc', 'generic'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
