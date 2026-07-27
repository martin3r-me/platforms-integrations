<?php

namespace Platform\Integrations\Tools\Plausible;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\PlausibleIntegrationService;
use Platform\Integrations\Services\IntegrationConnectionResolver;

class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.plausible.test';
    }

    public function getDescription(): string
    {
        return 'Testet die Plausible Analytics API-Verbindung. Maßgeblich ist die Stats-API (die das SEO-Modul nutzt). Mit site_id wird ein echter Stats-Probe gemacht; ohne site_id wird die Sites-API versucht (auf self-hosted-Instanzen oft nicht verfügbar).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Plausible-Verbindung.',
                ],
                'site_id' => [
                    'type' => 'string',
                    'description' => 'Optional: Domain/Site-ID (z.B. "offline-ag.com") für einen echten Stats-API-Check statt der Sites-API.',
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
            $service = app(PlausibleIntegrationService::class);
            $connectionId = $arguments['connection_id'] ?? null;

            if ($connectionId) {
                $resolver = app(IntegrationConnectionResolver::class);
                $connection = $resolver->resolveById($connectionId, $context->user);
            } else {
                $connection = $service->getConnectionForUser($context->user);
            }

            if (!$connection) {
                return ToolResult::error('NO_CONNECTION', 'Keine Plausible-Verbindung konfiguriert. Bitte zuerst unter Integrationen den Plausible API-Key eingeben.');
            }

            $result = $service->testConnection($connection, $arguments['site_id'] ?? null);

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
            'tags' => ['plausible', 'analytics', 'test', 'connectivity'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
