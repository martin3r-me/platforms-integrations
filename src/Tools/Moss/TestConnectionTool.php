<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\MossIntegrationService;
use Platform\Integrations\Services\IntegrationConnectionResolver;

class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.test';
    }

    public function getDescription(): string
    {
        return 'Testet die Moss API-Verbindung (Connectivity-Check). Prüft ob die Credentials (Client ID/Client Secret) gültig sind.';
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
            $service = app(MossIntegrationService::class);
            $connectionId = $arguments['connection_id'] ?? null;

            if ($connectionId) {
                $resolver = app(IntegrationConnectionResolver::class);
                $connection = $resolver->resolveById($connectionId, $context->user);
            } else {
                $connection = $service->getConnectionForUser($context->user);
            }

            if (!$connection) {
                return ToolResult::error('NO_CONNECTION', 'Keine Moss-Verbindung konfiguriert. Bitte zuerst unter Integrationen die Moss-Credentials (Client ID/Client Secret) eingeben.');
            }

            $result = $service->testConnection($connection);

            if ($result['success']) {
                return ToolResult::success([
                    'status' => 'connected',
                    'message' => $result['message'],
                    'connection_id' => $connection->id,
                    'last_tested_at' => $connection->last_tested_at?->toIso8601String(),
                ]);
            }

            return ToolResult::error('CONNECTION_FAILED', $result['message']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['moss', 'spend-management', 'test', 'connectivity'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
