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
        return 'PUT /articles/{id} - Aktualisiert einen Lexware-Artikel. Zuerst per GET abrufen um aktuelle version zu erhalten. Beispiel: {"version":1,"title":"Neuer Titel","type":"SERVICE","unitName":"Stunde","price":{"netPrice":150.00,"grossPrice":178.50,"leadingPrice":"NET","taxRate":19.0}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Lexware-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'id' => ['type' => 'string', 'description' => 'PFLICHT. UUID des Artikels (aus vorherigem GET).'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Aktualisierte Artikeldaten. WICHTIG: version muss enthalten sein (aus vorherigem GET).',
                    'properties' => [
                        'version' => ['type' => 'integer', 'description' => 'PFLICHT. Aktuelle Version (aus GET). Optimistic Locking.'],
                        'title' => ['type' => 'string', 'description' => 'Artikelbezeichnung.'],
                        'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
                        'type' => ['type' => 'string', 'description' => 'Artikeltyp: "PRODUCT" oder "SERVICE". GROSSBUCHSTABEN!'],
                        'articleNumber' => ['type' => 'string', 'description' => 'Eigene Artikelnummer.'],
                        'unitName' => ['type' => 'string', 'description' => 'Einheit, z.B. "Stück", "Stunde".'],
                        'price' => [
                            'type' => 'object',
                            'description' => 'Preisinformationen: netPrice (number), grossPrice (number), leadingPrice ("NET"/"GROSS"), taxRate (number, z.B. 19.0).',
                        ],
                        'note' => ['type' => 'string', 'description' => 'Interne Notiz.'],
                    ],
                    'required' => ['version'],
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
            $service = app(LexwareApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->updateArticle($context->user, $arguments['id'], $arguments['data']);
            return ToolResult::success($result);
        } catch (LexwareApiException $e) {
            $errorMsg = $e->getMessage();
            $responseData = $e->getResponseData();
            if ($responseData) {
                $errorMsg .= ' | API-Response: ' . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return ToolResult::error($e->getLexwareErrorCode() ?? 'LEXWARE_ERROR', $errorMsg);
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
