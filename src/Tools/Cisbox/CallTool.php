<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Services\CisboxResource;
use Platform\Integrations\Tools\Cisbox\Concerns\GuardsArguments;

/**
 * Generischer Zugriff auf alle Endpunkte der cisbox Invoice API v1.
 */
class CallTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.cisbox.call';
    }

    public function getDescription(): string
    {
        return 'Ruft einen beliebigen der 27 Endpunkte der cisbox Invoice API v1 auf. Pfad und Methode '
            . 'werden gegen den Endpunkt-Katalog geprüft, Pfad-Platzhalter wie {barcodeNo} aus '
            . '"path_params" ersetzt. Gültige Operationen liefert integrations.cisbox.overview. '
            . 'Die API ist vollständig lesend — es gibt keinen verändernden Aufruf.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Pfad aus dem Katalog, inklusive Platzhalter — z.B. "/invoices" '
                        . 'oder "/creditors/{creditorNo}/items".',
                ],
                'path_params' => [
                    'type' => 'object',
                    'description' => 'Werte für die Platzhalter im Pfad, z.B. {"barcodeNo": 4711}.',
                ],
                'query' => [
                    'type' => 'object',
                    'description' => 'Query-Parameter, z.B. {"page": 2, "itemsPerPage": 500, "creditorNo": 7893}. '
                        . 'Erlaubte Namen nennt der Katalog je Operation. Mehrfach-Parameter wie "status" '
                        . 'als Array übergeben.',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET'],
                    'description' => 'Optional — die API kennt ausschließlich GET.',
                ],
                'all_pages' => [
                    'type' => 'boolean',
                    'description' => 'Bei paginierten Listen alle Seiten zusammenführen (bis 50 Seiten).',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte). '
                        . 'Reduziert die Antwortgröße erheblich.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten cisbox-Verbindung.',
                ],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['path'])) {
            return $error;
        }

        $method = mb_strtoupper(trim((string) ($arguments['method'] ?? 'GET')));
        $path = '/' . ltrim(trim((string) $arguments['path']), '/');

        if ($method !== 'GET') {
            return ToolResult::error(
                'METHOD_NOT_ALLOWED',
                "Die cisbox Invoice API ist lesend — \"{$method}\" gibt es nicht."
            );
        }

        if ($error = $this->guardOperation($method, $path)) {
            return $error;
        }

        $operation = CisboxResource::operation($method, $path);
        $pathParams = is_array($arguments['path_params'] ?? null) ? $arguments['path_params'] : [];
        $query = is_array($arguments['query'] ?? null) ? $arguments['query'] : [];

        // Unbekannte Query-Parameter melden, statt sie stillschweigend
        // mitzuschicken: cisbox ignoriert sie, und das Ergebnis sähe wie ein
        // gefiltertes aus, obwohl der Filter nie gewirkt hat.
        $unknown = array_values(array_diff(array_keys($query), $operation['query']));
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

        $missing = array_values(array_diff($operation['required'], array_keys($query)));
        if ($missing !== []) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('Pflicht-Query-Parameter fehlen: %s.', implode(', ', $missing))
            );
        }

        if ($error = $this->guardSearch($query)) {
            return $error;
        }

        if ($error = $this->guardEnums(CisboxResource::key($method, $path), $query)) {
            return $error;
        }

        if (($arguments['all_pages'] ?? false) && !$operation['paginated']) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('"%s %s" ist nicht paginiert — "all_pages" greift hier nicht.', $method, $path)
            );
        }

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $this->runList($service, $context->user, $path, $pathParams, $query, $arguments);

            // Bei Einzelabrufen den einen Datensatz auspacken, statt ein Array
            // mit einem Element zurückzugeben.
            if ($operation['single'] && empty($arguments['fields'])) {
                $single = $service->single($result);

                if ($single === null) {
                    return ToolResult::error('NOT_FOUND', sprintf('"%s %s" lieferte keinen Datensatz.', $method, $path));
                }

                return ToolResult::success([
                    'data' => $single,
                    'request_id' => $result['request_id'] ?? null,
                ]);
            }

            return ToolResult::success($result);
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'invoice', 'rechnungen', 'generic', 'api'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
