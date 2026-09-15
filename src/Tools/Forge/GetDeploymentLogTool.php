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
 * Ruft das Log eines Deployments ab — der Weg zur Fehlerursache
 * eines fehlgeschlagenen Deployments.
 */
class GetDeploymentLogTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.forge.deployment-log.GET';
    }

    public function getDescription(): string
    {
        return 'GET /orgs/{org}/servers/{server}/sites/{site}/deployments/{deployment}/log — Ruft die '
            . 'Ausgabe eines Deployments ab. Ohne "deployment" wird stattdessen der aktuelle '
            . 'Deployment-Status der Site zurückgegeben (.../deployments/status). Erster Anlaufpunkt, '
            . 'um ein fehlgeschlagenes Deployment zu diagnostizieren.';
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
                    'description' => 'Site-ID.',
                ],
                'deployment' => [
                    'type' => 'string',
                    'description' => 'Deployment-ID aus integrations.forge.deployments.GET. Ohne Angabe wird der aktuelle Deployment-Status geliefert.',
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
            'required' => ['server', 'site'],
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

        $organization = isset($arguments['organization']) ? (string) $arguments['organization'] : null;
        $deployment = $arguments['deployment'] ?? null;

        try {
            $service = app(ForgeApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = ($deployment !== null && $deployment !== '')
                ? $service->getDeploymentLog(
                    $context->user,
                    (string) $arguments['server'],
                    (string) $arguments['site'],
                    (string) $deployment,
                    $organization
                )
                : $service->getDeploymentStatus(
                    $context->user,
                    (string) $arguments['server'],
                    (string) $arguments['site'],
                    $organization
                );

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
            'tags' => ['forge', 'laravel', 'deployments', 'log', 'debug'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
