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
 * Listet die DNS-Einträge (RRSets) einer Hetzner-Zone auf.
 */
class ListZoneRrsetsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.hetzner.zone.rrsets.GET';
    }

    public function getDescription(): string
    {
        return 'GET /zones/{zone}/rrsets — Listet die DNS-Einträge einer Hetzner-Zone auf. '
            . 'Ein RRSet bündelt alle Records gleichen Namens und Typs, z.B. "www" + "A". '
            . 'Die Zone wird über ihren Namen ("example.com") oder ihre ID adressiert. '
            . 'Verfügbare Zonen liefert integrations.hetzner.list.GET mit resource="zones".';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'zone' => [
                    'type' => 'string',
                    'description' => 'Zonenname (z.B. "example.com") oder Zonen-ID.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Filtert auf den exakten RRSet-Namen, z.B. "www".',
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
            'required' => ['zone'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['zone'])) {
            return $error;
        }

        try {
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->listZoneRrsets($context->user, (string) $arguments['zone'], $this->listQuery($arguments));

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
            'tags' => ['hetzner', 'cloud', 'dns', 'zones', 'rrsets'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
