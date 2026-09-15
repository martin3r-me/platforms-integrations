<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Referenz-Tool für die Laravel Forge API v2: erklärt Adressierung,
 * Paginierung und listet den Endpunkt-Katalog nach Themenbereich.
 *
 * Kein API-Call — reine Dokumentation, damit der generische
 * integrations.forge.call gezielt eingesetzt werden kann.
 */
class ForgeOverviewTool implements ToolContract, ToolMetadataContract
{
    /**
     * Endpunkt-Katalog der API v2, gruppiert nach Themenbereich.
     * Pfade sind relativ zur Base-URL https://forge.laravel.com/api.
     *
     * @var array<string, array{beschreibung: string, endpunkte: array<int, string>}>
     */
    private const CATALOG = [
        'organisationen' => [
            'beschreibung' => 'Einstiegspunkt: Organisationen, deren Slug in fast allen weiteren Pfaden steckt.',
            'endpunkte' => [
                'GET /orgs',
                'GET /orgs/{org}',
                'GET /orgs/{org}/events',
                'GET /orgs/{org}/server-credentials',
                'GET /orgs/{org}/server-credentials/{credential}',
                'GET|POST /orgs/{org}/server-credentials/{credential}/regions/{region}/vpcs',
            ],
        ],
        'server' => [
            'beschreibung' => 'Server anlegen, verwalten, Aktionen ausführen. "POST /orgs/{org}/servers/{server}/actions" erwartet ein Feld "action".',
            'endpunkte' => [
                'GET|POST /orgs/{org}/servers',
                'GET|PUT|DELETE /orgs/{org}/servers/{server}',
                'POST /orgs/{org}/servers/{server}/actions',
                'GET|POST /orgs/{org}/servers/archives',
                'DELETE /orgs/{org}/servers/archives/{server}',
                'GET /orgs/{org}/servers/{server}/events',
                'GET /orgs/{org}/servers/{server}/events/{event}',
                'GET /orgs/{org}/servers/{server}/events/{event}/output',
                'GET|PUT /orgs/{org}/servers/{server}/network',
                'POST /orgs/{org}/servers/{server}/services/{mysql|nginx|php|postgres|redis|supervisor}/actions',
            ],
        ],
        'php' => [
            'beschreibung' => 'PHP-Versionen und -Konfiguration je Server.',
            'endpunkte' => [
                'GET|POST /orgs/{org}/servers/{server}/php/versions',
                'GET|PUT|DELETE /orgs/{org}/servers/{server}/php/versions/{phpVersion}',
                'GET|PUT /orgs/{org}/servers/{server}/php/versions/{phpVersion}/configs/{cli|fpm|pool}',
                'GET|PUT /orgs/{org}/servers/{server}/php/cli-version',
                'GET|PUT /orgs/{org}/servers/{server}/php/site-version',
                'GET|PUT /orgs/{org}/servers/{server}/php/max-execution-time',
                'GET|PUT /orgs/{org}/servers/{server}/php/max-upload-size',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/php/opcache',
            ],
        ],
        'sites' => [
            'beschreibung' => 'Sites organisationsweit (/orgs/{org}/sites) oder je Server. Domains, Zertifikate, Nginx, Env, Healthcheck, Logs.',
            'endpunkte' => [
                'GET /orgs/{org}/sites',
                'GET /orgs/{org}/sites/{site}',
                'GET|POST /orgs/{org}/servers/{server}/sites',
                'POST /orgs/{org}/servers/{server}/sites/balancer',
                'PUT|DELETE /orgs/{org}/servers/{server}/sites/{site}',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/domains',
                'GET|PATCH|DELETE /orgs/{org}/servers/{server}/sites/{site}/domains/{domainRecord}',
                'POST /orgs/{org}/servers/{server}/sites/{site}/domains/{domainRecord}/actions',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/domains/{domainRecord}/certificates',
                'GET /orgs/{org}/servers/{server}/sites/{site}/certificates',
                'GET|PUT /orgs/{org}/servers/{server}/sites/{site}/environment',
                'GET|PUT /orgs/{org}/servers/{server}/sites/{site}/nginx',
                'GET|PUT /orgs/{org}/servers/{server}/sites/{site}/healthcheck',
                'PUT /orgs/{org}/servers/{server}/sites/{site}/git',
                'GET|DELETE /orgs/{org}/servers/{server}/sites/{site}/logs/{application|nginx-access|nginx-error}',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/heartbeats',
                'GET|PUT /orgs/{org}/servers/{server}/sites/{site}/load-balancing-nodes',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/{composer|npm}/credentials',
            ],
        ],
        'deployments' => [
            'beschreibung' => 'Deployments auslösen und nachverfolgen. Ein Deploy ist POST auf .../deployments.',
            'endpunkte' => [
                'GET /orgs/{org}/servers/{server}/deployments',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/deployments',
                'GET /orgs/{org}/servers/{server}/sites/{site}/deployments/{deployment}',
                'GET /orgs/{org}/servers/{server}/sites/{site}/deployments/{deployment}/log',
                'GET|DELETE /orgs/{org}/servers/{server}/sites/{site}/deployments/status',
                'GET|PUT /orgs/{org}/servers/{server}/sites/{site}/deployments/script',
                'GET|PUT /orgs/{org}/servers/{server}/sites/{site}/deployments/deploy-hook',
                'POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/deployments/push-to-deploy',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/deploy-key',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/webhooks',
            ],
        ],
        'datenbanken' => [
            'beschreibung' => 'Schemas, Datenbank-Benutzer und Backup-Konfigurationen je Server.',
            'endpunkte' => [
                'GET|POST /orgs/{org}/servers/{server}/database/schemas',
                'GET|DELETE /orgs/{org}/servers/{server}/database/schemas/{database}',
                'POST /orgs/{org}/servers/{server}/database/schemas/synchronizations',
                'GET|POST /orgs/{org}/servers/{server}/database/users',
                'GET|PUT|DELETE /orgs/{org}/servers/{server}/database/users/{databaseUser}',
                'PUT /orgs/{org}/servers/{server}/database/password',
                'GET|POST /orgs/{org}/servers/{server}/database/backups',
                'GET|PUT|DELETE /orgs/{org}/servers/{server}/database/backups/{backupConfiguration}',
                'GET|POST /orgs/{org}/servers/{server}/database/backups/{backupConfiguration}/instances',
                'POST /orgs/{org}/servers/{server}/database/backups/{backupConfiguration}/instances/{backup}/restores',
            ],
        ],
        'automatisierung' => [
            'beschreibung' => 'Geplante Jobs, Hintergrundprozesse, Kommandos, Monitore und Rezepte.',
            'endpunkte' => [
                'GET|POST /orgs/{org}/servers/{server}/scheduled-jobs',
                'GET|DELETE /orgs/{org}/servers/{server}/scheduled-jobs/{job}',
                'GET /orgs/{org}/servers/{server}/scheduled-jobs/{job}/output',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/scheduled-jobs',
                'GET|POST /orgs/{org}/servers/{server}/background-processes',
                'GET|PUT|DELETE /orgs/{org}/servers/{server}/background-processes/{backgroundProcess}',
                'POST /orgs/{org}/servers/{server}/background-processes/{backgroundProcess}/actions',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/commands',
                'GET /orgs/{org}/servers/{server}/sites/{site}/commands/{command}/output',
                'GET|POST /orgs/{org}/servers/{server}/monitors',
                'GET|POST /orgs/{org}/recipes',
                'GET|POST /orgs/{org}/recipes/{recipe}/runs',
                'GET /forge-recipes',
                'POST /forge-recipes/{forgeRecipe}/runs',
            ],
        ],
        'sicherheit' => [
            'beschreibung' => 'Firewall-Regeln, Security- und Redirect-Regeln, SSH-Keys, Nginx-Templates.',
            'endpunkte' => [
                'GET|POST /orgs/{org}/servers/{server}/firewall-rules',
                'GET|DELETE /orgs/{org}/servers/{server}/firewall-rules/{rule}',
                'GET|POST /orgs/{org}/servers/{server}/ssh-keys',
                'GET|PUT /orgs/{org}/servers/{server}/key',
                'GET|POST /orgs/{org}/servers/{server}/nginx/templates',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/security-rules',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/redirect-rules',
                'PUT /orgs/{org}/servers/{server}/sites/{site}/redirect-rules/reorder',
            ],
        ],
        'laravel-integrationen' => [
            'beschreibung' => 'Erstklassige Laravel-Integrationen je Site (aktivieren = POST, deaktivieren = DELETE).',
            'endpunkte' => [
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/integrations/horizon',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/integrations/octane',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/integrations/pulse',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/integrations/reverb',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/integrations/laravel-scheduler',
                'GET|POST|DELETE /orgs/{org}/servers/{server}/sites/{site}/integrations/laravel-maintenance',
                'GET|POST /orgs/{org}/servers/{server}/sites/{site}/integrations/inertia',
            ],
        ],
        'teams-und-rollen' => [
            'beschreibung' => 'Teams, Mitglieder, Einladungen, Rollen und Berechtigungen.',
            'endpunkte' => [
                'GET|POST /orgs/{org}/teams',
                'GET|PUT|DELETE /orgs/{org}/teams/{team}',
                'GET|POST /orgs/{org}/teams/{team}/invites',
                'GET|DELETE|PUT /orgs/{org}/teams/{team}/members/{user}',
                'GET|POST /orgs/{org}/teams/{team}/servers',
                'GET|POST /orgs/{org}/teams/{team}/server-credentials',
                'GET|POST /orgs/{org}/roles',
                'GET|PUT|DELETE /orgs/{org}/roles/{role}',
                'GET /permissions',
                'GET /predefined-roles',
            ],
        ],
        'stammdaten' => [
            'beschreibung' => 'Nicht organisationsbezogene Endpunkte: eigener Benutzer, Provider, Storage-Provider.',
            'endpunkte' => [
                'GET /me',
                'GET /user',
                'GET /sites',
                'GET /providers',
                'GET /providers/{provider}/regions',
                'GET /providers/{provider}/regions/{providerRegion}/sizes',
                'GET /providers/{provider}/sizes',
                'GET|POST /orgs/{org}/storage-providers',
            ],
        ],
    ];

    public function getName(): string
    {
        return 'integrations.forge.overview';
    }

    public function getDescription(): string
    {
        return 'Referenz zur Laravel Forge API v2: Adressierung über Organisationen, Cursor-Paginierung, '
            . 'Fehlercodes und der vollständige Endpunkt-Katalog nach Themenbereich (Server, Sites, '
            . 'Deployments, Datenbanken, Automatisierung, Sicherheit, Teams). Kein API-Aufruf. '
            . 'Nutze das Ergebnis, um integrations.forge.call gezielt einzusetzen. '
            . 'Optional per `bereich` auf einen Themenbereich eingrenzen oder per `search` durchsuchen.';
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
                    'description' => 'Optional: Filtert Endpunkte nach Teilstring im Pfad (z.B. "deployments", "php", "backup").',
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
            'api' => 'Laravel Forge API v2',
            'base_url' => config('integrations.forge.api_base_url', 'https://forge.laravel.com/api'),
            'dokumentation' => 'https://laravel.com/forge/docs/api-reference/introduction',
            'token_erzeugen' => 'https://forge.laravel.com/profile/api',
            'authentifizierung' => 'Bearer-Token im Authorization-Header. Zusätzlich sind Accept: application/json '
                . 'und Content-Type: application/json Pflicht — beides setzt der Service automatisch.',
            'adressierung' => 'Fast alle Ressourcen sind organisationsbezogen: /orgs/{org}/... Der {org}-Platzhalter '
                . 'ist der Organisations-Slug. Er kann pro Verbindung als Standard hinterlegt werden und muss dann '
                . 'nicht bei jedem Aufruf mitgegeben werden. integrations.forge.test-connection listet die '
                . 'verfügbaren Organisationen.',
            'paginierung' => 'CURSOR-basiert, nicht seitenbasiert: page[size] (Standard 30) und page[cursor]. '
                . 'Die Antwort enthält meta.next_cursor und meta.prev_cursor — den next_cursor als "cursor" '
                . 'in den Folgeaufruf geben. Ist next_cursor null, war es die letzte Seite.',
            'antwort_format' => 'Listen: { data: [...], meta: {...}, links: {...} }. Einzelressourcen: { data: {...} }.',
            'fehlercodes' => [
                '401' => 'Token ungültig.',
                '403' => 'Token ohne Berechtigung für diese Organisation/Ressource.',
                '404' => 'Nicht gefunden — meist falscher Organisations-Slug oder falsche Server-/Site-ID.',
                '422' => 'Validierungsfehler, Details unter "errors".',
                '429' => 'Rate-Limit überschritten.',
                '503' => 'Forge im Wartungsmodus.',
            ],
            'hinweis_schreibend' => 'POST/PUT/PATCH/DELETE verändern echte Server und Sites. Deployments, '
                . 'Server-Aktionen und Dienst-Neustarts wirken sofort in der Produktion.',
            'anzahl_endpunkte' => $total,
            'bereiche' => $catalog,
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'reference',
            'tags' => ['forge', 'laravel', 'overview', 'reference', 'endpoints'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
