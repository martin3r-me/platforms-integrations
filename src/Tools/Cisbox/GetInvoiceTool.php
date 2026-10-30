<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Tools\Cisbox\Concerns\GuardsArguments;

/**
 * Eine Rechnung abrufen, wahlweise mit ihren Positionen.
 */
class GetInvoiceTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.cisbox.invoice.GET';
    }

    public function getDescription(): string
    {
        return 'Ruft eine einzelne Rechnung über ihre Barcode-Nummer ab (GET /invoices/{barcodeNo}). '
            . 'Mit "include_items": true kommen zusätzlich die Positionen samt Kontierungs-Splits '
            . '(Konto, Steuerschlüssel, Code 1–3) — die einzige Stelle, an der sich die Kostenverteilung '
            . 'einer Rechnung ablesen lässt. Die Barcode-Nummer liefert integrations.cisbox.invoices.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'barcodeNo' => [
                    'type' => 'integer',
                    'description' => 'Barcode-Nummer der Rechnung — die eindeutige Kennung des Belegs in cisbox.',
                ],
                'include_items' => [
                    'type' => 'boolean',
                    'description' => 'Zusätzlich die Positionen mit ihren Kontierungs-Splits laden '
                        . '(GET /invoices/{barcodeNo}/items).',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese Felder der Rechnung zurückgeben. Die Positionen bleiben unberührt.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten cisbox-Verbindung.',
                ],
            ],
            'required' => ['barcodeNo'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['barcodeNo'])) {
            return $error;
        }

        $barcodeNo = (int) $arguments['barcodeNo'];

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $response = $service->call($context->user, 'GET', '/invoices/{barcodeNo}', ['barcodeNo' => $barcodeNo]);
            $invoice = $service->single($response);

            if ($invoice === null) {
                return ToolResult::error('NOT_FOUND', "Keine Rechnung mit Barcode-Nummer {$barcodeNo} gefunden.");
            }

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $projected = CisboxApiService::projectFields($invoice, $arguments['fields']);
                $invoice = is_array($projected) ? $projected : $invoice;
            }

            $result = [
                'data' => $invoice,
                'request_id' => $response['request_id'] ?? null,
            ];

            if ($arguments['include_items'] ?? false) {
                $result['items'] = $service->getInvoiceItems($context->user, $barcodeNo);
            }

            return ToolResult::success($result);
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'invoice', 'rechnung', 'positionen', 'kontierung'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
