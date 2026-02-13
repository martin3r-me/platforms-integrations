<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class ListPostingCategoriesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.posting_categories.GET';
    }

    public function getDescription(): string
    {
        return 'GET /posting-categories - Listet alle verfügbaren Buchungskategorien in Lexware auf (Stammdaten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
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
            $result = $service->getPostingCategories($context->user);
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
            'tags' => ['lexware', 'posting_categories', 'reference_data'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
