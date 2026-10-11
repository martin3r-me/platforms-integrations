<?php

namespace Platform\Integrations\Tools\Connections;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\IntegrationConnectionResolver;

/**
 * LLM-Tool: Integration-Connections eines Users auflisten.
 *
 * Hintergrund: Alle Vendor-Tools (Lexware, Necta, Moss, Easybill, DATEV, ...) akzeptieren
 * einen optionalen Parameter `connection_id`, um gezielt EINE von mehreren Verbindungen
 * anzusprechen. Ohne dieses Tool kannte der MCP-Client die IDs der vorhandenen Verbindungen
 * nicht und nutzte immer nur die Default-Connection — mehrere Verbindungen waren praktisch
 * nicht erreichbar.
 *
 * Dieses Tool liefert pro Integration die verfügbaren Connections mit ihrer `id`, dem Namen,
 * dem Default-Flag und dem Status. Die zurückgegebene `id` wird dann als `connection_id` an
 * das jeweilige Vendor-Tool übergeben.
 *
 * Hinweis zu Keys: Lexware/LexOffice läuft intern unter dem Key `lexoffice`.
 */
class ListConnectionsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.connections.GET';
    }

    public function getDescription(): string
    {
        return 'GET /connections - Listet die Integration-Connections des angemeldeten Users auf '
            . '(eigene und für ihn freigegebene). Liefert pro Connection id, name, is_default, status '
            . 'und den integration_key. Die id wird als connection_id an die Vendor-Tools übergeben, '
            . 'um gezielt eine von mehreren Verbindungen anzusprechen (ohne connection_id wird dort die '
            . 'Default-Connection verwendet). Optional nach integration_key filtern '
            . '(z.B. lexoffice für Lexware, necta, moss, easybill, datev).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'integration_key' => [
                    'type' => 'string',
                    'description' => 'Optional: nur Connections dieser Integration (z.B. "lexoffice", "necta", "moss", "easybill", "datev"). '
                        . 'Weglassen, um alle Integrationen des Users aufzulisten.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        try {
            $resolver = app(IntegrationConnectionResolver::class);

            $requestedKey = isset($arguments['integration_key'])
                ? trim((string) $arguments['integration_key'])
                : null;

            if ($requestedKey !== null && $requestedKey !== '') {
                $keys = [$requestedKey];
            } else {
                $keys = Integration::query()
                    ->where('is_enabled', true)
                    ->orderBy('key')
                    ->pluck('key')
                    ->all();
            }

            $connections = [];
            foreach ($keys as $key) {
                foreach ($resolver->resolveAllForUser($key, $context->user) as $conn) {
                    /** @var IntegrationConnection $conn */
                    $connections[] = [
                        'connection_id' => $conn->id,
                        'integration_key' => $conn->integration?->key ?? $key,
                        'integration_name' => $conn->integration?->name,
                        'name' => $conn->name,
                        'is_default' => (bool) $conn->is_default,
                        'status' => $conn->status,
                        'is_owner' => $conn->owner_user_id === $context->user->id,
                        'last_tested_at' => $conn->last_tested_at?->toIso8601String(),
                    ];
                }
            }

            return ToolResult::success([
                'connections' => $connections,
                'total' => count($connections),
                'usage_hint' => 'Übergib die gewünschte connection_id an das jeweilige Vendor-Tool '
                    . '(z.B. integrations.lexware.invoices.GET mit connection_id), um gezielt diese Verbindung '
                    . 'anzusprechen. Ohne connection_id wird die Connection mit is_default=true verwendet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['integrations', 'connections', 'discovery', 'multi-connection'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
