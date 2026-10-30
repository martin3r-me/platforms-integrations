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
 * Zahlungen auflisten oder einzeln abrufen.
 */
class ListPaymentsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    private const OPERATION = 'GET /payments';

    public function getName(): string
    {
        return 'integrations.cisbox.payments.GET';
    }

    public function getDescription(): string
    {
        return 'Zahlungen aus cisbox (GET /payments): Betrag, abgezogenes Skonto, Fälligkeit, '
            . 'Zahldatum, Bankkonto und Verarbeitungsstatus — von PROPOSAL (Zahlungsvorschlag) über '
            . 'WORKING bis COMPLETED. Filtern über "createdFrom"/"createdTo" (yyyy-MM-dd) und "status". '
            . 'Mit "paymentId" wird eine einzelne Zahlung abgerufen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge([
                'paymentId' => [
                    'type' => 'integer',
                    'description' => 'ID der Zahlung. Ohne Angabe wird die Liste geliefert.',
                ],
                'status' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => [
                            'UNDEFINED', 'PROPOSAL', 'WORKING', 'STATUS_3', 'STATUS_4',
                            'ERROR', 'WAITING_FOR_FINAPI', 'WAITING_FOR_CREDITOR_PAYMENT', 'COMPLETED',
                        ],
                    ],
                    'description' => 'Verarbeitungsstatus der Zahlung. Mehrere Werte sind erlaubt.',
                ],
                'createdFrom' => [
                    'type' => 'string',
                    'description' => 'Zahlungen angelegt ab (einschließlich), ISO yyyy-MM-dd.',
                ],
                'createdTo' => [
                    'type' => 'string',
                    'description' => 'Zahlungen angelegt bis (einschließlich), ISO yyyy-MM-dd.',
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

        $paymentId = $arguments['paymentId'] ?? null;

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            if ($paymentId !== null) {
                $response = $service->call(
                    $context->user,
                    'GET',
                    '/payments/{paymentId}',
                    ['paymentId' => (int) $paymentId]
                );
                $payment = $service->single($response);

                if ($payment === null) {
                    return ToolResult::error('NOT_FOUND', "Keine Zahlung mit der ID {$paymentId} gefunden.");
                }

                return ToolResult::success([
                    'data' => $payment,
                    'request_id' => $response['request_id'] ?? null,
                ]);
            }

            // Die Zahlungsliste kennt kein updatedSince.
            $query = $this->listQuery($arguments, ['status', 'createdFrom', 'createdTo']);
            unset($query['updatedSince']);

            if ($error = $this->guardEnums(self::OPERATION, $query)) {
                return $error;
            }

            return ToolResult::success(
                $this->runList($service, $context->user, '/payments', [], $query, $arguments)
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
            'tags' => ['cisbox', 'zahlungen', 'payments', 'skonto', 'bank'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
