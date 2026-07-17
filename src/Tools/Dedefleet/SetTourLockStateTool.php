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
 * Tourenplanung Step 4 — POST /Tour/SetLockState: Touren sperren/entsperren
 * (verhindert Verschieben während der Planung). Mehrere Touren pro Request.
 */
class SetTourLockStateTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.dedefleet.tour.lock.POST';
    }

    public function getDescription(): string
    {
        return 'Tourenplanung Step 4 — POST /Tour/SetLockState: setzt den Sperr-Status einer oder mehrerer Touren '
            . '(z.B. um freigegebene Touren gegen Umplanung zu schützen). `tours` ist eine Liste von '
            . '{ tourGuid, lock, reason }.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tours' => [
                    'type' => 'array',
                    'description' => 'Liste der Sperr-Updates.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'tourGuid' => ['type' => 'string', 'description' => 'GUID der Tour.'],
                            'lock' => ['type' => 'integer', 'description' => 'Sperrwert (z.B. 1 = gesperrt, 0 = frei).'],
                            'reason' => ['type' => 'string', 'description' => 'Optionaler Grund für die Änderung.'],
                        ],
                        'required' => ['tourGuid', 'lock'],
                    ],
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen DedeFleet-Connection.',
                ],
            ],
            'required' => ['tours'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($guard = $this->guardRequired($arguments, ['tours'])) {
            return $guard;
        }

        if (!is_array($arguments['tours'])) {
            return ToolResult::error('VALIDATION_ERROR', '"tours" muss eine Liste von { tourGuid, lock, reason } sein.');
        }

        try {
            $svc = app(DedefleetApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->setTourLockState($context->user, ['tours' => $arguments['tours']]);

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
