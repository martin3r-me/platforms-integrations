<?php

namespace Platform\Integrations\Tools\Necta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiService;
use Platform\Integrations\Exceptions\NectaApiException;
use Platform\Integrations\Support\FieldProjection;

/**
 * Komfort-Tool: GET /rawapi/products — Produkte (paginiert, read-only).
 * Entspricht integrations.necta.list.GET mit resource="products".
 */
class ListProductsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return "integrations.necta.products.GET";
    }

    public function getDescription(): string
    {
        return "GET /rawapi/products — Listet Produkte aus necta.one (paginiert, read-only).";
    }

    public function getSchema(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "pageNumber" => [
                    "type" => "integer",
                    "description" => "Seitenzahl (1-basiert). Standard: 1.",
                    "minimum" => 1,
                ],
                "pageSize" => [
                    "type" => "integer",
                    "description" => "Eintraege pro Seite. Standard: 50.",
                    "minimum" => 1,
                ],
                "filters" => [
                    "type" => "object",
                    "description" => "Optionale Query-Filter (siehe integrations.necta.resources.GET, resource=\"products\").",
                ],
                "fields" => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: nur diese Felder zurückgeben (Dot-Notation für verschachtelte, z.B. "customer.customerNumber"). Reduziert die Antwortgröße drastisch.'],
                "connection_id" => [
                    "type" => "integer",
                    "description" => "Optional: ID einer spezifischen necta.one-Connection.",
                ],
            ],
            "required" => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error("AUTH_ERROR", "Benutzer nicht authentifiziert.");
        }

        try {
            $svc = app(NectaApiService::class)->forConnection($arguments["connection_id"] ?? null);
            $result = $svc->listForUser(
                $context->user,
                "products",
                (int) ($arguments["pageNumber"] ?? 1),
                (int) ($arguments["pageSize"] ?? 50),
                is_array($arguments["filters"] ?? null) ? $arguments["filters"] : []
            );

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = FieldProjection::apply($result, $arguments['fields']);
            }

            return ToolResult::success($result);
        } catch (NectaApiException $e) {
            return ToolResult::error($e->getNectaErrorCode() ?? "NECTA_ERROR", $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error("EXECUTION_ERROR", "Fehler: " . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            "category" => "query",
            "tags" => ["necta", "products", "list"],
            "read_only" => true,
            "requires_auth" => true,
            "risk_level" => "safe",
        ];
    }
}
