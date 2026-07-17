<?php

namespace Platform\Integrations\Tools\Easybill\Concerns;

use Platform\Core\Contracts\ToolResult;

/**
 * Kleiner Guard-Helfer für easybill-Tools: prüft Pflicht-Argumente VOR dem
 * Zugriff und liefert eine saubere, hinweisgebende Fehlermeldung statt einer
 * PHP-Notice ("Undefined array key …").
 *
 * Besonderheit: Bei fehlenden *_id-Parametern (Einzel-Ressource) verweist die
 * Meldung auf das passende Plural-List-Tool — löst die Singular/Plural-Verwirrung.
 */
trait GuardsArguments
{
    /**
     * Gibt eine Fehler-ToolResult zurück, falls eines der Pflicht-Argumente
     * fehlt/leer ist — sonst null.
     *
     * @param array<string, mixed> $arguments
     * @param array<int, string>   $keys
     */
    protected function guardRequired(array $arguments, array $keys): ?ToolResult
    {
        foreach ($keys as $key) {
            $value = $arguments[$key] ?? null;

            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                return ToolResult::error('VALIDATION_ERROR', $this->missingArgumentMessage($key));
            }
        }

        return null;
    }

    /**
     * Baut eine hilfreiche Fehlermeldung für ein fehlendes Pflicht-Argument.
     */
    protected function missingArgumentMessage(string $key): string
    {
        if (str_ends_with($key, '_id')) {
            $resource = substr($key, 0, -3);            // z.B. "document"
            $plural = $this->pluralizeResource($resource);

            return "Pflichtparameter \"{$key}\" fehlt — erwartet wird die ID eines einzelnen "
                . "{$resource}. Zum Auflisten oder Suchen nutze das Plural-Tool "
                . "integrations.easybill.{$plural}.GET (dort optional mit search/query filtern).";
        }

        if ($key === 'data') {
            return 'Pflichtparameter "data" (object) fehlt. Die erwarteten Felder samt Beispiel-Payload '
                . 'stehen in der Tool-Description (Feldliste nach Zweck gruppiert). '
                . 'Geldbeträge immer als Integer-Cent (185000 = 1.850,00 €).';
        }

        return "Pflichtparameter \"{$key}\" fehlt.";
    }

    /**
     * Naive, aber für easybill-Ressourcen ausreichende Pluralisierung
     * (document→documents, customer→customers, position→positions, project→projects,
     * task→tasks). Sonderfall: category→categories.
     */
    protected function pluralizeResource(string $resource): string
    {
        if (str_ends_with($resource, 'y')) {
            return substr($resource, 0, -1) . 'ies';
        }

        if (str_ends_with($resource, 's')) {
            return $resource;
        }

        return $resource . 's';
    }
}
