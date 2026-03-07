<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

class CreateCommentThreadTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.comments.threads.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen Kommentar-Thread auf einem Design.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'design_id' => ['type' => 'string', 'description' => 'ID des Designs, auf dem der Kommentar-Thread erstellt werden soll.'],
                'message' => ['type' => 'string', 'description' => 'Nachricht des Kommentar-Threads.'],
                'attached_to' => [
                    'type' => 'object',
                    'description' => 'Optional: Objekt zum Anheften des Kommentars an ein bestimmtes Element.',
                    'properties' => [
                        'type' => ['type' => 'string', 'description' => 'Typ des Elements (z.B. "element").'],
                        'id' => ['type' => 'string', 'description' => 'ID des Elements.'],
                    ],
                ],
            ],
            'required' => ['design_id', 'message'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['design_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Design-ID ist erforderlich.');
        }

        if (empty($arguments['message'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Nachricht ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'design_id' => $arguments['design_id'],
                'message' => $arguments['message'],
            ];

            if (!empty($arguments['attached_to'])) {
                $params['attached_to'] = $arguments['attached_to'];
            }

            $result = $service->createCommentThread($context->user, $params);

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
            'tags' => ['canva', 'comment', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
