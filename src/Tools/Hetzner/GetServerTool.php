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
 * Ruft einen einzelnen Hetzner-Server ab.
 */
class GetServerTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.hetzner.server.GET';
    }

    public function getDescription(): string
    {
        return 'GET /servers/{id} — Ruft einen einzelnen Hetzner-Server mit allen Details ab: Status, Servertyp, '
            . 'Image, Standort, öffentliche und private Netzwerke, Volumes, Backup-Fenster und Löschschutz.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Server-ID (aus integrations.hetzner.servers.GET).',
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
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['id'])) {
            return $error;
        }

        try {
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->getServer($context->user, (string) $arguments['id']);

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
            'tags' => ['hetzner', 'cloud', 'server', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
