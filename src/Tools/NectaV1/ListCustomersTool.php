<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * necta.one API v1 — GET /api/v1/{tenantId}/customers (Kunden).
 */
class ListCustomersTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.customers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /api/v1/{tenantId}/customers — Listet Kunden aus der necta.one API v1. Optionale Query via `params`. Benötigt tenant_id in der Connection.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'params' => [
                    'type' => 'object',
                    'description' => 'Optionale Query-Parameter (Filter/Paging; siehe spec/necta-one.json).',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen necta-Connection.',
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
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->listCustomers($context->user, $params);

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
            'tags' => ['necta', 'v1', 'customers', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
