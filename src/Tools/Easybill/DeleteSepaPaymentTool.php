<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class DeleteSepaPaymentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.sepa-payment.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /sepa-payments/{id} — SEPA-Zahlung löschen.';
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
            'sepa_payment_id' => [
              'type' => 'integer',
              'description' => 'ID der SEPA-Zahlung',
            ],
          ],
          'required' => [
            0 => 'sepa_payment_id',
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
            $result = $svc->deleteSepaPayment($context->user, (int) $arguments['sepa_payment_id']);
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
              1 => 'sepa-payments',
              2 => 'delete',
            ],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}