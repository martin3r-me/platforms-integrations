<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class ListCustomersTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.customers.GET';
    }

    public function getDescription(): string
    {
        return <<<TXT
        GET /customers — Listet Kunden (paginiert).

        SUCHE: Nutze den Parameter `search` für eine Freitext-Suche — dann werden nur Treffer
        zurückgegeben (client-seitig über number, company_name, first_name, last_name, display_name,
        emails, notes gefiltert). easybill hat server-seitig KEINEN Freitext-Filter; ohne `search`
        kommt die komplette Liste zurück.

        EXAKTE FILTER server-seitig via `query` (kombinierbar mit search):
        limit, page, number, company_name, first_name, last_name, emails, country, zip_code,
        group_id, additional_group_id, created_at.
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
              'description' => 'Freitext-Suchbegriff. Gibt nur Treffer zurück (client-seitige Substring-Suche über '
                . 'number, company_name, first_name, last_name, display_name, emails, notes). '
                . 'Bevorzugt gegenüber dem Durchscannen der Gesamtliste.',
            ],
            'query' => [
              'type' => 'object',
              'description' => 'Server-seitige easybill-Feldfilter, z.B. {"company_name":"Foodsolutions"} oder '
                . '{"number":"K-1001"}, sowie limit/page. Wird mit `search` kombiniert (erst server-seitig filtern, dann client-seitig suchen).',
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
                ? $svc->searchCustomers($context->user, $search, $query)
                : $svc->listCustomers($context->user, $query);

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
              1 => 'customers',
              2 => 'list',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}