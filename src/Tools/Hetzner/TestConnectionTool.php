<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\HetznerIntegrationService;

/**
 * Prüft die Hetzner-Cloud-Verbindung des Users.
 */
class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.hetzner.test-connection';
    }

    public function getDescription(): string
    {
        return 'Testet die Hetzner-Cloud-Verbindung (GET /servers) und gibt zurück, wie viele Server im '
            . 'Projekt liegen. Ein Hetzner-Token gilt immer für genau ein Projekt — der Test bestätigt also '
            . 'zugleich, welches Projekt die Verbindung adressiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung. Ohne Angabe wird die Standard-Verbindung genutzt.',
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
            $service = app(HetznerIntegrationService::class);

            $connectionId = $arguments['connection_id'] ?? null;

            if ($connectionId) {
                $connection = IntegrationConnection::query()
                    ->with('integration')
                    ->where('id', (int) $connectionId)
                    ->where('owner_user_id', $context->user->id)
                    ->first();
            } else {
                $connection = $service->getConnectionForUser($context->user);
            }

            if (!$connection) {
                return ToolResult::error('NO_CONNECTION', 'Keine Hetzner-Cloud-Verbindung gefunden. Bitte zuerst unter /integrations verbinden.');
            }

            $result = $service->testConnection($connection);

            return ToolResult::success([
                'connection_id' => $connection->id,
                'connection_name' => $connection->name,
                'project' => $service->getProjectLabel($connection),
                'success' => $result['success'],
                'message' => $result['message'],
                'server_count' => $result['server_count'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['hetzner', 'cloud', 'connection', 'test'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
