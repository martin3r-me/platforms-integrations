<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\HetznerApiException;
use Platform\Integrations\Services\HetznerApiService;
use Platform\Integrations\Tools\Hetzner\Concerns\GuardsArguments;

/**
 * Fragt den Status asynchroner Hetzner-Aktionen ab.
 *
 * Jeder verändernde Aufruf der Hetzner-API liefert eine action-ID. Dieses Tool
 * ist der Weg, deren Fortschritt zu verfolgen — optional mit Warten, bis die
 * Aktion abgeschlossen ist.
 */
class ActionsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.hetzner.actions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /actions bzw. GET /actions/{id} — Fragt asynchrone Hetzner-Aktionen ab. '
            . 'Mit "id" wird eine einzelne Aktion abgerufen, ohne "id" die Liste der Aktionen im Projekt. '
            . 'Der status ist running, success oder error. '
            . 'Mit "wait": true wird solange gepollt, bis die Aktion nicht mehr läuft (Obergrenze über '
            . '"max_wait" in Sekunden). Jeder verändernde Aufruf der Hetzner-API liefert eine action-ID, '
            . 'die hier eingesetzt wird.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Aktions-ID. Ohne Angabe wird die Liste der Aktionen zurückgegeben.',
                ],
                'wait' => [
                    'type' => 'boolean',
                    'description' => 'Nur mit "id": wartet, bis die Aktion nicht mehr den Status "running" hat.',
                ],
                'max_wait' => [
                    'type' => 'integer',
                    'description' => 'Maximale Wartezeit in Sekunden bei "wait" (Standard aus der Konfiguration, üblicherweise 60).',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Nur ohne "id": filtert die Liste nach Status (running, success, error).',
                ],
                'sort' => [
                    'type' => 'string',
                    'description' => 'Nur ohne "id": Sortierung, z.B. "started:desc".',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Seitennummer (ab 1).',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Einträge pro Seite (Standard 25, Maximum 50).',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
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

        $id = $arguments['id'] ?? null;

        try {
            $service = app(HetznerApiService::class)->forConnection($arguments['connection_id'] ?? null);

            if ($id === null || $id === '') {
                return ToolResult::success($service->listActions($context->user, $this->listQuery($arguments)));
            }

            if (($arguments['wait'] ?? false) === true) {
                $maxWait = (int) ($arguments['max_wait'] ?? config('integrations.hetzner.action.max_wait', 60));
                $interval = (int) config('integrations.hetzner.action.poll_interval', 2);

                $result = $service->waitForAction($context->user, (string) $id, $maxWait, $interval);
                $status = $result['action']['status'] ?? null;

                return ToolResult::success([
                    'action' => $result['action'] ?? $result,
                    'abgeschlossen' => $status !== 'running',
                    'hinweis' => $status === 'running'
                        ? "Die Aktion läuft nach {$maxWait} Sekunden noch. Erneut abfragen."
                        : null,
                ]);
            }

            return ToolResult::success($service->getAction($context->user, (string) $id));
        } catch (HetznerApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'HETZNER_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['hetzner', 'cloud', 'actions', 'status', 'async'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
