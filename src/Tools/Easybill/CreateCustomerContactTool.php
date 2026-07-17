<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class CreateCustomerContactTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.customer.contact.POST';
    }

    public function getDescription(): string
    {
        return <<<TXT
POST /customers/{customerId}/contacts — Ansprechpartner/Kontakt zu einem Kunden anlegen.

Ein Kunde (z.B. Firma) kann beliebig viele Kontaktpersonen haben (Geschäftsführung, Buchhaltung, technischer Ansprechpartner …). Pro Beleg kann genau einer als `contact_id` referenziert werden.

EMPFOHLEN: mindestens first_name und last_name (oder email).

Häufige data-Felder:
- Identität: salutation (0/1/2 = unbestimmt/Herr/Frau), title, first_name, last_name, suffix_1, suffix_2, position (Funktion im Unternehmen, z.B. "Geschäftsführer", "Einkauf")
- Kontakt: email, phone, mobile, fax
- Persönlich: birth_date (YYYY-MM-DD)
- Flags: usable_for_documents (true = darf auf Belegen als Ansprechpartner gesetzt werden), main_address (true = Hauptansprechpartner)
- Custom: note (interne Notiz)

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
            'data' => [
              'type' => 'object',
              'description' => 'Kontakt-Daten. Siehe Tool-Description. Beispiel: {"salutation": 1, "first_name": "Max", "last_name": "Mustermann", "position": "Geschäftsführer", "email": "max@muster.de", "phone": "+49 211 1234567", "usable_for_documents": true}.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'customer_id',
            1 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['customer_id', 'data'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->createCustomerContact($context->user, (int) $arguments['customer_id'], $arguments['data']);
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
              2 => 'create',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}