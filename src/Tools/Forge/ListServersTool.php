<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\ForgeApiException;
use Platform\Integrations\Services\ForgeApiService;
use Platform\Integrations\Support\FieldProjection;
use Platform\Integrations\Tools\Forge\Concerns\GuardsArguments;

/**
 * Listet die Server einer Forge-Organisation auf.
 */
class ListServersTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.forge.servers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /orgs/{org}/servers — Listet alle Server einer Laravel-Forge-Organisation auf. '
            . 'Ohne "organization" wird die Standard-Organisation der Verbindung genutzt. '
            . 'Die Server-ID aus dem Ergebnis wird von allen server-bezogenen Tools benötigt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'organization' => [
                    'type' => 'string',
                    'description' => 'Organisations-Slug. Ohne Angabe: Standard-Organisation der Verbindung.',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Einträge pro Seite (Forge-Default: 30).',
                ],
                'cursor' => [
                    'type' => 'string',
                    'description' => 'Cursor aus meta.next_cursor der Vorantwort für die nächste Seite.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: reduziert die Antwort auf diese Felder, z.B. ["id","attributes.name","attributes.ip_address","attributes.type"]. Forge antwortet im JSON:API-Format, die Nutzdaten liegen unter "attributes" — entsprechend "attributes.name" statt "name" angeben.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung.',
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
            $result = app(ForgeApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->listServers(
                    $context->user,
                    isset($arguments['organization']) ? (string) $arguments['organization'] : null,
                    $this->paginationQuery($arguments)
                );

            $fields = $arguments['fields'] ?? [];

            if (is_array($fields) && $fields) {
                $result = FieldProjection::apply($result, $fields);
            }

            return ToolResult::success($result);
        } catch (ForgeApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'FORGE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['forge', 'laravel', 'servers', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
