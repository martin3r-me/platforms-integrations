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
 * Ruft CPU-, Festplatten- und Netzwerk-Metriken eines Servers ab.
 *
 * Komfort: Ohne "start"/"end" wird automatisch das letzte Zeitfenster
 * (Standard 1 Stunde) gesetzt — die API verlangt beide Werte im RFC3339-Format.
 */
class GetServerMetricsTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public const TYPES = ['cpu', 'disk', 'network'];

    public function getName(): string
    {
        return 'integrations.hetzner.server.metrics.GET';
    }

    public function getDescription(): string
    {
        return 'GET /servers/{id}/metrics — Ruft Metriken eines Hetzner-Servers ab: cpu (Auslastung in Prozent), '
            . 'disk (IOPS und Durchsatz) und network (Pakete und Bandbreite). '
            . 'Ohne Zeitangabe wird automatisch die letzte Stunde ausgewertet; alternativ "hours" setzen oder '
            . '"start" und "end" im RFC3339-Format angeben. Erster Anlaufpunkt bei Performance-Fragen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Server-ID.',
                ],
                'type' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => self::TYPES],
                    'description' => 'Metrik-Typen: cpu, disk, network. Ohne Angabe werden alle drei abgefragt.',
                ],
                'hours' => [
                    'type' => 'integer',
                    'description' => 'Komfort: Zeitfenster in Stunden bis jetzt (Standard 1). Wird ignoriert, wenn start und end gesetzt sind.',
                ],
                'start' => [
                    'type' => 'string',
                    'description' => 'Beginn des Zeitraums im RFC3339-Format, z.B. "2026-09-15T08:00:00Z".',
                ],
                'end' => [
                    'type' => 'string',
                    'description' => 'Ende des Zeitraums im RFC3339-Format.',
                ],
                'step' => [
                    'type' => 'integer',
                    'description' => 'Auflösung der Messpunkte in Sekunden.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
                ],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['id'])) {
            return $error;
        }

        $types = $arguments['type'] ?? self::TYPES;
        $types = is_array($types) ? $types : [$types];
        $types = array_values(array_filter(array_map(
            static fn ($t) => mb_strtolower(trim((string) $t)),
            $types
        )));

        if (!$types) {
            $types = self::TYPES;
        }

        foreach ($types as $type) {
            if (!in_array($type, self::TYPES, true)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    "Unbekannter Metrik-Typ \"{$type}\". Erlaubt: " . implode(', ', self::TYPES) . '.'
                );
            }
        }

        $start = trim((string) ($arguments['start'] ?? ''));
        $end = trim((string) ($arguments['end'] ?? ''));

        if ($start === '' || $end === '') {
            $hours = max(1, (int) ($arguments['hours'] ?? 1));
            $end = now()->utc()->format('Y-m-d\TH:i:s\Z');
            $start = now()->utc()->subHours($hours)->format('Y-m-d\TH:i:s\Z');
        }

        $query = [
            'type' => $types,
            'start' => $start,
            'end' => $end,
        ];

        if (!empty($arguments['step'])) {
            $query['step'] = (int) $arguments['step'];
        }

        try {
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->getServerMetrics($context->user, (string) $arguments['id'], $query);

            return ToolResult::success([
                'server_id' => (int) $arguments['id'],
                'zeitraum' => ['start' => $start, 'end' => $end],
                'typen' => $types,
                'metrics' => $result['metrics'] ?? $result,
            ]);
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
            'tags' => ['hetzner', 'cloud', 'server', 'metrics', 'monitoring'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
