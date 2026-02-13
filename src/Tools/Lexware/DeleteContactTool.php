<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class DeleteContactTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.contacts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /contacts/{id} - Löscht einen Lexware-Kontakt. id (string, UUID) - Kontakt-ID. ACHTUNG: Kann nicht rückgängig gemacht werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'UUID des zu löschenden Kontakts'],
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
            return ToolResult::error('VALIDATION_ERROR', 'Kontakt-ID ist erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->deleteContact($context->user, $arguments['id']);
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
            'category' => 'action',
            'tags' => ['lexware', 'contacts', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'destructive',
            'side_effects' => ['deletes'],
        ];
    }
}
