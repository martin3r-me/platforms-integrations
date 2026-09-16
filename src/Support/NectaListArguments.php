<?php

namespace Platform\Integrations\Support;

use Platform\Integrations\Exceptions\NectaApiException;
use Platform\Integrations\Services\NectaResource;

/**
 * Normalisiert und validiert die Argumente der Raw-API-Lese-Tools.
 *
 * Hintergrund: Die beiden necta-APIs haben unterschiedliche Vertraege, und die
 * MCP-Tools haben diesen Unterschied ungefiltert nach aussen gegeben:
 *
 *   Raw-API (/rawapi)  → "pageNumber" + Filter im Objekt "filters"
 *   API v1  (/api/v1)  → "page"       + Filter als Top-Level-Argumente
 *
 * Ein Aufrufer, der die v1-Schreibweise auf ein Raw-Tool anwendet, bekam bisher
 * KEINEN Fehler: "page" landete in keinem Parameter (→ immer Seite 1) und
 * Top-Level-Filter wurden stillschweigend verworfen (→ immer ungefilterte
 * Liste). Das Ergebnis sah aus wie ein kaputtes Pagination-/Filter-Verhalten
 * von necta, war aber ein stiller Parameter-Verlust auf unserer Seite.
 *
 * Diese Klasse macht beides unmoeglich:
 *  - "page" wird als Alias fuer "pageNumber" akzeptiert,
 *  - Filter werden top-level UND im "filters"-Objekt entgegengenommen,
 *  - unbekannte Filter brechen mit einer Meldung ab, die sagt, was erlaubt ist
 *    und bei welcher Ressource der Filter stattdessen existiert.
 *
 * Lieber ein harter Fehler als ein plausibel aussehendes, aber ungefiltertes
 * Ergebnis — letzteres wird sonst als Fakt weiterverarbeitet.
 */
final class NectaListArguments
{
    /** Argumente des Tool-Schemas, die keine necta-Query-Filter sind. */
    private const RESERVED = [
        'resource',
        'pageNumber',
        'page',
        'pageSize',
        'filters',
        'fields',
        'connection_id',
    ];

    public const DEFAULT_PAGE_SIZE = 50;

    /**
     * @param array<string, mixed> $arguments Rohe Tool-Argumente
     * @return array{pageNumber: int, pageSize: int, filters: array<string, mixed>}
     *
     * @throws NectaApiException bei unbekannten Filtern
     */
    public static function normalize(string $resource, array $arguments, int $defaultPageSize = self::DEFAULT_PAGE_SIZE): array
    {
        // "page" als Alias — die v1-Tools heissen so, der Reflex ist vorhersehbar.
        if (array_key_exists('page', $arguments) && array_key_exists('pageNumber', $arguments)) {
            throw new NectaApiException(
                'page und pageNumber sind gleichzeitig gesetzt — page ist nur ein Alias fuer '
                . 'pageNumber. Bitte nur einen der beiden Parameter angeben.',
                400,
                'AMBIGUOUS_PAGE_PARAMETER'
            );
        }

        $pageNumber = $arguments['pageNumber'] ?? $arguments['page'] ?? 1;
        $pageSize = $arguments['pageSize'] ?? $defaultPageSize;

        $filters = is_array($arguments['filters'] ?? null) ? $arguments['filters'] : [];

        // Top-Level-Filter einsammeln (v1-Schreibweise). Explizite "filters"-
        // Eintraege gewinnen, falls beides gesetzt ist.
        foreach ($arguments as $key => $value) {
            if (in_array($key, self::RESERVED, true) || $value === null) {
                continue;
            }
            if (array_key_exists($key, $filters)) {
                if ($filters[$key] !== $value) {
                    throw new NectaApiException(
                        sprintf(
                            'Filter "%s" ist doppelt und widerspruechlich gesetzt: top-level und in '
                            . '"filters". Bitte nur an einer Stelle angeben.',
                            $key
                        ),
                        400,
                        'AMBIGUOUS_FILTER'
                    );
                }

                continue;
            }

            $filters[$key] = $value;
        }

        self::assertKnownFilters($resource, $filters);

        return [
            'pageNumber' => max(1, (int) $pageNumber),
            'pageSize' => max(1, (int) $pageSize),
            'filters' => $filters,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @throws NectaApiException
     */
    private static function assertKnownFilters(string $resource, array $filters): void
    {
        $allowed = NectaResource::filters($resource);
        $unknown = array_values(array_diff(array_keys($filters), $allowed));

        if ($unknown === []) {
            return;
        }

        $hints = [];
        foreach ($unknown as $filter) {
            $elsewhere = self::resourcesOfferingFilter($filter);
            if ($elsewhere !== []) {
                $hints[] = sprintf(
                    '"%s" gibt es stattdessen bei: %s',
                    $filter,
                    implode(', ', array_slice($elsewhere, 0, 5)) . (count($elsewhere) > 5 ? ', …' : '')
                );
            }
        }

        $message = sprintf(
            'Unbekannte(r) Filter fuer Ressource "%s": %s. %s',
            $resource,
            implode(', ', $unknown),
            $allowed === []
                ? 'Diese Ressource hat keine dokumentierten Filter.'
                : 'Erlaubt sind: ' . implode(', ', $allowed) . '.'
        );

        if ($hints !== []) {
            $message .= ' ' . implode(' ', $hints) . '.';
        }

        // necta ignoriert unbekannte Query-Parameter kommentarlos und liefert
        // die vollstaendige Liste — das saehe wie ein funktionierender, aber
        // wirkungsloser Filter aus. Deshalb hier abbrechen.
        throw new NectaApiException($message, 400, 'UNKNOWN_FILTER');
    }

    /**
     * Ressourcen, die diesen Filter kennen — macht aus einem Fehlschlag einen
     * brauchbaren Hinweis auf die richtige Ressource.
     *
     * @return array<int, string>
     */
    public static function resourcesOfferingFilter(string $filter): array
    {
        $matches = [];
        foreach (NectaResource::all() as $slug) {
            if (in_array($filter, NectaResource::filters($slug), true)) {
                $matches[] = $slug;
            }
        }

        return $matches;
    }
}
