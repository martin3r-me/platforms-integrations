<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\ForgeApiException;
use Platform\Integrations\Services\ForgeApiService;
use Platform\Integrations\Tools\Forge\Concerns\GuardsArguments;

/**
 * Löst ein Deployment für eine Site aus — SCHREIBEND.
 *
 * Wirkt sofort auf der Produktionsumgebung, daher mit ausdrücklicher
 * Bestätigung abgesichert.
 */
class DeploySiteTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.forge.site.deploy';
    }

    public function getDescription(): string
    {
        return <<<TXT
        SCHREIBEND: Löst ein Deployment für eine Laravel-Forge-Site aus
        (POST /orgs/{org}/servers/{server}/sites/{site}/deployments).

        Das Deployment läuft sofort auf dem echten Server und macht die Site während des
        Vorgangs kurzzeitig instabil. Zur Absicherung muss "confirm" auf true gesetzt werden.

        Das Deployment läuft asynchron. Der Fortschritt wird über
        integrations.forge.deployment-log.GET (ohne "deployment" = aktueller Status) verfolgt.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'server' => [
                    'type' => 'string',
                    'description' => 'Server-ID.',
                ],
                'site' => [
                    'type' => 'string',
                    'description' => 'Site-ID, die deployt werden soll.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Pflicht: muss true sein. Bestätigt, dass ein echtes Deployment in der Produktion ausgelöst wird.',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Organisations-Slug. Ohne Angabe: Standard-Organisation der Verbindung.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung.',
                ],
            ],
            'required' => ['server', 'site', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['server', 'site'])) {
            return $error;
        }

        if (($arguments['confirm'] ?? false) !== true) {
            return ToolResult::error(
                'CONFIRMATION_REQUIRED',
                'Ein Deployment verändert die Produktionsumgebung. Bitte "confirm": true setzen, um es auszulösen.'
            );
        }

        try {
            $result = app(ForgeApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->deploySite(
                    $context->user,
                    (string) $arguments['server'],
                    (string) $arguments['site'],
                    isset($arguments['organization']) ? (string) $arguments['organization'] : null
                );

            return ToolResult::success([
                'deployment' => $result,
                'hinweis' => 'Deployment wurde ausgelöst und läuft asynchron. Status abfragen mit '
                    . 'integrations.forge.deployment-log.GET (server + site, ohne "deployment").',
            ]);
        } catch (ForgeApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'FORGE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['forge', 'laravel', 'deployment', 'deploy', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
