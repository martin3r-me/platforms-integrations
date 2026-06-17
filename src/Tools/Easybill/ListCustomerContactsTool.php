<?php

namespace Platform\Integrations\Tools\Easybill;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\EasybillApiService;
use Platform\Integrations\Exceptions\EasybillApiException;

class ListCustomerContactsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.easybill.customer.contacts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /customers/{customerId}/contacts — Kontakte eines Kunden listen.';
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
            'query' => [
              'type' => 'object',
              'description' => 'Query-Parameter (limit, page, …).',
            ],
          ],
          'required' => [
            0 => 'customer_id',
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
            $result = $svc->listCustomerContacts($context->user, (int) $arguments['customer_id'], $arguments['query'] ?? []);
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
              1 => 'customer-contacts',
              2 => 'list',
            ],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}