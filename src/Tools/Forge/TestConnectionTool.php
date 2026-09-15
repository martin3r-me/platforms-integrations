<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\ForgeIntegrationService;

/**
 * Prüft die Laravel-Forge-Verbindung und listet die erreichbaren Organisationen.
 */
class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.forge.test-connection';
    }

    public function getDescription(): string
    {
        return 'Testet die Laravel-Forge-Verbindung (GET /orgs) und gibt zurück, auf welche Organisationen '
            . 'das API-Token Zugriff hat. Nutze das Ergebnis, um den richtigen "organization"-Slug für alle '
            . 'weiteren Forge-Tools zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung. Ohne Angabe wird die Standard-Verbindung genutzt.',
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
            $service = app(ForgeIntegrationService::class);

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
                return ToolResult::error('NO_CONNECTION', 'Keine Laravel-Forge-Verbindung gefunden. Bitte zuerst unter /integrations verbinden.');
            }

            $result = $service->testConnection($connection);

            return ToolResult::success([
                'connection_id' => $connection->id,
                'connection_name' => $connection->name,
                'success' => $result['success'],
                'message' => $result['message'],
                'organizations' => $result['organizations'] ?? [],
                'default_organization' => $service->getDefaultOrganization($connection),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['forge', 'laravel', 'connection', 'test'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
