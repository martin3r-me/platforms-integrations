<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Services\NinjaApiService;
use Platform\Integrations\Tools\Ninja\Concerns\GuardsArguments;

/**
 * Organisationen (Mandanten) in NinjaOne auflisten oder einzeln abrufen.
 */
class ListOrganizationsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.ninja.organizations.GET';
    }

    public function getDescription(): string
    {
        return 'Listet die Organisationen (Mandanten) aus NinjaOne. Mit "id" werden stattdessen die '
            . 'Details einer Organisation geladen, mit detailed=true die ausführliche Liste inkl. '
            . 'Standorten und Richtlinien-Zuordnung. Die Organisations-ID ist der Schlüssel für '
            . 'Gerätelisten und Gerätefilter ("org = <id>").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Nur diese Organisation abrufen (GET /v2/organization/{id}).',
                ],
                'detailed' => [
                    'type' => 'boolean',
                    'description' => 'true ruft /v2/organizations-detailed auf — mit Standorten und Richtlinien.',
                ],
                'pageSize' => ['type' => 'integer', 'description' => 'Einträge pro Seite (max. 1000).', 'minimum' => 1],
                'after' => ['type' => 'integer', 'description' => 'Cursor: letzte Organisations-ID der vorherigen Seite.'],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese Felder zurückgeben, z.B. ["id","name"].',
                ],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: bestimmte NinjaOne-Verbindung.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $id = $arguments['id'] ?? null;

        [$path, $pathParams, $query] = match (true) {
            (bool) $id => ['/v2/organization/{id}', ['id' => (int) $id], []],
            (bool) ($arguments['detailed'] ?? false) => ['/v2/organizations-detailed', [], $this->listQuery($arguments)],
            default => ['/v2/organizations', [], $this->listQuery($arguments)],
        };

        try {
            $result = app(NinjaApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, 'GET', $path, $pathParams, $query);

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = NinjaApiService::projectFields($result, $arguments['fields']);
            }

            return ToolResult::success($result);
        } catch (NinjaApiException $e) {
            return ToolResult::error($e->getNinjaErrorCode() ?? 'NINJA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'organizations'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
