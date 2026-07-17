<?php

namespace Platform\Integrations\Tools\Necta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiService;
use Platform\Integrations\Services\NectaResource;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * Generisches Lese-Tool für die necta.one Raw-API.
 *
 * Die Raw-API ist read-only und uniform: GET /rawapi/{resource}?pageNumber&pageSize&<filter>.
 * Dieses eine Tool deckt damit alle 417 Ressourcen ab. Gültige `resource`-Slugs und
 * ihre Filter liefert integrations.necta.resources.GET.
 */
class ListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.list.GET';
    }

    public function getDescription(): string
    {
        return 'GET /rawapi/{resource} — liest eine paginierte Seite einer beliebigen necta.one '
            . 'Raw-API-Ressource (z.B. "products", "customers", "orders", "suppliers", "invoices"). '
            . 'Gültige Slugs + erlaubte Filter via integrations.necta.resources.GET. Read-only.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => [
                    'type' => 'string',
                    'description' => 'Ressourcen-Slug, z.B. "products", "customers", "agencys". '
                        . 'Siehe integrations.necta.resources.GET.',
                ],
                'pageNumber' => [
                    'type' => 'integer',
                    'description' => 'Seitenzahl (1-basiert). Standard: 1.',
                    'minimum' => 1,
                ],
                'pageSize' => [
                    'type' => 'integer',
                    'description' => 'Einträge pro Seite. Standard: 50.',
                    'minimum' => 1,
                ],
                'filters' => [
                    'type' => 'object',
                    'description' => 'Optionale Query-Filter der Ressource (z.B. Datumsbereiche wie '
                        . 'creationDateFrom/creationDateTo). Erlaubte Filter siehe resources.GET.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen necta.one-Connection.',
                ],
            ],
            'required' => ['resource'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        $resource = trim((string) ($arguments['resource'] ?? ''));
        if ($resource === '') {
            return ToolResult::error('VALIDATION_ERROR', 'Parameter "resource" ist erforderlich.');
        }
        if (!NectaResource::exists($resource)) {
            return ToolResult::error(
                'UNKNOWN_RESOURCE',
                "Unbekannte Ressource \"{$resource}\". Gültige Slugs via integrations.necta.resources.GET."
            );
        }

        try {
            $svc = app(NectaApiService::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->listForUser(
                $context->user,
                $resource,
                (int) ($arguments['pageNumber'] ?? 1),
                (int) ($arguments['pageSize'] ?? 50),
                is_array($arguments['filters'] ?? null) ? $arguments['filters'] : []
            );

            return ToolResult::success($result);
        } catch (NectaApiException $e) {
            return ToolResult::error($e->getNectaErrorCode() ?? 'NECTA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['necta', 'raw-api', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
