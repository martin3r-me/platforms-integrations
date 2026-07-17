<?php

namespace Platform\Integrations\Tools\Dedefleet\Concerns;

use Platform\Core\Contracts\ToolResult;

/**
 * Guard-Helfer für DedeFleet-Tools: prüft Pflicht-Argumente vor dem Zugriff und
 * liefert eine klare Fehlermeldung statt einer PHP-Notice.
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
}
