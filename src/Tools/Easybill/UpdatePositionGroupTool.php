<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class UpdatePositionGroupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.position-group.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /position-groups/{id} — Artikelgruppe aktualisieren.';
    }

    public function getSchema(): array
    {
        return [
          'type' => 'object',
          'properties' => [
            'connection_id' => [
              'type' => 'integer',
              'description' => 'Optional: ID einer spezifischen easybill-Connection.',
            ],
            'group_id' => [
              'type' => 'integer',
              'description' => 'ID der Artikelgruppe',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Payload-Daten für das Update.',
            ],
          ],
          'required' => [
            0 => 'group_id',
            1 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->updatePositionGroup($context->user, (int) $arguments['group_id'], $arguments['data']);
            return ToolResult::success($result);
        } catch (EasybillApiException $e) {
            return ToolResult::error($e->getEasybillErrorCode() ?? 'EASYBILL_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'mutation',
            'tags' => [
              0 => 'easybill',
              1 => 'position-groups',
              2 => 'update',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}