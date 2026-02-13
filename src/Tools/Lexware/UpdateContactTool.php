<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class UpdateContactTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'lexware.contacts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /contacts/{id} - Aktualisiert einen Lexware-Kontakt. id (string, UUID) - Kontakt-ID. data (object) - Aktualisierte Felder. WICHTIG: version-Feld muss mitgesendet werden (aus vorherigem GET).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'UUID des Kontakts'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Aktualisierte Kontakt-Daten. WICHTIG: version (int) muss enthalten sein. Felder: roles, company, person, addresses, emailAddresses, phoneNumbers, note.',
                ],
            ],
            'required' => ['id', 'data'],
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

        if (empty($arguments['data'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Aktualisierte Daten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->updateContact($context->user, $arguments['id'], $arguments['data']);
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
            'tags' => ['lexware', 'contacts', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['updates'],
        ];
    }
}
