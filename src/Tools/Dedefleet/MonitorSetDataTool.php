<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet POST /Monitor/SetData — Stores monitor data for the supplied request payload.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class MonitorSetDataTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.monitor.set-data.POST';
    }

    public function getDescription(): string
    {
        return 'Stores monitor data for the supplied request payload.

REQUEST-Felder (`data`):
- key: string — A key, which is used for finding the content of the intralogistik monitor program.
- content: string — Html content to show on the intralogistik monitor program.

Vollständige Feld-/Response-Details: https://ortung.dedefleet.de/swagger (Spec /swagger/data/api/2).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Request-Body (Felder siehe Tool-Description / Swagger).',
                    'additionalProperties' => true,
                ],
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

        $payload = is_array($arguments['data'] ?? null) ? $arguments['data'] : [];

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->call($context->user, 'POST', '/Monitor/SetData', $payload);

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
            'tags' => ['dedefleet', 'monitor'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
