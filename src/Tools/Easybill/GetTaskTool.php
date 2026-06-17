<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class GetTaskTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.task.GET';
    }

    public function getDescription(): string
    {
        return 'GET /tasks/{id} — Aufgabe abrufen.';
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
            'task_id' => [
              'type' => 'integer',
              'description' => 'ID der Aufgabe',
            ],
          ],
          'required' => [
            0 => 'task_id',
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
            $result = $svc->getTask($context->user, (int) $arguments['task_id']);
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
            'category' => 'query',
            'tags' => [
              0 => 'easybill',
              1 => 'tasks',
              2 => 'get',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}