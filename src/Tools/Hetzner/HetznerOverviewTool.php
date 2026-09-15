<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Referenz-Tool für die Hetzner Cloud API v1: erklärt Auth, Paginierung und das
 * asynchrone Aktionsmodell und listet den Endpunkt-Katalog nach Themenbereich.
 *
 * Kein API-Call — reine Dokumentation, damit der generische
 * integrations.hetzner.call gezielt eingesetzt werden kann.
 */
class HetznerOverviewTool implements ToolContract, ToolMetadataContract
{
    /**
     * Endpunkt-Katalog der API v1, gruppiert nach Themenbereich.
     * Pfade sind relativ zur Base-URL https://api.hetzner.cloud/v1.
     *
     * @var array<string, array{beschreibung: string, endpunkte: array<int, string>}>
     */
    private const CATALOG = [
        'server' => [
            'beschreibung' => 'Server anlegen, verwalten und steuern. Jede Aktion unter /actions/ ist asynchron '
                . 'und liefert ein action-Objekt.',
            'endpunkte' => [
                'GET|POST /servers',
                'GET|PUT|DELETE /servers/{id}',
                'GET /servers/{id}/metrics',
                'GET /servers/{id}/actions',
                'POST /servers/{id}/actions/poweron',
                'POST /servers/{id}/actions/poweroff',
                'POST /servers/{id}/actions/reboot',
                'POST /servers/{id}/actions/reset',
                'POST /servers/{id}/actions/shutdown',
                'POST /servers/{id}/actions/reset_password',
                'POST /servers/{id}/actions/rebuild',
                'POST /servers/{id}/actions/change_type',
                'POST /servers/{id}/actions/create_image',
                'POST /servers/{id}/actions/enable_backup',
                'POST /servers/{id}/actions/disable_backup',
                'POST /servers/{id}/actions/enable_rescue',
                'POST /servers/{id}/actions/disable_rescue',
                'POST /servers/{id}/actions/attach_iso',
                'POST /servers/{id}/actions/detach_iso',
                'POST /servers/{id}/actions/attach_to_network',
                'POST /servers/{id}/actions/detach_from_network',
                'POST /servers/{id}/actions/change_alias_ips',
                'POST /servers/{id}/actions/change_dns_ptr',
                'POST /servers/{id}/actions/change_protection',
                'POST /servers/{id}/actions/request_console',
                'POST /servers/{id}/actions/add_to_placement_group',
                'POST /servers/{id}/actions/remove_from_placement_group',
            ],
        ],
        'katalog' => [
            'beschreibung' => 'Unveränderliche Stammdaten: Servertypen, Images, Standorte, Rechenzentren, '
                . 'ISOs und die Preisliste. Ideal, um vor einer Bestellung Verfügbarkeit und Kosten zu prüfen.',
            'endpunkte' => [
                'GET /server_types',
                'GET /server_types/{id}',
                'GET /images',
                'GET|PUT|DELETE /images/{id}',
                'GET /locations',
                'GET /locations/{id}',
                'GET /datacenters',
                'GET /datacenters/{id}',
                'GET /isos',
                'GET /isos/{id}',
                'GET /pricing',
                'GET /load_balancer_types',
            ],
        ],
        'speicher' => [
            'beschreibung' => 'Volumes (Block Storage) und Placement Groups.',
            'endpunkte' => [
                'GET|POST /volumes',
                'GET|PUT|DELETE /volumes/{id}',
                'POST /volumes/{id}/actions/attach',
                'POST /volumes/{id}/actions/detach',
                'POST /volumes/{id}/actions/resize',
                'POST /volumes/{id}/actions/change_protection',
                'GET|POST /placement_groups',
                'GET|PUT|DELETE /placement_groups/{id}',
            ],
        ],
        'netzwerk' => [
            'beschreibung' => 'Private Netzwerke, Firewalls, Load Balancer sowie Primary- und Floating-IPs.',
            'endpunkte' => [
                'GET|POST /networks',
                'GET|PUT|DELETE /networks/{id}',
                'POST /networks/{id}/actions/add_subnet',
                'POST /networks/{id}/actions/delete_subnet',
                'POST /networks/{id}/actions/add_route',
                'POST /networks/{id}/actions/delete_route',
                'POST /networks/{id}/actions/change_ip_range',
                'GET /networks/{id}/members',
                'GET|POST /firewalls',
                'GET|PUT|DELETE /firewalls/{id}',
                'POST /firewalls/{id}/actions/set_rules',
                'POST /firewalls/{id}/actions/apply_to_resources',
                'POST /firewalls/{id}/actions/remove_from_resources',
                'GET|POST /load_balancers',
                'GET|PUT|DELETE /load_balancers/{id}',
                'GET /load_balancers/{id}/metrics',
                'POST /load_balancers/{id}/actions/add_service',
                'POST /load_balancers/{id}/actions/add_target',
                'POST /load_balancers/{id}/actions/remove_target',
                'POST /load_balancers/{id}/actions/change_algorithm',
                'GET|POST /primary_ips',
                'GET|PUT|DELETE /primary_ips/{id}',
                'POST /primary_ips/{id}/actions/assign',
                'POST /primary_ips/{id}/actions/unassign',
                'GET|POST /floating_ips',
                'GET|PUT|DELETE /floating_ips/{id}',
                'POST /floating_ips/{id}/actions/assign',
                'POST /floating_ips/{id}/actions/unassign',
            ],
        ],
        'dns' => [
            'beschreibung' => 'DNS-Zonen und deren Resource-Record-Sets (RRSets). Ein RRSet wird über '
                . 'Name und Typ adressiert, z.B. /zones/example.com/rrsets/www/A.',
            'endpunkte' => [
                'GET|POST /zones',
                'GET|PUT|DELETE /zones/{id_or_name}',
                'GET /zones/{id_or_name}/zonefile',
                'POST /zones/{id_or_name}/actions/import_zonefile',
                'POST /zones/{id_or_name}/actions/change_ttl',
                'POST /zones/{id_or_name}/actions/change_primary_nameservers',
                'GET|POST /zones/{id_or_name}/rrsets',
                'GET|PUT|DELETE /zones/{id_or_name}/rrsets/{name}/{type}',
                'POST /zones/{id_or_name}/rrsets/{name}/{type}/actions/add_records',
                'POST /zones/{id_or_name}/rrsets/{name}/{type}/actions/remove_records',
                'POST /zones/{id_or_name}/rrsets/{name}/{type}/actions/set_records',
                'POST /zones/{id_or_name}/rrsets/{name}/{type}/actions/update_records',
            ],
        ],
        'sicherheit' => [
            'beschreibung' => 'SSH-Keys und TLS-Zertifikate des Projekts.',
            'endpunkte' => [
                'GET|POST /ssh_keys',
                'GET|PUT|DELETE /ssh_keys/{id}',
                'GET|POST /certificates',
                'GET|PUT|DELETE /certificates/{id}',
                'POST /certificates/{id}/actions/retry',
            ],
        ],
        'aktionen' => [
            'beschreibung' => 'Zentrale Statusabfrage aller asynchronen Vorgänge. Jeder verändernde Aufruf '
                . 'liefert eine action-ID, deren Fortschritt hier abgefragt wird.',
            'endpunkte' => [
                'GET /actions',
                'GET /actions/{id}',
                'GET /servers/actions',
                'GET /volumes/actions',
                'GET /networks/actions',
                'GET /firewalls/actions',
                'GET /load_balancers/actions',
                'GET /certificates/actions',
                'GET /images/actions',
                'GET /floating_ips/actions',
                'GET /primary_ips/actions',
                'GET /zones/actions',
            ],
        ],
    ];

    public function getName(): string
    {
        return 'integrations.hetzner.overview';
    }

    public function getDescription(): string
    {
        return 'Referenz zur Hetzner Cloud API v1: Projekt-Token, seitenbasierte Paginierung, das asynchrone '
            . 'Aktionsmodell, Label-Selektoren, Fehlercodes und der vollständige Endpunkt-Katalog nach '
            . 'Themenbereich (Server, Katalog, Speicher, Netzwerk, DNS, Sicherheit, Aktionen). Kein API-Aufruf. '
            . 'Nutze das Ergebnis, um integrations.hetzner.call gezielt einzusetzen. '
            . 'Optional per `bereich` eingrenzen oder per `search` durchsuchen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'bereich' => [
                    'type' => 'string',
                    'enum' => array_keys(self::CATALOG),
                    'description' => 'Optional: nur diesen Themenbereich ausgeben.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Filtert Endpunkte nach Teilstring im Pfad (z.B. "firewall", "rrsets", "poweroff").',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $bereich = trim((string) ($arguments['bereich'] ?? ''));
        $search = mb_strtolower(trim((string) ($arguments['search'] ?? '')));

        $catalog = self::CATALOG;

        if ($bereich !== '') {
            if (!isset($catalog[$bereich])) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    "Unbekannter Bereich \"{$bereich}\". Erlaubt: " . implode(', ', array_keys(self::CATALOG))
                );
            }

            $catalog = [$bereich => $catalog[$bereich]];
        }

        if ($search !== '') {
            $filtered = [];
            foreach ($catalog as $key => $group) {
                $matches = array_values(array_filter(
                    $group['endpunkte'],
                    static fn ($e) => str_contains(mb_strtolower($e), $search)
                ));

                if ($matches) {
                    $filtered[$key] = ['beschreibung' => $group['beschreibung'], 'endpunkte' => $matches];
                }
            }

            $catalog = $filtered;
        }

        $total = 0;
        foreach ($catalog as $group) {
            $total += count($group['endpunkte']);
        }

        return ToolResult::success([
            'api' => 'Hetzner Cloud API v1',
            'base_url' => config('integrations.hetzner.api_base_url', 'https://api.hetzner.cloud/v1'),
            'dokumentation' => 'https://docs.hetzner.cloud/reference/cloud',
            'token_erzeugen' => 'Cloud Console → Projekt → Security → API Tokens → "Generate API token"',
            'authentifizierung' => 'Bearer-Token im Authorization-Header.',
            'projekt_bindung' => 'WICHTIG: Ein Token gilt immer für genau EIN Projekt. Es gibt keinen '
                . 'Projekt-Parameter in der API. Für mehrere Projekte wird je Projekt eine eigene Verbindung '
                . 'angelegt und per connection_id angesprochen. Ein Read-Only-Token beantwortet schreibende '
                . 'Aufrufe mit HTTP 403 (forbidden).',
            'paginierung' => 'SEITENBASIERT: page (ab 1) und per_page (Standard 25, Maximum 50). Die Antwort '
                . 'enthält meta.pagination mit page, per_page, next_page, last_page und total_entries. '
                . 'next_page ist null auf der letzten Seite.',
            'antwort_format' => 'Listen liefern die Ressource unter ihrem Plural-Schlüssel, z.B. { "servers": [...], '
                . '"meta": {...} }. Einzelressourcen unter dem Singular, z.B. { "server": {...} }.',
            'filter' => 'Viele Listen unterstützen name (exakter Name), label_selector (z.B. "env=prod") und '
                . 'sort (z.B. "name:asc"). Server zusätzlich status.',
            'asynchrone_aktionen' => 'Alle verändernden Aufrufe sind ASYNCHRON. Sie antworten mit einem '
                . 'action-Objekt { id, status, progress, command }. status ist zunächst "running" und wechselt '
                . 'zu "success" oder "error". Den Fortschritt liefert GET /actions/{id} bzw. '
                . 'integrations.hetzner.actions.GET. Die Antwort eines Aufrufs bedeutet also NICHT, dass die '
                . 'Aktion bereits abgeschlossen ist.',
            'fehlercodes' => [
                'unauthorized' => 'Token ungültig (HTTP 401).',
                'forbidden' => 'Keine Berechtigung, z.B. Read-Only-Token bei schreibendem Aufruf (HTTP 403).',
                'not_found' => 'Ressource existiert nicht oder gehört zu einem anderen Projekt (HTTP 404).',
                'invalid_input' => 'Ungültige Eingabe, Details unter error.details (HTTP 400).',
                'locked' => 'Ressource gesperrt, weil bereits eine Aktion läuft (HTTP 423).',
                'protected' => 'Löschschutz aktiv.',
                'resource_limit_exceeded' => 'Projekt-Limit erreicht (z.B. maximale Server-Anzahl).',
                'resource_unavailable' => 'Servertyp am gewünschten Standort derzeit nicht verfügbar.',
                'rate_limit_exceeded' => 'Rate-Limit überschritten (Standard 3600 Requests/Stunde je Projekt).',
            ],
            'hinweis_schreibend' => 'POST/PUT/DELETE verändern echte Cloud-Ressourcen und verursachen Kosten. '
                . 'Ein DELETE auf /servers/{id} löscht den Server unwiderruflich samt lokaler Festplatte.',
            'anzahl_endpunkte' => $total,
            'bereiche' => $catalog,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'reference',
            'tags' => ['hetzner', 'cloud', 'overview', 'reference', 'endpoints'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
