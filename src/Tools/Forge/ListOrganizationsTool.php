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
 * Listet die Organisationen auf, auf die das Forge-Token Zugriff hat.
 */
class ListOrganizationsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.forge.organizations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /orgs — Listet alle Laravel-Forge-Organisationen auf, auf die das API-Token Zugriff hat. '
            . 'Der zurückgegebene "slug" ist der Wert, den alle anderen Forge-Tools als "organization" erwarten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
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
                    'description' => 'Optional: reduziert die Antwort auf diese Felder (Dot-Notation). Forge antwortet im JSON:API-Format, die Nutzdaten liegen unter "attributes" — entsprechend "attributes.name" statt "name" angeben.',
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
                ->listOrganizations($context->user, $this->paginationQuery($arguments));

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
            'tags' => ['forge', 'laravel', 'organizations', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
