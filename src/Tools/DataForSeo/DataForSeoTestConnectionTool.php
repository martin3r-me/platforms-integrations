<?php

namespace Platform\Integrations\Tools\DataForSeo;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DataForSeoIntegrationService;
use Platform\Integrations\Services\IntegrationConnectionResolver;

/**
 * LLM-Tool: Connectivity-Check für DataForSEO
 *
 * Testet die DataForSEO API-Verbindung ohne aktives Budget zu verbrauchen.
 * Prüft nur, ob die Credentials (Login/Password) gültig sind.
 */
class DataForSeoTestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.dataforseo.test';
    }

    public function getDescription(): string
    {
        return 'Testet die DataForSEO API-Verbindung (Connectivity-Check). Prüft ob die Credentials (Login/Password) gültig sind, ohne Budget zu verbrauchen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen DataForSEO-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
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
            $service = app(DataForSeoIntegrationService::class);
            $connectionId = $arguments['connection_id'] ?? null;

            if ($connectionId) {
                $resolver = app(IntegrationConnectionResolver::class);
                $connection = $resolver->resolveById($connectionId, $context->user);
            } else {
                $connection = $service->getConnectionForUser($context->user);
            }

            if (!$connection) {
                return ToolResult::error('NO_CONNECTION', 'Keine DataForSEO-Verbindung konfiguriert. Bitte zuerst unter Integrationen die DataForSEO-Credentials (Login/Password) eingeben.');
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
            'tags' => ['dataforseo', 'seo', 'test', 'connectivity'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
