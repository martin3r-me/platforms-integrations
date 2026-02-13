<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class ListContactsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'lexware.contacts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /contacts - Listet Lexware-Kontakte (Kunden/Lieferanten) auf. Paginiert (page, size).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Seite (0-basiert, default: 0)'],
                'size' => ['type' => 'integer', 'description' => 'Einträge pro Seite (max 250, default: 25)'],
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
            $result = $service->getContacts($context->user, $arguments['page'] ?? 0, $arguments['size'] ?? 25);
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
            'tags' => ['lexware', 'contacts', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
