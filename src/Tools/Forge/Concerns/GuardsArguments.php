<?php

namespace Platform\Integrations\Tools\Forge\Concerns;

use Platform\Core\Contracts\ToolResult;

/**
 * Guard-Helfer für Forge-Tools: prüft Pflicht-Argumente vor dem Zugriff und
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

    /**
     * Baut die Cursor-Paginierungs-Query der Forge API v2 (page[size], page[cursor]).
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    protected function paginationQuery(array $arguments): array
    {
        $page = [];

        if (!empty($arguments['per_page'])) {
            $page['size'] = (int) $arguments['per_page'];
        }

        if (!empty($arguments['cursor'])) {
            $page['cursor'] = (string) $arguments['cursor'];
        }

        return $page ? ['page' => $page] : [];
    }
}
