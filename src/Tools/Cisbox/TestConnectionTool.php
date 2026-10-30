<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\CisboxIntegrationService;

/**
 * Prüft die cisbox-Verbindung des Users.
 */
class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.cisbox.test-connection';
    }

    public function getDescription(): string
    {
        return 'Testet die cisbox-Verbindung (GET /invoices?itemsPerPage=1) und meldet, wie viele '
            . 'Rechnungen die Installation enthält — der Test bestätigt damit zugleich, auf welchen '
            . 'Datenbestand der API-Key zeigt. Schlägt er wegen eines Netzwerkproblems fehl, bleibt die '
            . 'Verbindung aktiv; nur echte Zugangsfehler (401/403) markieren sie als defekt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten cisbox-Verbindung. Ohne Angabe wird die Standard-Verbindung getestet.',
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
            $service = app(CisboxIntegrationService::class);
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
                    'Keine cisbox-Verbindung gefunden. Bitte zuerst unter /integrations verbinden.'
                );
            }

            $result = $service->testConnection($connection);
            $connection->refresh();

            return ToolResult::success([
                'connection_id' => $connection->id,
                'name' => $connection->name,
                'installation' => $service->getInstanceLabel($connection),
                'base_url' => $service->getBaseUrl($connection),
                'eigene_adresse' => $service->hasCustomBaseUrl($connection),
                'status' => $connection->status,
                'last_error' => $connection->last_error,
                'success' => $result['success'],
                'message' => $result['message'],
                'invoice_count' => $result['invoice_count'] ?? null,
                'request_id' => $result['request_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'invoice', 'rechnungen', 'connection', 'test'],
            // Schreibt Status und last_error der Verbindung fort.
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
