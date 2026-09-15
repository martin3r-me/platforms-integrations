<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\ForgeApiException;
use Platform\Integrations\Services\ForgeApiService;
use Platform\Integrations\Tools\Forge\Concerns\GuardsArguments;

/**
 * Führt eine Server-Aktion aus (Neustart / Power-Cycle) — SCHREIBEND.
 */
class ServerActionTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /** Von der Forge-API erlaubte Server-Aktionen. */
    public const ACTIONS = ['reboot', 'power-cycle'];

    public function getName(): string
    {
        return 'integrations.forge.server.action';
    }

    public function getDescription(): string
    {
        return <<<TXT
        SCHREIBEND: Führt eine Aktion auf einem Laravel-Forge-Server aus
        (POST /orgs/{org}/servers/{server}/actions).

        Erlaubte Aktionen:
        - reboot: Server sauber neu starten.
        - power-cycle: Server hart aus- und wieder einschalten (entspricht dem Ziehen des Steckers;
          laufende Schreibvorgänge können verloren gehen).

        Beide Aktionen unterbrechen alle auf dem Server laufenden Sites. Zur Absicherung muss
        "confirm" auf true gesetzt werden. Um nur einen einzelnen Dienst neu zu starten, ist
        integrations.forge.service.action der schonendere Weg.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'server' => [
                    'type' => 'string',
                    'description' => 'Server-ID.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => self::ACTIONS,
                    'description' => 'Auszuführende Aktion: "reboot" (sauberer Neustart) oder "power-cycle" (harter Neustart).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Pflicht: muss true sein. Bestätigt, dass der Server tatsächlich neu gestartet wird.',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Organisations-Slug. Ohne Angabe: Standard-Organisation der Verbindung.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung.',
                ],
            ],
            'required' => ['server', 'action', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['server', 'action'])) {
            return $error;
        }

        $action = trim((string) $arguments['action']);

        if (!in_array($action, self::ACTIONS, true)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                "Unbekannte Aktion \"{$action}\". Erlaubt: " . implode(', ', self::ACTIONS) . '.'
            );
        }

        if (($arguments['confirm'] ?? false) !== true) {
            return ToolResult::error(
                'CONFIRMATION_REQUIRED',
                "Die Aktion \"{$action}\" startet den Server neu und unterbricht alle darauf laufenden Sites. "
                . 'Bitte "confirm": true setzen, um sie auszuführen.'
            );
        }

        try {
            $result = app(ForgeApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->runServerAction(
                    $context->user,
                    (string) $arguments['server'],
                    $action,
                    isset($arguments['organization']) ? (string) $arguments['organization'] : null
                );

            return ToolResult::success([
                'action' => $action,
                'server' => (string) $arguments['server'],
                'result' => $result,
                'hinweis' => 'Die Aktion läuft asynchron. Fortschritt über integrations.forge.events.GET '
                    . '(mit "server") nachverfolgen.',
            ]);
        } catch (ForgeApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'FORGE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['forge', 'laravel', 'server', 'reboot', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
