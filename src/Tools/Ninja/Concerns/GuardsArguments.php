<?php

namespace Platform\Integrations\Tools\Ninja\Concerns;

use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\NinjaResource;

/**
 * Gemeinsame Argument-Prüfungen der NinjaOne-Tools.
 *
 * Verändernde Aufrufe verlangen ausdrücklich "confirm": true. NinjaOne steuert
 * echte Endgeräte — ein Neustart oder ein Skriptlauf lässt sich nicht
 * zurücknehmen, deshalb darf er nicht aus einer beiläufigen Tool-Nutzung
 * heraus passieren.
 */
trait GuardsArguments
{
    /** NinjaOne deckelt pageSize bei 1000. */
    public const MAX_PAGE_SIZE = 1000;

    public const DEFAULT_PAGE_SIZE = 100;

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
     * Verlangt confirm=true für verändernde Aufrufe.
     *
     * @param array<string, mixed> $arguments
     */
    protected function guardConfirmed(array $arguments, string $what): ?ToolResult
    {
        if (($arguments['confirm'] ?? false) !== true) {
            return ToolResult::error(
                'CONFIRMATION_REQUIRED',
                "{$what} verändert echte Systeme und erfordert \"confirm\": true."
            );
        }

        return null;
    }

    /**
     * Baut die Paginierungs-Query (pageSize, after, cursor) und reicht
     * zusätzliche erlaubte Filter durch.
     *
     * @param array<string, mixed> $arguments
     * @param array<int, string>   $extra Weitere Query-Parameter dieses Endpunkts
     * @return array<string, mixed>
     */
    protected function listQuery(array $arguments, array $extra = []): array
    {
        $query = [];

        if (!empty($arguments['pageSize'])) {
            $query['pageSize'] = min((int) $arguments['pageSize'], self::MAX_PAGE_SIZE);
        }

        foreach (array_merge(['after', 'cursor', 'df'], $extra) as $key) {
            if (isset($arguments[$key]) && $arguments[$key] !== '' && $arguments[$key] !== []) {
                $query[$key] = $arguments[$key];
            }
        }

        return $query;
    }

    /**
     * Prüft, ob die Operation im Katalog steht, und liefert sonst einen Fehler
     * mit passenden Vorschlägen — besser als ein nacktes 404 der API.
     */
    protected function guardOperation(string $method, string $path): ?ToolResult
    {
        if (NinjaResource::exists($method, $path)) {
            return null;
        }

        $needle = trim(preg_replace('#\{[^}]*\}#', '', $path) ?? $path, '/');
        $needle = $needle !== '' ? (explode('/', $needle)[1] ?? $needle) : $path;
        $hits = NinjaResource::search($needle, 8);

        $message = sprintf('Unbekannte Operation "%s %s".', mb_strtoupper($method), $path);
        $message .= $hits
            ? ' Meintest du: ' . implode(' · ', $hits) . '?'
            : ' Verfügbare Endpunkte siehe integrations.ninja.overview.';

        return ToolResult::error('UNKNOWN_OPERATION', $message);
    }
}
