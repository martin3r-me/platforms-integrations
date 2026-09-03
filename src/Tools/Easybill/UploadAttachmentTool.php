<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class UploadAttachmentTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.attachment.upload';
    }

    public function getDescription(): string
    {
        return 'POST /attachments — Datei-Upload per multipart/form-data (echter Anhang, z.B. PDF). '
            . 'Im Gegensatz zu integrations.easybill.attachment.POST (JSON, für Dateien nicht nutzbar) '
            . 'funktioniert dieser Weg tatsächlich.';
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
            'content' => [
              'type' => 'string',
              'description' => 'Dateiinhalt, base64-kodiert.',
            ],
            'file_name' => [
              'type' => 'string',
              'description' => 'Dateiname inkl. Endung, z.B. "Rechnung.pdf".',
            ],
            'content_type' => [
              'type' => 'string',
              'description' => 'Optional: MIME-Type. Wird sonst aus der Dateiendung bzw. dem Inhalt erkannt.',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Begleitfelder, z.B. document_id oder customer_id (siehe attachments.GET).',
            ],
          ],
          'required' => [
            0 => 'content',
            1 => 'file_name',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['content', 'file_name'])) {
            return $guard;
        }

        $fileContent = base64_decode($arguments['content'], true);

        if ($fileContent === false) {
            return ToolResult::error('VALIDATION_ERROR', 'content ist nicht gültig base64-kodiert.');
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->uploadAttachment(
                $context->user,
                $fileContent,
                $arguments['file_name'],
                $arguments['data'] ?? [],
                $arguments['content_type'] ?? null
            );
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
              1 => 'attachments',
              2 => 'upload',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
