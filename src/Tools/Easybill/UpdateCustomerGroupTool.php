<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class UpdateCustomerGroupTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.customer-group.PUT';
    }

    public function getDescription(): string
    {
        return <<<TXT
PUT /customer-groups/{id} — Kundengruppe aktualisieren.

Voll-Update: easybill ersetzt die Gruppe mit dem Payload — am sichersten zuerst GET /customer-groups/{id}, anpassen, komplettes Objekt zurückschicken.

WICHTIG: discount bei discount_type=AMOUNT ist Integer-Cent; bei PERCENT Prozentwert (1–100).

Häufige data-Felder (selbe Struktur wie POST):
- Stamm: name, archived
- Zahlung: due_in_days, cash_allowance, cash_allowance_days, payment_options
- Preise/Rabatt: sale_price_level, discount, discount_type
- Verknüpfung: text_template_ids[], additional_groups_ids[]
- Custom: buyer_reference, info_1, info_2

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
            'group_id' => [
              'type' => 'integer',
              'description' => 'ID der Kundengruppe',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Gruppen-Daten — vollständiger Stand, nicht diff. Siehe Tool-Description für alle Felder.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'group_id',
            1 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['group_id', 'data'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->updateCustomerGroup($context->user, (int) $arguments['group_id'], $arguments['data']);
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
              1 => 'customer-groups',
              2 => 'update',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}