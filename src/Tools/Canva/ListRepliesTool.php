<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class ListRepliesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.comments.thread.replies.GET';
    }

    public function getDescription(): string
    {
        return 'Listet Antworten eines Kommentar-Threads auf.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'thread_id' => ['type' => 'string', 'description' => 'ID des Kommentar-Threads.'],
                'continuation' => ['type' => 'string', 'description' => 'Optional: Continuation-Token für Pagination.'],
            ],
            'required' => ['thread_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['thread_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Thread-ID ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->listReplies($context->user, $arguments['thread_id'], $arguments['continuation'] ?? null);

            return ToolResult::success([
                'items' => array_map(fn($item) => $item->toArray(), $result['items']),
                'continuation' => $result['continuation'] ?? null,
            ]);
        } catch (CanvaApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CANVA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['canva', 'comment', 'replies'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
