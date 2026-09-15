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
 * Listet die Server des Hetzner-Projekts auf.
 */
class ListServersTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.hetzner.servers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /servers — Listet die Server des Hetzner-Projekts auf (Name, Status, Typ, IPs, Standort). '
            . 'Filter: name (exakt), status, label_selector (z.B. "env=prod"), sort. '
            . 'Paginierung über page und per_page (max. 50). Das Projekt ergibt sich aus dem Token der Verbindung.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Filtert auf den exakten Servernamen.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filtert nach Status: running, initializing, starting, stopping, off, deleting, migrating, rebuilding, unknown.',
                ],
                'label_selector' => [
                    'type' => 'string',
                    'description' => 'Label-Selektor, z.B. "env=prod" oder "role in (web,api)".',
                ],
                'sort' => [
                    'type' => 'string',
                    'description' => 'Sortierung, z.B. "name:asc" oder "created:desc".',
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
                    'description' => 'Optional: reduziert die Antwort, z.B. ["id","name","status","public_net.ipv4.ip"].',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
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
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->listServers($context->user, $this->listQuery($arguments));

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
            'tags' => ['hetzner', 'cloud', 'servers', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
