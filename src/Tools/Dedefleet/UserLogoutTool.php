<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;

/**
 * DedeFleet GET /User/Logout — Invalidates the current API token and logs out the current user.
 * Auto-generiert aus der v2-Swagger-Spec; delegiert an DedefleetApiService::call().
 */
class UserLogoutTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dedefleet.user.logout.GET';
    }

    public function getDescription(): string
    {
        return 'Invalidates the current API token and logs out the current user.

Optionale Query-Parameter via `params`.

Vollständige Feld-/Response-Details: https://ortung.dedefleet.de/swagger (Spec /swagger/data/api/2).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'params' => [
                    'type' => 'object',
                    'description' => 'Query-Parameter (siehe Swagger).',
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

        $payload = is_array($arguments['params'] ?? null) ? $arguments['params'] : [];

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->call($context->user, 'GET', '/User/Logout', $payload);

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
            'tags' => ['dedefleet', 'user'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
