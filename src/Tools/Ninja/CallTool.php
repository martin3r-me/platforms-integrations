<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Services\NinjaApiService;
use Platform\Integrations\Services\NinjaResource;
use Platform\Integrations\Tools\Ninja\Concerns\GuardsArguments;

/**
 * Generischer Zugriff auf alle Endpunkte der NinjaOne Public API 2.0.
 */
class CallTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.ninja.call';
    }

    public function getDescription(): string
    {
        return 'Ruft einen beliebigen der 315 Endpunkte der NinjaOne Public API 2.0 auf. '
            . 'Pfad und Methode werden gegen den Endpunkt-Katalog geprüft, Pfad-Platzhalter wie {id} '
            . 'aus "path_params" ersetzt. Gültige Operationen liefert integrations.ninja.overview. '
            . 'Verändernde Aufrufe (POST/PUT/PATCH/DELETE) verlangen "confirm": true — sie wirken auf '
            . 'echte Endgeräte und lassen sich nicht zurücknehmen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'description' => 'HTTP-Methode der Operation.',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Pfad aus dem Katalog, inklusive Platzhalter — z.B. "/v2/devices" '
                        . 'oder "/v2/device/{id}/alerts".',
                ],
                'path_params' => [
                    'type' => 'object',
                    'description' => 'Werte für die Platzhalter im Pfad, z.B. {"id": 42}.',
                ],
                'query' => [
                    'type' => 'object',
                    'description' => 'Query-Parameter, z.B. {"pageSize": 100, "df": "status eq APPROVED"}. '
                        . 'Erlaubte Namen nennt der Katalog je Operation.',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'Request-Body für POST/PUT/PATCH.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Pflicht bei verändernden Methoden. Ohne true wird der Aufruf abgelehnt.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, '
                        . 'z.B. "system.name"). Reduziert die Antwortgröße erheblich.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten NinjaOne-Verbindung.',
                ],
            ],
            'required' => ['method', 'path'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['method', 'path'])) {
            return $error;
        }

        $method = mb_strtoupper(trim((string) $arguments['method']));
        $path = '/' . ltrim(trim((string) $arguments['path']), '/');

        if ($error = $this->guardOperation($method, $path)) {
            return $error;
        }

        $operation = NinjaResource::operation($method, $path);

        if ($operation['write'] && ($error = $this->guardConfirmed($arguments, "{$method} {$path}"))) {
            return $error;
        }

        $pathParams = is_array($arguments['path_params'] ?? null) ? $arguments['path_params'] : [];
        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];
        $body = is_array($arguments['body'] ?? null) ? $arguments['body'] : [];

        // Unbekannte Query-Parameter melden, statt sie stillschweigend
        // mitzuschicken: NinjaOne ignoriert sie, und das Ergebnis sähe wie ein
        // gefiltertes aus, obwohl der Filter nie gewirkt hat.
        $unknown = array_diff(array_keys($query), $operation['query']);
        if ($unknown !== []) {
            return ToolResult::error(
                'UNKNOWN_QUERY_PARAMETER',
                sprintf(
                    'Unbekannte(r) Query-Parameter für "%s %s": %s.%s',
                    $method,
                    $path,
                    implode(', ', $unknown),
                    $operation['query']
                        ? ' Erlaubt sind: ' . implode(', ', $operation['query']) . '.'
                        : ' Diese Operation kennt keine Query-Parameter.'
                )
            );
        }

        $missing = array_diff($operation['required'], array_keys($query));
        if ($missing !== []) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('Pflicht-Query-Parameter fehlen: %s.', implode(', ', $missing))
            );
        }

        try {
            $result = app(NinjaApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, $method, $path, $pathParams, $query, $body);

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = NinjaApiService::projectFields($result, $arguments['fields']);
            }

            return ToolResult::success($result);
        } catch (NinjaApiException $e) {
            return ToolResult::error($e->getNinjaErrorCode() ?? 'NINJA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'generic', 'api'],
            // Das Tool kann verändernde Endpunkte ansprechen — jeder davon
            // verlangt aber ausdrücklich confirm=true.
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
