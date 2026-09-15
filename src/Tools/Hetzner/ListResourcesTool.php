<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\HetznerApiException;
use Platform\Integrations\Services\HetznerApiService;
use Platform\Integrations\Support\FieldProjection;
use Platform\Integrations\Tools\Hetzner\Concerns\GuardsArguments;

/**
 * Sammel-Tool für alle einfachen Listen-Endpunkte der Hetzner Cloud API.
 *
 * Statt je Ressource ein eigenes Tool zu registrieren, wird die Ressource über
 * den Parameter "resource" gewählt. Server haben mit
 * integrations.hetzner.servers.GET ein eigenes Tool, weil sie die mit Abstand
 * häufigste Abfrage sind.
 */
class ListResourcesTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Auswählbare Ressourcen: Slug => [Pfad, Beschreibung].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const RESOURCES = [
        'server_types' => ['/server_types', 'Buchbare Servertypen mit CPU, RAM, Disk und Preis.'],
        'images' => ['/images', 'Betriebssystem-Images, Snapshots und Backups.'],
        'locations' => ['/locations', 'Standorte (z.B. fsn1, nbg1, hel1, ash, hil).'],
        'datacenters' => ['/datacenters', 'Rechenzentren je Standort inkl. verfügbarer Servertypen.'],
        'isos' => ['/isos', 'Einbindbare ISO-Images.'],
        'volumes' => ['/volumes', 'Block-Storage-Volumes.'],
        'placement_groups' => ['/placement_groups', 'Platzierungsgruppen zur Verteilung von Servern.'],
        'networks' => ['/networks', 'Private Netzwerke inkl. Subnetze und Routen.'],
        'firewalls' => ['/firewalls', 'Firewalls mit Regeln und zugeordneten Ressourcen.'],
        'load_balancers' => ['/load_balancers', 'Load Balancer mit Services und Targets.'],
        'load_balancer_types' => ['/load_balancer_types', 'Buchbare Load-Balancer-Typen.'],
        'primary_ips' => ['/primary_ips', 'Primäre IP-Adressen.'],
        'floating_ips' => ['/floating_ips', 'Floating IPs, die zwischen Servern umgehängt werden können.'],
        'ssh_keys' => ['/ssh_keys', 'Im Projekt hinterlegte SSH-Public-Keys.'],
        'certificates' => ['/certificates', 'TLS-Zertifikate (verwaltet oder hochgeladen).'],
        'zones' => ['/zones', 'DNS-Zonen des Projekts.'],
    ];

    public function getName(): string
    {
        return 'integrations.hetzner.list.GET';
    }

    public function getDescription(): string
    {
        $lines = [];
        foreach (self::RESOURCES as $slug => [$path, $desc]) {
            $lines[] = "- {$slug} (GET {$path}): {$desc}";
        }

        return "Listet eine Hetzner-Cloud-Ressource auf. Die Ressource wird über \"resource\" gewählt:\n"
            . implode("\n", $lines)
            . "\n\nFilter: name (exakt), label_selector (z.B. \"env=prod\"), sort. "
            . 'Paginierung über page und per_page (max. 50). '
            . 'Server werden über das eigene Tool integrations.hetzner.servers.GET abgefragt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => [
                    'type' => 'string',
                    'enum' => array_keys(self::RESOURCES),
                    'description' => 'Aufzulistende Ressource.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Filtert auf den exakten Namen der Ressource.',
                ],
                'label_selector' => [
                    'type' => 'string',
                    'description' => 'Label-Selektor, z.B. "env=prod".',
                ],
                'sort' => [
                    'type' => 'string',
                    'description' => 'Sortierung, z.B. "name:asc".',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Seitennummer (ab 1).',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Einträge pro Seite (Standard 25, Maximum 50).',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: reduziert die Antwort auf diese Felder (Dot-Notation).',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
                ],
            ],
            'required' => ['resource'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['resource'])) {
            return $error;
        }

        $resource = mb_strtolower(trim((string) $arguments['resource']));

        if (!isset(self::RESOURCES[$resource])) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                "Unbekannte Ressource \"{$resource}\". Erlaubt: " . implode(', ', array_keys(self::RESOURCES)) . '.'
            );
        }

        [$path] = self::RESOURCES[$resource];

        try {
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->get($context->user, $path, $this->listQuery($arguments));

            $fields = $arguments['fields'] ?? [];

            if (is_array($fields) && $fields) {
                $result = FieldProjection::apply($result, $fields);
            }

            return ToolResult::success($result);
        } catch (HetznerApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'HETZNER_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['hetzner', 'cloud', 'list', 'resources'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
