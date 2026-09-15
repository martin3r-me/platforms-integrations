<?php

namespace Platform\Integrations\Tools\Hetzner\Concerns;

use Platform\Core\Contracts\ToolResult;

/**
 * Guard-Helfer für Hetzner-Tools: prüft Pflicht-Argumente vor dem Zugriff und
 * baut die seitenbasierte Paginierungs-Query der Hetzner Cloud API.
 */
trait GuardsArguments
{
    /** Hetzner erlaubt maximal 50 Einträge pro Seite. */
    public const MAX_PER_PAGE = 50;

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
     * Baut die Paginierungs- und Filter-Query (page, per_page, name, label_selector, sort, status).
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    protected function listQuery(array $arguments): array
    {
        $query = [];

        if (!empty($arguments['page'])) {
            $query['page'] = (int) $arguments['page'];
        }

        if (!empty($arguments['per_page'])) {
            // Hetzner deckelt bei 50 — darüber antwortet die API mit invalid_input.
            $query['per_page'] = min((int) $arguments['per_page'], self::MAX_PER_PAGE);
        }

        foreach (['name', 'label_selector', 'sort', 'status'] as $key) {
            if (isset($arguments[$key]) && $arguments[$key] !== '' && $arguments[$key] !== []) {
                $query[$key] = $arguments[$key];
            }
        }

        return $query;
    }
}
