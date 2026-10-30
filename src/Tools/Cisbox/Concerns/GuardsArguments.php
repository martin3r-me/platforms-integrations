<?php

namespace Platform\Integrations\Tools\Cisbox\Concerns;

use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Services\CisboxResource;

/**
 * Gemeinsame Argument-Prüfungen der cisbox-Tools.
 *
 * Die API ist durchgehend lesend, deshalb gibt es hier keine
 * Bestätigungspflicht. Geprüft wird stattdessen alles, was cisbox sonst
 * stillschweigend oder mit einem nackten 422 quittieren würde: zu kurze
 * Suchbegriffe, unbekannte Enum-Werte und Parameter, die es am angefragten
 * Endpunkt gar nicht gibt.
 */
trait GuardsArguments
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<int, string>   $keys
     */
    protected function guardRequired(array $arguments, array $keys): ?ToolResult
    {
        foreach ($keys as $key) {
            $value = $arguments[$key] ?? null;

            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                return ToolResult::error('VALIDATION_ERROR', "Pflichtparameter \"{$key}\" fehlt.");
            }
        }

        return null;
    }

    /**
     * cisbox verlangt für die Volltextsuche mindestens vier Zeichen und
     * antwortet sonst mit 422. Das hier vorab zu sagen erspart den Umweg.
     *
     * @param array<string, mixed> $arguments
     */
    protected function guardSearch(array $arguments): ?ToolResult
    {
        $q = trim((string) ($arguments['q'] ?? ''));

        if ($q !== '' && mb_strlen($q) < CisboxApiService::MIN_SEARCH_LENGTH) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf(
                    'Der Suchbegriff "q" braucht mindestens %d Zeichen — cisbox lehnt kürzere ab.',
                    CisboxApiService::MIN_SEARCH_LENGTH
                )
            );
        }

        return null;
    }

    /**
     * Prüft Enum-Werte gegen den Katalog, bevor sie zur API gehen.
     *
     * @param array<string, mixed> $query
     */
    protected function guardEnums(string $operationKey, array $query): ?ToolResult
    {
        $operation = CisboxResource::OPERATIONS[$operationKey] ?? null;

        if (!$operation) {
            return null;
        }

        foreach ($operation['enums'] as $parameter => $allowed) {
            if (!isset($query[$parameter])) {
                continue;
            }

            $values = is_array($query[$parameter]) ? $query[$parameter] : [$query[$parameter]];
            $invalid = array_values(array_diff(array_map('strval', $values), $allowed));

            if ($invalid !== []) {
                return ToolResult::error(
                    'INVALID_ENUM_VALUE',
                    sprintf(
                        'Ungültige(r) Wert(e) für "%s": %s. Erlaubt sind: %s.',
                        $parameter,
                        implode(', ', $invalid),
                        implode(', ', $allowed)
                    )
                );
            }
        }

        return null;
    }

    /**
     * Prüft, ob die Operation im Katalog steht, und liefert sonst einen Fehler
     * mit passenden Vorschlägen — besser als ein nacktes 404 der API.
     */
    protected function guardOperation(string $method, string $path): ?ToolResult
    {
        if (CisboxResource::exists($method, $path)) {
            return null;
        }

        $needle = trim(preg_replace('#\{[^}]*\}#', '', $path) ?? $path, '/');
        $needle = $needle !== '' ? (explode('/', $needle)[0]) : $path;
        $hits = CisboxResource::search($needle, 8);

        $message = sprintf('Unbekannte Operation "%s %s".', mb_strtoupper($method), $path);
        $message .= $hits
            ? ' Meintest du: ' . implode(' · ', $hits) . '?'
            : ' Verfügbare Endpunkte siehe integrations.cisbox.overview.';

        return ToolResult::error('UNKNOWN_OPERATION', $message);
    }

    /**
     * Baut die Query aus Paginierung, Volltextsuche und den erlaubten Filtern
     * der jeweiligen Operation. Nicht gesetzte Werte bleiben weg, damit die
     * Standardwerte der API gelten.
     *
     * @param array<string, mixed> $arguments
     * @param array<int, string>   $extra Weitere Query-Parameter dieses Endpunkts
     * @return array<string, mixed>
     */
    protected function listQuery(array $arguments, array $extra = []): array
    {
        $query = [];

        if (!empty($arguments['page'])) {
            $query['page'] = max(1, (int) $arguments['page']);
        }

        if (!empty($arguments['itemsPerPage'])) {
            $query['itemsPerPage'] = min(
                max(1, (int) $arguments['itemsPerPage']),
                CisboxApiService::MAX_ITEMS_PER_PAGE
            );
        }

        foreach (array_merge(['q', 'updatedSince'], $extra) as $key) {
            if (!array_key_exists($key, $arguments)) {
                continue;
            }

            $value = $arguments[$key];

            // false ist ein gültiger Filterwert (cancelled=false), "" und []
            // sind es nicht.
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $query[$key] = $value;
        }

        return $query;
    }

    /**
     * Gemeinsame Schema-Bausteine für Paginierung und Volltextsuche.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function paginationSchema(): array
    {
        return [
            'page' => [
                'type' => 'integer',
                'description' => 'Seitennummer, beginnend bei 1 (Standard: 1).',
                'minimum' => 1,
            ],
            'itemsPerPage' => [
                'type' => 'integer',
                'description' => 'Einträge pro Seite (Standard: 200, Maximum: 2000).',
                'minimum' => 1,
                'maximum' => CisboxApiService::MAX_ITEMS_PER_PAGE,
            ],
            'q' => [
                'type' => 'string',
                'description' => 'Volltextsuche, mindestens 4 Zeichen.',
                'minLength' => CisboxApiService::MIN_SEARCH_LENGTH,
            ],
            'all_pages' => [
                'type' => 'boolean',
                'description' => 'Alle Seiten zusammenführen (bis 50 Seiten). Bei großen Beständen mit "fields" kombinieren.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Nur diese Felder zurückgeben (Dot-Notation für verschachtelte). '
                    . 'Verfügbare Felder nennt integrations.cisbox.overview.',
            ],
            'connection_id' => [
                'type' => 'integer',
                'description' => 'Optional: ID einer bestimmten cisbox-Verbindung.',
            ],
        ];
    }

    /**
     * Führt eine Listen-Operation aus — seitenweise oder über alle Seiten —
     * und wendet anschließend die Feld-Projektion an.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $pathParams
     * @param array<string, mixed> $query
     * @return array<mixed>
     *
     * @throws \Platform\Integrations\Exceptions\CisboxApiException
     */
    protected function runList(
        CisboxApiService $service,
        ?\Platform\Core\Models\User $user,
        string $path,
        array $pathParams,
        array $query,
        array $arguments
    ): array {
        $result = ($arguments['all_pages'] ?? false)
            ? $service->fetchAll(
                $user,
                $path,
                $pathParams,
                $query,
                (int) ($arguments['itemsPerPage'] ?? 500)
            )
            : $service->call($user, 'GET', $path, $pathParams, $query);

        if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
            $projected = CisboxApiService::projectFields($result, $arguments['fields']);
            $result = is_array($projected) ? $projected : $result;
        }

        return $result;
    }
}
