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
 * Offene Posten auflisten (GET /invoices/pending).
 */
class ListPendingInvoicesTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    private const OPERATION = 'GET /invoices/pending';

    public function getName(): string
    {
        return 'integrations.cisbox.invoices.pending.GET';
    }

    public function getDescription(): string
    {
        return 'Listet offene Posten aus cisbox (GET /invoices/pending): offener und bereits gezahlter '
            . 'Betrag, nächste geplante Zahlung, Fälligkeit, Skontofrist und -betrag, Zahlungssperre, '
            . 'Priorität und Lastschrifttermin. Der richtige Einstieg für Fragen nach fälligen oder '
            . 'ungezahlten Rechnungen. Mit "status" nach Freigabe-/Zahlungsstatus filtern — mehrere '
            . 'Werte als Array.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'status' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['NOT_APPROVED', 'APPROVED', 'IN_PROCESS', 'CANCELLED', 'PAID'],
                    ],
                    'description' => 'Freigabe-/Zahlungsstatus. Mehrere Werte sind erlaubt, z.B. '
                        . '["APPROVED","IN_PROCESS"] für alles, was zur Zahlung ansteht.',
                ],
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
                    'description' => 'Buchungsperiode ab (einschließlich), Format YYYYMM.',
                ],
                'accountingPeriodTo' => [
                    'type' => 'string',
                    'description' => 'Buchungsperiode bis (einschließlich), Format YYYYMM.',
                ],
                'creditorNo' => [
                    'type' => 'integer',
                    'description' => 'Nur offene Posten dieses Kreditors.',
                ],
                'code1' => [
                    'type' => 'string',
                    'description' => 'Nur Posten mit mindestens einer Kontierungszeile auf dieser Kostenstelle (Code 1).',
                ],
                'updatedSince' => [
                    'type' => 'string',
                    'description' => 'Nur seit diesem Zeitpunkt geänderte Posten (ISO-8601).',
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
            'status',
            'invoiceDateFrom', 'invoiceDateTo',
            'accountingPeriodFrom', 'accountingPeriodTo',
            'creditorNo', 'code1',
        ]);

        if ($error = $this->guardEnums(self::OPERATION, $query)) {
            return $error;
        }

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            return ToolResult::success(
                $this->runList($service, $context->user, '/invoices/pending', [], $query, $arguments)
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
            'tags' => ['cisbox', 'invoice', 'offene-posten', 'faellig', 'zahlungen', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
