<?php

namespace Platform\Integrations\Tools\Plausible;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\PlausibleApiService;
use Platform\Integrations\Exceptions\PlausibleApiException;

class ListSitesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.plausible.sites.GET';
    }

    public function getDescription(): string
    {
        return 'GET /sites - Listet alle in Plausible registrierten Sites auf. Gibt Domain und weitere Metadaten zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Plausible-Verbindung.',
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
            $service = app(PlausibleApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->getSites($context->user);

            return ToolResult::success($result);
        } catch (PlausibleApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'PLAUSIBLE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['plausible', 'analytics', 'sites', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
