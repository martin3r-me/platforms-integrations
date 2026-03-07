<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Autofill-Job erstellen
 *
 * Erstellt einen Autofill-Job (fuellt ein Brand Template mit Daten aus).
 */
class CreateAutofillTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.autofills.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen Autofill-Job (fuellt Brand Template mit Daten aus).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'brand_template_id' => ['type' => 'string', 'description' => 'ID des Brand Templates, das ausgefuellt werden soll.'],
                'data' => ['type' => 'object', 'description' => 'Key-Value-Paare fuer die Template-Felder (Feldname => Wert).'],
                'title' => ['type' => 'string', 'description' => 'Optional: Titel fuer das resultierende Design.'],
            ],
            'required' => ['brand_template_id', 'data'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['brand_template_id'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Brand-Template-ID ist erforderlich.');
        }

        if (empty($arguments['data'])) {
            return ToolResult::error('VALIDATION_ERROR', 'Data (Template-Felder) ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'brand_template_id' => $arguments['brand_template_id'],
                'data' => $arguments['data'],
            ];

            if (!empty($arguments['title'])) {
                $params['title'] = $arguments['title'];
            }

            $result = $service->createAutofill($context->user, $params);

            return ToolResult::success($result->toArray());
        } catch (CanvaApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CANVA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['canva', 'autofill', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
