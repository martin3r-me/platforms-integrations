<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class ListVoucherlistTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.voucherlist.GET';
    }

    public function getDescription(): string
    {
        return 'GET /voucherlist - Zentrale Belegliste mit Filtern. Listet alle Belegarten (Rechnungen, Angebote, Gutschriften etc.) paginiert auf. Filter: voucherType, voucherStatus, archived, contactId.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seite (0-basiert, default: 0)'],
                'size' => ['type' => 'integer', 'description' => 'Einträge pro Seite (max 250, default: 25)'],
                'voucherType' => ['type' => 'string', 'description' => 'Belegart filtern (z.B. invoice, quotation, creditnote, orderconfirmation, deliverynote, dunning)'],
                'voucherStatus' => ['type' => 'string', 'description' => 'Belegstatus filtern (z.B. draft, open, paid, overdue, voided)'],
                'archived' => ['type' => 'boolean', 'description' => 'Nur archivierte (true) oder nicht-archivierte (false) Belege'],
                'contactId' => ['type' => 'string', 'description' => 'Nur Belege eines bestimmten Kontakts (UUID)'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->getVoucherlist(
                $context->user,
                $arguments['page'] ?? 0,
                $arguments['size'] ?? 25,
                $arguments['voucherType'] ?? null,
                $arguments['voucherStatus'] ?? null,
                $arguments['archived'] ?? null,
                $arguments['contactId'] ?? null
            );
            return ToolResult::success($result);
        } catch (LexwareApiException $e) {
            return ToolResult::error($e->getLexwareErrorCode() ?? 'LEXWARE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['lexware', 'voucherlist', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
