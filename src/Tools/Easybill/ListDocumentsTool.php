<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class ListDocumentsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.documents.GET';
    }

    public function getDescription(): string
    {
        return <<<TXT
        GET /documents — Belege listen (paginiert).

        FILTER server-seitig via `query` (bevorzugt, da easybill hier präzise filtert): type
        (INVOICE/OFFER/CREDIT/DELIVERY_NOTE/ORDER_CONFIRMATION/…), customer_id, project_id, number,
        title, status, is_draft, is_archive, document_date, paid_at, ref_id, limit, page.

        SUCHE: `search` filtert client-seitig über number, title, text, external_id, order_number, ref_id
        und gibt nur Treffer zurück. easybill hat server-seitig keinen Freitext-Filter.
        TXT;
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
            'search' => [
              'type' => 'string',
              'description' => 'Freitext-Suchbegriff (client-seitige Substring-Suche über number, title, text, '
                . 'external_id, order_number, ref_id). Gibt nur Treffer zurück. Für präzise Treffer besser `query` nutzen.',
            ],
            'query' => [
              'type' => 'object',
              'description' => 'Server-seitige easybill-Feldfilter, z.B. {"type":"INVOICE","customer_id":12345} '
                . 'oder {"number":"RE-2026-001"}, sowie limit/page. Wird mit `search` kombiniert.',
            ],
          ],
          'required' => [
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
            $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];
            $search = trim((string) ($arguments['search'] ?? ''));

            $result = $search !== ''
                ? $svc->searchDocuments($context->user, $search, $query)
                : $svc->listDocuments($context->user, $query);

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
              1 => 'documents',
              2 => 'list',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}