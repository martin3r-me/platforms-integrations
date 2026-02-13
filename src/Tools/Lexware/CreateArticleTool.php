<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateArticleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.articles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /articles - Erstellt einen neuen Lexware-Artikel. data (object) - Artikeldaten gemäß Lexware API. Wichtige Felder: title, type (product/service), unitName, unitPrice, taxType.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Artikeldaten gemäß Lexware API. Felder: title (string), description (string), type (enum: product, service), articleNumber (string), unitName (string), price (object mit netPrice, grossPrice, taxRatePercentage), note (string).',
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
            return ToolResult::error('VALIDATION_ERROR', 'Artikeldaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class);
            $result = $service->createArticle($context->user, $arguments['data']);
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
            'tags' => ['lexware', 'articles', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
