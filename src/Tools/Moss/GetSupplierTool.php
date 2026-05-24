<?php

namespace Platform\Integrations\Tools\Moss;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\MossApiService;
use Platform\Integrations\Exceptions\MossApiException;

class GetSupplierTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.moss.suppliers.detail.GET';
    }

    public function getDescription(): string
    {
        return 'GET /v1/suppliers/{id} - Ruft Details eines einzelnen Moss Suppliers ab.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Moss-Verbindung.',
                ],
                'id' => [
                    'type' => 'string',
                    'description' => 'ID des Suppliers.',
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

        try {
            $service = app(MossApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $result = $service->getSupplier($context->user, $arguments['id']);

            return ToolResult::success($result);
        } catch (MossApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'MOSS_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['moss', 'spend-management', 'suppliers', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
