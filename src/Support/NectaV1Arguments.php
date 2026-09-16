<?php

namespace Platform\Integrations\Support;

use Platform\Integrations\Exceptions\NectaApiException;

/**
 * Baut die Query-Parameter der necta.one v1-Tools aus den Tool-Argumenten.
 *
 * Bisher wurden nur die in QUERY_KEYS deklarierten Argumente uebernommen und
 * alles andere kommentarlos verworfen. Ein Tippfehler oder ein Filtername aus
 * einer anderen Ressource fuehrte damit nicht zu einem Fehler, sondern zu einer
 * vollstaendigen, ungefilterten Liste — die dann als gefiltertes Ergebnis
 * weiterverarbeitet wurde.
 *
 * Gegenstueck fuer die Raw-API: {@see NectaListArguments}. Beide akzeptieren
 * jeweils die Paginierungs-Schreibweise der anderen API als Alias, damit der
 * Wechsel zwischen den Tool-Familien nicht still ins Leere laeuft:
 *
 *   Raw-API (/rawapi) → pageNumber (+ Alias "page")
 *   API v1  (/api/v1) → page       (+ Alias "pageNumber")
 */
final class NectaV1Arguments
{
    /** Tool-Argumente, die keine necta-Query-Parameter sind. */
    private const RESERVED = ['data', 'fields', 'connection_id'];

    /**
     * @param array<string, mixed> $arguments  Rohe Tool-Argumente
     * @param array<int, string>   $queryKeys  Deklarierte Query-Parameter des Endpunkts
     * @param array<int, string>   $pathParams Pfad-Platzhalter des Endpunkts (z.B. "id")
     * @return array<string, mixed>
     *
     * @throws NectaApiException bei unbekannten Argumenten
     */
    public static function query(array $arguments, array $queryKeys, array $pathParams = []): array
    {
        // "pageNumber" ist die Schreibweise der Raw-API-Tools — als Alias
        // durchreichen, statt sie als unbekanntes Argument abzulehnen.
        if (in_array('page', $queryKeys, true) && array_key_exists('pageNumber', $arguments)) {
            if (array_key_exists('page', $arguments)) {
                throw new NectaApiException(
                    'page und pageNumber sind gleichzeitig gesetzt — pageNumber ist nur ein Alias '
                    . 'fuer page. Bitte nur einen der beiden Parameter angeben.',
                    400,
                    'AMBIGUOUS_PAGE_PARAMETER'
                );
            }

            $arguments['page'] = $arguments['pageNumber'];
            unset($arguments['pageNumber']);
        }

        $query = [];
        foreach ($queryKeys as $k) {
            if (array_key_exists($k, $arguments) && $arguments[$k] !== null) {
                $query[$k] = $arguments[$k];
            }
        }

        self::assertNoUnknownArguments($arguments, $queryKeys, $pathParams);

        return $query;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<int, string>   $queryKeys
     * @param array<int, string>   $pathParams
     *
     * @throws NectaApiException
     */
    private static function assertNoUnknownArguments(array $arguments, array $queryKeys, array $pathParams): void
    {
        $known = array_merge($queryKeys, $pathParams, self::RESERVED);
        $unknown = array_values(array_diff(array_keys($arguments), $known));

        if ($unknown === []) {
            return;
        }

        throw new NectaApiException(
            sprintf(
                'Unbekannte(s) Argument(e): %s. Erlaubte Query-Parameter dieses Endpunkts: %s.%s',
                implode(', ', $unknown),
                $queryKeys === [] ? '(keine)' : implode(', ', $queryKeys),
                $pathParams === [] ? '' : ' Pfad-Parameter: ' . implode(', ', $pathParams) . '.'
            ),
            400,
            'UNKNOWN_PARAMETER'
        );
    }
}
