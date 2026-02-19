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
        return 'POST /articles - Erstellt einen neuen Lexware-Artikel. Beispiel: {"title":"Beratungsstunde","type":"SERVICE","unitName":"Stunde","price":{"netPrice":120.00,"grossPrice":142.80,"leadingPrice":"NET","taxRate":19.0}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => 'Artikeldaten für die Lexware API.',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'PFLICHT. Artikelbezeichnung, z.B. "Beratungsstunde".'],
                        'description' => ['type' => 'string', 'description' => 'Beschreibung des Artikels.'],
                        'type' => [
                            'type' => 'string',
                            'description' => 'PFLICHT. Artikeltyp: "PRODUCT" (physisches Produkt) oder "SERVICE" (Dienstleistung). GROSSBUCHSTABEN!',
                        ],
                        'articleNumber' => ['type' => 'string', 'description' => 'Eigene Artikelnummer, z.B. "ART-001".'],
                        'unitName' => ['type' => 'string', 'description' => 'Einheit, z.B. "Stück", "Stunde", "kg".'],
                        'price' => [
                            'type' => 'object',
                            'description' => 'Preisinformationen.',
                            'properties' => [
                                'netPrice' => ['type' => 'number', 'description' => 'Nettopreis, z.B. 100.00.'],
                                'grossPrice' => ['type' => 'number', 'description' => 'Bruttopreis, z.B. 119.00.'],
                                'leadingPrice' => ['type' => 'string', 'description' => 'Führender Preis: "NET" oder "GROSS". GROSSBUCHSTABEN!'],
                                'taxRate' => ['type' => 'number', 'description' => 'Steuersatz in Prozent, z.B. 19.0 oder 7.0.'],
                            ],
                        ],
                        'note' => ['type' => 'string', 'description' => 'Interne Notiz zum Artikel.'],
                    ],
                    'required' => ['title', 'type'],
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
