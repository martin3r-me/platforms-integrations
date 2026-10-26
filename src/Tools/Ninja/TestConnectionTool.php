<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\NinjaIntegrationService;

/**
 * Prüft die NinjaOne-Verbindung: Token beziehen und lesend zugreifen.
 */
class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.ninja.test-connection';
    }

    public function getDescription(): string
    {
        return 'Testet die NinjaOne-Verbindung: holt ein OAuth-Token und ruft GET /v2/organizations auf. '
            . 'Gibt Region, Scopes, Status und den letzten Fehler zurück. Schlägt der Test wegen eines '
            . 'Netzwerkproblems fehl, bleibt die Verbindung aktiv — nur echte Zugangsfehler (401/403) '
            . 'markieren sie als defekt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten NinjaOne-Verbindung. Ohne Angabe wird die Standard-Verbindung getestet.',
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
            $service = app(NinjaIntegrationService::class);
            $connectionId = $arguments['connection_id'] ?? null;

            $connection = $connectionId
                ? IntegrationConnection::query()
                    ->with('integration')
                    ->where('id', (int) $connectionId)
                    ->where('owner_user_id', $context->user->id)
                    ->first()
                : $service->getConnectionForUser($context->user);

            if (!$connection) {
                return ToolResult::error(
                    'NO_CONNECTION',
                    'Keine NinjaOne-Verbindung gefunden. Bitte zuerst unter /integrations verbinden.'
                );
            }

            $result = $service->testConnection($connection);
            $connection->refresh();

            return ToolResult::success([
                'connection_id' => $connection->id,
                'name' => $connection->name,
                'region' => mb_strtoupper($service->getRegion($connection)),
                'base_url' => $service->getBaseUrl($connection),
                'auth_scheme' => $service->getAuthScheme($connection),
                'scopes' => $service->getScopes($connection),
                'status' => $connection->status,
                'last_error' => $connection->last_error,
                'success' => $result['success'],
                'message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'connection', 'test'],
            // Schreibt Status und last_error der Verbindung fort.
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
