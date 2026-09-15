<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\HetznerApiException;
use Platform\Integrations\Services\HetznerApiService;
use Platform\Integrations\Tools\Hetzner\Concerns\GuardsArguments;

/**
 * Führt eine Aktion auf einem Hetzner-Server aus — SCHREIBEND.
 *
 * Die Aktionen laufen asynchron; auf Wunsch wird bis zum Abschluss gewartet.
 */
class ServerActionTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Kurzbeschreibung der wichtigsten Aktionen für die Tool-Beschreibung.
     *
     * @var array<string, string>
     */
    private const ACTION_HINTS = [
        'poweron' => 'Server einschalten.',
        'shutdown' => 'Sauberes Herunterfahren per ACPI-Signal (empfohlen).',
        'poweroff' => 'Hartes Ausschalten — wie Stecker ziehen, Datenverlust möglich.',
        'reboot' => 'Sauberer Neustart per ACPI-Signal.',
        'reset' => 'Harter Reset — wie Reset-Knopf, Datenverlust möglich.',
        'rebuild' => 'Server aus einem Image neu aufsetzen — LÖSCHT ALLE DATEN auf der Festplatte.',
        'reset_password' => 'Root-Passwort zurücksetzen (Server muss laufen).',
        'enable_rescue' => 'Rescue-System für den nächsten Start aktivieren.',
        'disable_rescue' => 'Rescue-System deaktivieren.',
        'change_type' => 'Servertyp ändern (Upgrade/Downgrade); benötigt server_type im body.',
        'create_image' => 'Snapshot des Servers erstellen; verursacht laufende Speicherkosten.',
        'enable_backup' => 'Automatische Backups aktivieren; kostenpflichtig (20 Prozent Aufschlag).',
        'disable_backup' => 'Automatische Backups deaktivieren; vorhandene Backups werden gelöscht.',
        'attach_iso' => 'ISO einbinden; benötigt iso im body.',
        'detach_iso' => 'Eingebundenes ISO entfernen.',
        'change_protection' => 'Lösch- und Rebuild-Schutz setzen; benötigt delete/rebuild im body.',
        'change_dns_ptr' => 'Reverse-DNS-Eintrag setzen; benötigt ip und dns_ptr im body.',
        'attach_to_network' => 'Server einem privaten Netzwerk zuordnen; benötigt network im body.',
        'detach_from_network' => 'Server aus einem privaten Netzwerk entfernen.',
        'change_alias_ips' => 'Alias-IPs im privaten Netzwerk setzen.',
        'request_console' => 'VNC-Konsolen-URL anfordern.',
        'add_to_placement_group' => 'Server einer Platzierungsgruppe zuordnen (Server muss aus sein).',
        'remove_from_placement_group' => 'Server aus der Platzierungsgruppe entfernen.',
    ];

    public function getName(): string
    {
        return 'integrations.hetzner.server.action';
    }

    public function getDescription(): string
    {
        $lines = [];
        foreach (self::ACTION_HINTS as $action => $hint) {
            $lines[] = "- {$action}: {$hint}";
        }

        return "SCHREIBEND: Führt eine Aktion auf einem Hetzner-Server aus "
            . "(POST /servers/{id}/actions/{action}).\n\n"
            . implode("\n", $lines)
            . "\n\nDie Aktion läuft ASYNCHRON und liefert ein action-Objekt mit status running|success|error. "
            . "Mit \"wait\": true wird bis zum Abschluss gewartet, sonst den Fortschritt über "
            . "integrations.hetzner.actions.GET verfolgen.\n\n"
            . "Die Aktionen poweroff, reset und rebuild unterbrechen den Server hart bzw. löschen Daten und "
            . "verlangen deshalb \"confirm\": true. rebuild setzt den Server vollständig neu auf.";
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Server-ID.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => HetznerApiService::SERVER_ACTIONS,
                    'description' => 'Auszuführende Aktion. Bedeutung siehe Tool-Beschreibung.',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'Zusätzliche Parameter der Aktion, z.B. {"server_type":"cx32"} bei change_type '
                        . 'oder {"image":"ubuntu-24.04"} bei rebuild.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Erforderlich für poweroff, reset und rebuild. Bestätigt den harten Eingriff bzw. den Datenverlust.',
                ],
                'wait' => [
                    'type' => 'boolean',
                    'description' => 'Wartet, bis die Aktion abgeschlossen ist (Standard: false).',
                ],
                'max_wait' => [
                    'type' => 'integer',
                    'description' => 'Maximale Wartezeit in Sekunden bei "wait" (Standard aus der Konfiguration, üblicherweise 60).',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
                ],
            ],
            'required' => ['id', 'action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['id', 'action'])) {
            return $error;
        }

        $action = mb_strtolower(trim((string) $arguments['action']));

        if (!in_array($action, HetznerApiService::SERVER_ACTIONS, true)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                "Unbekannte Aktion \"{$action}\". Erlaubt: " . implode(', ', HetznerApiService::SERVER_ACTIONS) . '.'
            );
        }

        if (in_array($action, HetznerApiService::DESTRUCTIVE_SERVER_ACTIONS, true)
            && ($arguments['confirm'] ?? false) !== true) {
            $hint = self::ACTION_HINTS[$action] ?? '';

            return ToolResult::error(
                'CONFIRMATION_REQUIRED',
                "Die Aktion \"{$action}\" ist zerstörend: {$hint} "
                . 'Bitte "confirm": true setzen, um sie auszuführen.'
            );
        }

        $body = $arguments['body'] ?? [];

        if (!is_array($body)) {
            return ToolResult::error('VALIDATION_ERROR', 'body muss ein Objekt sein.');
        }

        try {
            $service = app(HetznerApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->runServerAction($context->user, (string) $arguments['id'], $action, $body);
            $actionId = $result['action']['id'] ?? null;
            $status = $result['action']['status'] ?? null;

            if (($arguments['wait'] ?? false) === true && $actionId) {
                $maxWait = (int) ($arguments['max_wait'] ?? config('integrations.hetzner.action.max_wait', 60));
                $interval = (int) config('integrations.hetzner.action.poll_interval', 2);

                $result = $service->waitForAction($context->user, (string) $actionId, $maxWait, $interval);
                $status = $result['action']['status'] ?? $status;
            }

            return ToolResult::success([
                'server_id' => (int) $arguments['id'],
                'action' => $action,
                'action_id' => $actionId,
                'status' => $status,
                'result' => $result,
                'hinweis' => $status === 'running'
                    ? 'Die Aktion läuft noch. Status abfragen mit integrations.hetzner.actions.GET '
                        . '(id=' . $actionId . ', optional wait=true).'
                    : null,
            ]);
        } catch (HetznerApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'HETZNER_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['hetzner', 'cloud', 'server', 'power', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
