<?php

namespace Platform\Integrations\Tools\Lexware;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\LexwareApiService;
use Platform\Integrations\Exceptions\LexwareApiException;

class CreateDeliveryNoteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.lexware.delivery_notes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /delivery-notes - Erstellt einen neuen Lexware-Lieferschein. HINWEIS: Lieferscheine enthalten nur Mengenangaben, KEINE Preise! Beispiel: {"voucherDate":"2024-06-15","address":{"contactId":"UUID"},"lineItems":[{"type":"custom","name":"Produkt A","quantity":5,"unitName":"Karton"}],"shippingConditions":{"shippingDate":"2024-06-15","shippingType":"delivery"}}';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Lexware-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'data' => [
                    'type' => 'object',
                    'description' => 'Lieferscheindaten für die Lexware API. WICHTIG: Lieferscheine enthalten KEINE Preise - nur Mengen!',
                    'properties' => [
                        'voucherDate' => ['type' => 'string', 'description' => 'Datum im Format YYYY-MM-DD.'],
                        'address' => [
                            'type' => 'object',
                            'description' => 'PFLICHT. Empfänger. ENTWEDER {"contactId":"UUID"} ODER {"name":"...","street":"...","zip":"...","city":"...","countryCode":"DE"}.',
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'PFLICHT. Positionen (NUR Mengen, keine Preise). Jede Position: {"type":"custom","name":"Produkt A","quantity":5,"unitName":"Karton"}.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string', 'description' => 'PFLICHT. "custom" oder "material".'],
                                    'name' => ['type' => 'string', 'description' => 'PFLICHT. Bezeichnung.'],
                                    'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
                                    'quantity' => ['type' => 'number', 'description' => 'PFLICHT. Menge.'],
                                    'unitName' => ['type' => 'string', 'description' => 'Einheit, z.B. "Karton", "Stück", "Palette".'],
                                ],
                            ],
                        ],
                        'shippingConditions' => [
                            'type' => 'object',
                            'description' => 'Lieferbedingungen.',
                            'properties' => [
                                'shippingDate' => ['type' => 'string', 'description' => 'Lieferdatum YYYY-MM-DD.'],
                                'shippingType' => ['type' => 'string', 'description' => '"delivery", "pickup" etc.'],
                            ],
                        ],
                        'title' => ['type' => 'string', 'description' => 'Titel, z.B. "Lieferschein".'],
                        'introduction' => ['type' => 'string', 'description' => 'Einleitungstext.'],
                        'remark' => ['type' => 'string', 'description' => 'Schlussbemerkung.'],
                    ],
                    'required' => ['address', 'lineItems'],
                ],
                'finalize' => ['type' => 'boolean', 'description' => 'Direkt finalisieren (default: false).'],
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
            return ToolResult::error('VALIDATION_ERROR', 'Lieferscheindaten (data) sind erforderlich.');
        }

        try {
            $service = app(LexwareApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $service->createDeliveryNote($context->user, $arguments['data'], $arguments['finalize'] ?? false);
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
            'tags' => ['lexware', 'delivery_notes', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'side_effects' => ['creates'],
        ];
    }
}
