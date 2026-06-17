<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class UpdateCustomerContactTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.customer.contact.PUT';
    }

    public function getDescription(): string
    {
        return <<<TXT
PUT /customers/{customerId}/contacts/{id} — Ansprechpartner/Kontakt zu einem Kunden aktualisieren.

Voll-Update: easybill ersetzt den Kontakt mit dem Payload — am sichersten zuerst GET /customers/{customerId}/contacts/{id}, anpassen, komplettes Objekt zurückschicken.

Häufige data-Felder (selbe Struktur wie POST):
- Identität: salutation (0/1/2), title, first_name, last_name, suffix_1, suffix_2, position
- Kontakt: email, phone, mobile, fax
- Persönlich: birth_date (YYYY-MM-DD)
- Flags: usable_for_documents, main_address
- Custom: note

Volle Feldliste: https://api.easybill.de/rest/v1/ (Swagger).
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
            'customer_id' => [
              'type' => 'integer',
              'description' => 'ID des Kunden',
            ],
            'contact_id' => [
              'type' => 'integer',
              'description' => 'ID des Kontakts',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Kontakt-Daten — vollständiger Stand, nicht diff. Siehe Tool-Description für alle Felder.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'customer_id',
            1 => 'contact_id',
            2 => 'data',
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
            $result = $svc->updateCustomerContact($context->user, (int) $arguments['customer_id'], (int) $arguments['contact_id'], $arguments['data']);
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
              1 => 'customer-contacts',
              2 => 'update',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}