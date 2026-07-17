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
 * Tourenplanung Step 4 — POST /Tour/ChangeStatus: ändert den Status einer Tour. 0=Planning, 1=Released (freigegeben), 2=Completed.
 */
class ChangeTourStatusTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.dedefleet.tour.change-status.POST';
    }

    public function getDescription(): string
    {
        return 'Tourenplanung Step 4 — POST /Tour/ChangeStatus: ändert den Status einer Tour. 0=Planning, 1=Released (freigegeben), 2=Completed.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tourGuid' => ['type' => 'string', 'description' => 'GUID der Tour.'],
                'status' => ['type' => 'integer', 'description' => 'Neuer Status: 0=Planning, 1=Released, 2=Completed.', 'enum' => [0, 1, 2]],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
                ],
            ],
            'required' => ['tourGuid', 'status'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['tourGuid', 'status'])) {
            return $guard;
        }

        $payload = [];
        foreach (['tourGuid', 'status'] as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $payload[$k] = $arguments[$k];
            }
        }

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->changeTourStatus($context->user, $payload);

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
            'tags' => ['dedefleet', 'tourenplanung', 'step-4'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
