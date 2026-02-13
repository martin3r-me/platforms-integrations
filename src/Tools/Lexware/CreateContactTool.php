<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateContactTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'lexware.contacts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /contacts - Erstellt einen neuen Lexware-Kontakt (Kunde/Lieferant). Mindestens company.name oder person (firstName+lastName) erforderlich.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Kontakt-Daten gemäß Lexware API. Wichtige Felder: version (int), roles (object mit customer/vendor), company (object mit name), person (object mit salutation, firstName, lastName), addresses (object mit billing/shipping arrays), emailAddresses (object mit business/office/private/other arrays), phoneNumbers (object mit business/office/mobile/private/fax/other arrays), note (string).',
                ],
            ],
            'required' => ['data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['data'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Kontakt-Daten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->createContact($context->user, $arguments['data']);
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
            'tags' => ['lexware', 'contacts', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
