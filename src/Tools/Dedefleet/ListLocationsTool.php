<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;
use Platform\Integrations\Support\FieldProjection;

/**
 * GET /Location/List — Standorte (DedeFleet, read-only).
 */
class ListLocationsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.locations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /Location/List — Listet Standorte aus DedeFleet. Optionale Filter/Query via `params`.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'params' => [
                    'type' => 'object',
                    'description' => 'Optionale Query-Parameter bzw. Filter (siehe Swagger /swagger/data/api/2).',
                ],
                'fields' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $params = is_array($arguments['params'] ?? null) ? $arguments['params'] : [];

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->listLocations($context->user, $params);

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = FieldProjection::apply($result, $arguments['fields']);
            }

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
            'category' => 'query',
            'tags' => ['dedefleet', 'locations', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
