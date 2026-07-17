<?php

namespace Platform\Integrations\Tools\Dedefleet;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\DedefleetApiService;
use Platform\Integrations\Exceptions\DedefleetApiException;
use Platform\Integrations\Tools\Dedefleet\Concerns\GuardsArguments;

/**
 * Tourenplanung Step 2 (Variante) — POST /Tour/CreateFromTemplate: erzeugt eine Tour aus einer Vorlage. templateName wird zuerst als ID, dann als Name aufgelöst. Verfügbare Vorlagen via tours-templates (Tour/ListTemplates) bzw. call().
 */
class CreateTourFromTemplateTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.dedefleet.tour.from-template.POST';
    }

    public function getDescription(): string
    {
        return 'Tourenplanung Step 2 (Variante) — POST /Tour/CreateFromTemplate: erzeugt eine Tour aus einer Vorlage. templateName wird zuerst als ID, dann als Name aufgelöst. Verfügbare Vorlagen via tours-templates (Tour/ListTemplates) bzw. call().';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'templateName' => ['type' => 'string', 'description' => 'Name oder ID der Tour-Vorlage.'],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
                ],
            ],
            'required' => ['templateName'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['templateName'])) {
            return $guard;
        }

        $payload = [];
        foreach (['templateName'] as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $payload[$k] = $arguments[$k];
            }
        }

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->createTourFromTemplate($context->user, $payload);

            return ToolResult::success($result);
        } catch (DedefleetApiException $e) {
            return ToolResult::error($e->getDedefleetErrorCode() ?? 'DEDEFLEET_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['dedefleet', 'tourenplanung', 'step-2'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
