<?php

namespace Platform\Integrations\Tools\Canva;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\CanvaApiService;
use Platform\Integrations\Exceptions\CanvaApiException;

/**
 * LLM-Tool: Design nach Canva importieren
 *
 * Importiert ein Design nach Canva (z.B. von einer URL).
 */
class ImportDesignTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.canva.design_imports.POST';
    }

    public function getDescription(): string
    {
        return 'Importiert ein Design nach Canva (z.B. von einer URL).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: ID einer spezifischen Canva-Connection. Wenn nicht angegeben, wird die Standard-Connection verwendet.'],
                'url' => ['type' => 'string', 'description' => 'URL der Datei, die nach Canva importiert werden soll.'],
                'title' => ['type' => 'string', 'description' => 'Optional: Titel fuer das importierte Design.'],
            ],
            'required' => ['url'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if (empty($arguments['url'])) {
            return ToolResult::error('VALIDATION_ERROR', 'URL ist erforderlich.');
        }

        try {
            $service = app(CanvaApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $params = [
                'url' => $arguments['url'],
            ];

            if (!empty($arguments['title'])) {
                $params['title'] = $arguments['title'];
            }

            $result = $service->importDesign($context->user, $params);

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
            'tags' => ['canva', 'import', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'low',
        ];
    }
}
