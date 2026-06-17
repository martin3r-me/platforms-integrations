<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class SendDocumentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.document.send';
    }

    public function getDescription(): string
    {
        return 'POST /documents/{id}/send/{type} — Beleg versenden. type ∈ {email, fax, post, sms, whatsapp}. data enthält optionale Overrides (subject, message, to_emails, …).';
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
            'document_id' => [
              'type' => 'integer',
              'description' => 'ID des Belegs',
            ],
            'type' => [
              'type' => 'string',
              'enum' => [
                0 => 'email',
                1 => 'fax',
                2 => 'post',
                3 => 'sms',
                4 => 'whatsapp',
              ],
              'description' => 'Versandweg',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Override-Felder (subject, message, to_emails, cc_emails, …).',
            ],
          ],
          'required' => [
            0 => 'document_id',
            1 => 'type',
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
            $result = $svc->sendDocument($context->user, (int) $arguments['document_id'], $arguments['type'], $arguments['data'] ?? []);
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
              1 => 'documents',
              2 => 'send',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}