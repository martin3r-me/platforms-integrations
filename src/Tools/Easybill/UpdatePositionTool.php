<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Tools\Easybill\Concerns\GuardsArguments;

class UpdatePositionTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.easybill.position.PUT';
    }

    public function getDescription(): string
    {
        return <<<TXT
PUT /positions/{id} — Stamm-Artikel aktualisieren.

Voll-Update: easybill ersetzt den Artikel mit dem Payload — am sichersten zuerst GET /positions/{id}, anpassen, komplettes Objekt zurückschicken.

WICHTIG: sale_price und purchase_price sind Integer-Cent (185000 = 1.850,00 €).

Häufige data-Felder (selbe Struktur wie POST /positions):
- Stammdaten: type (PRODUCT|SERVICE|TEXT), number, description, description_intern, group_id, bundle_id
- Preise: sale_price (Cent), sale_price_gross (Cent), sale_price_brutto_or_net_type (NET|GROSS), purchase_price (Cent), vat_percent
- Einheit: unit, unit_factor
- Lager: stock_initial, stock_min, stock_keeping, weight, ean
- Buchhaltung: booking_account, tax_account, cost_center_1, cost_center_2, export_cost_1, export_cost_2
- Rabatt: discount, discount_type (AMOUNT|PERCENT)
- Partner: partner_id
- Custom: info_1, info_2

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
            'position_id' => [
              'type' => 'integer',
              'description' => 'ID der Position',
            ],
            'data' => [
              'type' => 'object',
              'description' => 'Artikel-Daten — vollständiger Stand, nicht diff. Siehe Tool-Description für alle Felder.',
              'additionalProperties' => true,
            ],
          ],
          'required' => [
            0 => 'position_id',
            1 => 'data',
          ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['position_id', 'data'])) {
            return $guard;
        }

        try {
            $svc = app(EasybillApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->updatePosition($context->user, (int) $arguments['position_id'], $arguments['data']);
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
              1 => 'positions',
              2 => 'update',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}