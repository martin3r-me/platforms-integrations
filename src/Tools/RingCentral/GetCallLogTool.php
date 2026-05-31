<?php

namespace Platform\Integrations\Tools\RingCentral;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\RingCentralApiService;
use Platform\Integrations\Exceptions\RingCentralApiException;

class GetCallLogTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.ringcentral.call_log.GET';
    }

    public function getDescription(): string
    {
        return 'GET /call-log - Ruft das Call Log (Anrufhistorie) des RingCentral-Accounts ab. Unterstützt Filter nach Zeitraum, Typ und Richtung.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'ID der RingCentral-Verbindung.',
                ],
                'date_from' => [
                    'type' => 'string',
                    'description' => 'Start-Datum (ISO 8601, z.B. 2026-01-01T00:00:00Z).',
                ],
                'date_to' => [
                    'type' => 'string',
                    'description' => 'End-Datum (ISO 8601, z.B. 2026-12-31T23:59:59Z).',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => 'Anruftyp: Voice, Fax.',
                    'enum' => ['Voice', 'Fax'],
                ],
                'direction' => [
                    'type' => 'string',
                    'description' => 'Anrufrichtung: Inbound, Outbound.',
                    'enum' => ['Inbound', 'Outbound'],
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Seitennummer (1-basiert).',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Einträge pro Seite (max 250).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $service = app(RingCentralApiService::class)->forConnection($arguments['connection_id'] ?? null);

            $filters = [];
            if (!empty($arguments['date_from'])) {
                $filters['dateFrom'] = $arguments['date_from'];
            }
            if (!empty($arguments['date_to'])) {
                $filters['dateTo'] = $arguments['date_to'];
            }
            if (!empty($arguments['type'])) {
                $filters['type'] = $arguments['type'];
            }
            if (!empty($arguments['direction'])) {
                $filters['direction'] = $arguments['direction'];
            }
            if (!empty($arguments['page'])) {
                $filters['page'] = $arguments['page'];
            }
            if (!empty($arguments['per_page'])) {
                $filters['perPage'] = $arguments['per_page'];
            }

            $result = $service->getCallLog($context->user, $filters);

            return ToolResult::success($result);
        } catch (RingCentralApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'RC_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ringcentral', 'telefonie', 'call-log', 'anrufe', 'historie'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
