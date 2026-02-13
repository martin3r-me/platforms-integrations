<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class RenderInvoicePdfTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.invoices.pdf.GET';
    }

    public function getDescription(): string
    {
        return 'GET /invoices/{id}/pdf - Rendert eine Lexware-Rechnung als PDF. Gibt die documentFileId zurück, mit der die Datei heruntergeladen werden kann. id (string, UUID) - Rechnungs-ID.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'UUID der Rechnung'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Rechnungs-ID ist erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->renderInvoicePdf($context->user, $arguments['id']);
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
            'tags' => ['lexware', 'invoices', 'pdf'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
