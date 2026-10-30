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
 * Eingangsrechnungen auflisten (GET /invoices).
 */
class ListInvoicesTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    private const OPERATION = 'GET /invoices';

    public function getName(): string
    {
        return 'integrations.cisbox.invoices.GET';
    }

    public function getDescription(): string
    {
        return 'Listet Eingangsrechnungen aus cisbox (GET /invoices) mit Freigabe-, Kontierungs- und '
            . 'Zahlungsstatus, Beträgen, Fälligkeit und Skonto. Filtern nach Rechnungsdatum '
            . '(invoiceDateFrom/-To, yyyy-MM-dd), Buchungsperiode (accountingPeriodFrom/-To, YYYYMM), '
            . 'Kreditor (creditorNo), Kostenstelle (code1) und Stornostatus (cancelled). Für offene '
            . 'Posten mit Zahlbeträgen stattdessen integrations.cisbox.invoices.pending.GET nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'invoiceDateFrom' => [
                    'type' => 'string',
                    'description' => 'Rechnungsdatum ab (einschließlich), ISO yyyy-MM-dd.',
                ],
                'invoiceDateTo' => [
                    'type' => 'string',
                    'description' => 'Rechnungsdatum bis (einschließlich), ISO yyyy-MM-dd.',
                ],
                'accountingPeriodFrom' => [
                    'type' => 'string',
                    'description' => 'Buchungsperiode ab (einschließlich), Format YYYYMM — z.B. "202601".',
                ],
                'accountingPeriodTo' => [
                    'type' => 'string',
                    'description' => 'Buchungsperiode bis (einschließlich), Format YYYYMM.',
                ],
                'creditorNo' => [
                    'type' => 'integer',
                    'description' => 'Nur Rechnungen dieses Kreditors.',
                ],
                'code1' => [
                    'type' => 'string',
                    'description' => 'Nur Rechnungen mit mindestens einer Kontierungszeile auf dieser Kostenstelle (Code 1).',
                ],
                'cancelled' => [
                    'type' => 'boolean',
                    'description' => 'true liefert nur stornierte Rechnungen, false schließt sie aus. '
                        . 'Ohne Angabe sind beide enthalten.',
                ],
                'updatedSince' => [
                    'type' => 'string',
                    'description' => 'Nur seit diesem Zeitpunkt geänderte Rechnungen (ISO-8601). '
                        . 'Der Weg für inkrementelle Abgleiche.',
                ],
            ], $this->paginationSchema()),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardSearch($arguments)) {
            return $error;
        }

        $query = $this->listQuery($arguments, [
            'invoiceDateFrom', 'invoiceDateTo',
            'accountingPeriodFrom', 'accountingPeriodTo',
            'creditorNo', 'code1', 'cancelled',
        ]);

        if ($error = $this->guardEnums(self::OPERATION, $query)) {
            return $error;
        }

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            return ToolResult::success(
                $this->runList($service, $context->user, '/invoices', [], $query, $arguments)
            );
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
            'tags' => ['cisbox', 'invoice', 'rechnungen', 'eingangsrechnungen', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
