<?php

namespace Platform\Integrations\Tools\Buchhaltungsbutler;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Exceptions\BuchhaltungsbutlerApiException;
use Platform\Integrations\Services\BuchhaltungsbutlerApiService;
use Platform\Integrations\Tools\Buchhaltungsbutler\Concerns\BuildsInvoiceDraftPayload;

class CreateCreditNoteDraftTool implements ToolContract, ToolMetadataContract
{
    use BuildsInvoiceDraftPayload;

    public function getName(): string
    {
        return 'integrations.buchhaltungsbutler.invoices.draft.credit';
    }

    public function getDescription(): string
    {
        return 'POST /invoices/create/draft (type=credit) — Erstellt einen GUTSCHRIFTS-Entwurf in BuchhaltungsButler. Der Entwurf landet unter "Rechnungsstellung → Entwürfe" und kann dort manuell finalisiert werden.';
    }

    public function getSchema(): array
    {
        return $this->invoiceDraftSchema('Gutschrift');
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['items']) || !is_array($arguments['items'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Mindestens eine Position (items) ist erforderlich.');
        }

        try {
            $service = app(BuchhaltungsbutlerApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $body    = $this->buildInvoiceDraftBody($arguments);
            $result  = $service->createInvoiceDraft($context->user, 'credit', $body);
            return ToolResult::success($result);
        } catch (BuchhaltungsbutlerApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'BUCHHALTUNGSBUTLER_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['buchhaltungsbutler', 'credit_notes', 'draft', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
