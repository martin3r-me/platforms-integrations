<?php

namespace Platform\Integrations\Support;

/**
 * Client-seitige Feld-Projektion für API-Antworten.
 *
 * Reduziert eine Response auf gewünschte Felder (Dot-Notation für verschachtelte
 * Felder, z.B. "customer.customerNumber"). Erkennt Listen automatisch — am Root
 * oder unter result/items/data/content — und projiziert deren Einträge, behält
 * aber Paginierungs-Metadaten (page/totalCount/hasMore/…) bei. Ein Einzelobjekt
 * wird direkt projiziert.
 *
 * Erkennt zusätzlich ressourcenspezifische Hüllen: Hetzner Cloud liefert Listen
 * unter dem Plural-Namen der Ressource ("servers", "volumes"), Einzelressourcen
 * unter dem Singular; Laravel Forge nutzt durchgehend "data".
 *
 * Wird integrationsübergreifend genutzt (necta Raw + v1, DedeFleet, Laravel Forge,
 * Hetzner Cloud), um riesige Antworten (Bestellungen/Rechnungen/Artikel mit 100+
 * Feldern) zu verschlanken.
 */
final class FieldProjection
{
    /** Generische Schlüssel, unter denen APIs ihre Listen ausliefern. */
    private const KNOWN_LIST_KEYS = ['result', 'items', 'data', 'content'];

    /** Schlüssel, die zur Hülle gehören und nie die Nutzlast sind. */
    private const ENVELOPE_KEYS = ['meta', 'pagination', 'links', 'status', 'success'];

    /**
     * @param array<int, string> $fields
     */
    public static function apply(mixed $response, array $fields): mixed
    {
        $paths = array_values(array_filter(
            array_map(static fn ($f) => trim((string) $f), $fields),
            static fn ($f) => $f !== ''
        ));

        if (!$paths || !is_array($response)) {
            return $response;
        }

        if (array_is_list($response)) {
            return array_map(
                static fn ($it) => is_array($it) ? self::pickPaths($it, $paths) : $it,
                $response
            );
        }

        foreach (self::KNOWN_LIST_KEYS as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && array_is_list($response[$key])) {
                return self::projectInto($response, $key, $paths);
            }
        }

        // Ressourcenspezifische Hülle: Hetzner Cloud liefert die Liste unter dem
        // Plural-Namen der Ressource ("servers", "volumes", "zones", ...), nicht
        // unter einem generischen Schlüssel. Gibt es genau einen Listen-Schlüssel
        // neben den Metadaten, ist das die Nutzlast.
        $listKeys = [];
        foreach ($response as $key => $value) {
            if (in_array($key, self::ENVELOPE_KEYS, true)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                $listKeys[] = $key;
            }
        }

        if (count($listKeys) === 1) {
            return self::projectInto($response, $listKeys[0], $paths);
        }

        // Einzelressource in einer Hülle: Forge liefert { "data": {...} },
        // Hetzner { "server": {...} }. Wenn keines der gewünschten Felder auf
        // oberster Ebene existiert, wird eine Ebene tiefer projiziert — sonst
        // käme ein leeres Ergebnis zurück.
        if (!self::hasAnyPath($response, $paths)) {
            $wrapperKeys = [];
            foreach ($response as $key => $value) {
                if (in_array($key, self::ENVELOPE_KEYS, true)) {
                    continue;
                }

                if (is_array($value) && !array_is_list($value)) {
                    $wrapperKeys[] = $key;
                }
            }

            if (count($wrapperKeys) === 1) {
                $response[$wrapperKeys[0]] = self::pickPaths($response[$wrapperKeys[0]], $paths);

                return $response;
            }
        }

        return self::pickPaths($response, $paths);
    }

    /**
     * Projiziert die Einträge einer Liste unter $key und behält die Hülle bei.
     *
     * @param array<string, mixed> $response
     * @param array<int, string>   $paths
     * @return array<string, mixed>
     */
    private static function projectInto(array $response, string $key, array $paths): array
    {
        $response[$key] = array_map(
            static fn ($it) => is_array($it) ? self::pickPaths($it, $paths) : $it,
            $response[$key]
        );

        return $response;
    }

    /**
     * Prüft, ob mindestens eines der gewünschten Felder auf oberster Ebene liegt.
     *
     * @param array<string, mixed> $response
     * @param array<int, string>   $paths
     */
    private static function hasAnyPath(array $response, array $paths): bool
    {
        foreach ($paths as $path) {
            $first = explode('.', $path)[0];

            if (array_key_exists($first, $response)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, string> $paths
     * @return array<string, mixed>
     */
    private static function pickPaths(array $item, array $paths): array
    {
        $out = [];
        foreach ($paths as $path) {
            $segs = explode('.', $path);
            $ref = $item;
            $exists = true;
            foreach ($segs as $s) {
                if (is_array($ref) && array_key_exists($s, $ref)) {
                    $ref = $ref[$s];
                } else {
                    $exists = false;
                    break;
                }
            }
            if ($exists) {
                self::setPath($out, $segs, $ref);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<int, string> $segs
     */
    private static function setPath(array &$target, array $segs, mixed $value): void
    {
        $ref = &$target;
        $last = array_key_last($segs);
        foreach ($segs as $i => $seg) {
            if ($i === $last) {
                $ref[$seg] = $value;
                return;
            }
            if (!isset($ref[$seg]) || !is_array($ref[$seg])) {
                $ref[$seg] = [];
            }
            $ref = &$ref[$seg];
        }
    }
}
