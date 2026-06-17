<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class CreateDocumentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.document.POST';
    }

    public function getDescription(): string
    {
        return 'POST /documents — Beleg erstellen. `type` ist Pflicht (INVOICE/OFFER/CREDIT/DELIVERY_NOTE/ORDER_CONFIRMATION/…). is_draft=false finalisiert direkt.';
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
            'data' => [
              'type' => 'object',
              'description' => 'Beleg-Daten inkl. type, customer_id, items (Array von Positionen), document_date, …',
            ],
          ],
          'required' => [
            0 => 'data',
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
            $result = $svc->createDocument($context->user, $arguments['data']);
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
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}