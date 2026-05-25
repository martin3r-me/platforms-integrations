<?php

namespace Platform\Integrations\Tools\GoogleSearchConsole;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\GoogleSearchConsoleIntegrationService;
use Platform\Integrations\Services\IntegrationConnectionResolver;

class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.google_search_console.test';
    }

    public function getDescription(): string
    {
        return 'Testet die Google Search Console API-Verbindung (Connectivity-Check). Prüft ob der OAuth2-Token gültig ist und Sites abgerufen werden können.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Google Search Console-Verbindung.',
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
            $service = app(GoogleSearchConsoleIntegrationService::class);
            $connectionId = $arguments['connection_id'] ?? null;

            if ($connectionId) {
                $resolver = app(IntegrationConnectionResolver::class);
                $connection = $resolver->resolveById($connectionId, $context->user);
            } else {
                $connection = $service->getConnectionForUser($context->user);
            }

            if (!$connection) {
                return ToolResult::error('NO_CONNECTION', 'Keine Google Search Console-Verbindung konfiguriert. Bitte zuerst unter Integrationen mit Google Search Console verbinden.');
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
            'tags' => ['google-search-console', 'seo', 'test', 'connectivity'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
