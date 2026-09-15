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
 * Ruft eine einzelne Forge-Site ab.
 */
class GetSiteTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.forge.site.GET';
    }

    public function getDescription(): string
    {
        return 'GET /orgs/{org}/sites/{site} — Ruft eine einzelne Laravel-Forge-Site ab '
            . '(Name, Verzeichnis, Repository, Branch, PHP-Version, Status).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'site' => [
                    'type' => 'string',
                    'description' => 'Site-ID (aus integrations.forge.sites.GET).',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Organisations-Slug. Ohne Angabe: Standard-Organisation der Verbindung.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: reduziert die Antwort auf diese Felder (Dot-Notation).',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung.',
                ],
            ],
            'required' => ['site'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['site'])) {
            return $error;
        }

        try {
            $result = app(ForgeApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->getSite(
                    $context->user,
                    (string) $arguments['site'],
                    isset($arguments['organization']) ? (string) $arguments['organization'] : null
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
            'tags' => ['forge', 'laravel', 'site', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
