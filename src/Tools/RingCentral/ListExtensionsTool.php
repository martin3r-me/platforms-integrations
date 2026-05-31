<?php

namespace Platform\Integrations\Tools\RingCentral;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\RingCentralApiService;
use Platform\Integrations\Exceptions\RingCentralApiException;

class ListExtensionsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.ringcentral.extensions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /extension - Listet alle Extensions/Nebenstellen des RingCentral-Accounts auf. Gibt Extension-Nummer, Name, Typ und Status zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der RingCentral-Verbindung.',
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
            $service = app(RingCentralApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->getExtensions($context->user);

            return ToolResult::success($result);
        } catch (RingCentralApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'RC_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ringcentral', 'telefonie', 'extensions', 'nebenstellen'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
