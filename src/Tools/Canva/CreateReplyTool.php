<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class CreateReplyTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.comments.replies.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt eine Antwort auf einen Kommentar-Thread.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'thread_id' => ['type' => 'string', 'description' => 'ID des Kommentar-Threads, auf den geantwortet werden soll.'],
                'message' => ['type' => 'string', 'description' => 'Nachricht der Antwort.'],
            ],
            'required' => ['thread_id', 'message'],
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

        if (empty($arguments['message'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Nachricht ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->createReply($context->user, $arguments['thread_id'], ['message' => $arguments['message']]);

            return ToolResult::success($result->toArray());
        } catch (CanvaApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CANVA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['canva', 'comment', 'reply'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
