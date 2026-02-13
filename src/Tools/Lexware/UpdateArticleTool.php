<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class UpdateArticleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.articles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /articles/{id} - Aktualisiert einen Lexware-Artikel. id (string, UUID). data (object) - Aktualisierte Felder.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'UUID des Artikels'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Aktualisierte Artikeldaten. Felder: title, description, type, articleNumber, unitName, price, note.',
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
            return ToolResult::error('VALIDATION_ERROR', 'Artikel-ID ist erforderlich.');
        }

        if (empty($arguments['data'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Aktualisierte Daten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->updateArticle($context->user, $arguments['id'], $arguments['data']);
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
            'tags' => ['lexware', 'articles', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['updates'],
        ];
    }
}
