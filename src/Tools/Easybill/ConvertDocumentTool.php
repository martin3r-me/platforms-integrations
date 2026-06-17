<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class ConvertDocumentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.document.convert';
    }

    public function getDescription(): string
    {
        return 'POST /documents/{id}/{type} — Beleg in anderen Belegtyp umwandeln (z.B. Angebot → Rechnung).';
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
              'description' => 'ID des Quell-Belegs',
            ],
            'target_type' => [
              'type' => 'string',
              'description' => 'Ziel-Belegtyp (INVOICE, ORDER_CONFIRMATION, DELIVERY_NOTE, …)',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Optionale Override-Felder für den neuen Beleg.',
            ],
          ],
          'required' => [
            0 => 'document_id',
            1 => 'target_type',
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
            $result = $svc->convertDocument($context->user, (int) $arguments['document_id'], $arguments['target_type'], $arguments['data'] ?? []);
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
              2 => 'convert',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}