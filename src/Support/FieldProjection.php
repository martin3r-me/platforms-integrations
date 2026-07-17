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
 * Wird integrationsübergreifend genutzt (necta Raw + v1, DedeFleet), um riesige
 * Antworten (Bestellungen/Rechnungen/Artikel mit 100+ Feldern) zu verschlanken.
 */
final class FieldProjection
{
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

        foreach (['result', 'items', 'data', 'content'] as $key) {
            if (isset($response[$key]) && is_array($response[$key]) && array_is_list($response[$key])) {
                $response[$key] = array_map(
                    static fn ($it) => is_array($it) ? self::pickPaths($it, $paths) : $it,
                    $response[$key]
                );

                return $response;
            }
        }

        return self::pickPaths($response, $paths);
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
