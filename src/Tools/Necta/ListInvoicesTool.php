<?php

namespace Platform\Integrations\Tools\Necta;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiService;
use Platform\Integrations\Support\NectaListArguments;
use Platform\Integrations\Exceptions\NectaApiException;
use Platform\Integrations\Support\FieldProjection;

/**
 * Komfort-Tool: GET /rawapi/invoices — Rechnungen (Ausgangsrechnungen) (paginiert, read-only).
 * Entspricht integrations.necta.list.GET mit resource="invoices".
 */
class ListInvoicesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return "integrations.necta.invoices.GET";
    }

    public function getDescription(): string
    {
        return "GET /rawapi/invoices — Listet Rechnungen (Ausgangsrechnungen) aus necta.one (paginiert, read-only).";
    }

    public function getSchema(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "pageNumber" => [
                    "type" => "integer",
                    "description" => "Seitenzahl (1-basiert). Standard: 1. Alias: \"page\".",
                    "minimum" => 1,
                ],
                "page" => [
                    "type" => "integer",
                    "description" => "Alias fuer pageNumber (Schreibweise der v1-Tools).",
                    "minimum" => 1,
                ],
                "pageSize" => [
                    "type" => "integer",
                    "description" => "Eintraege pro Seite. Standard: 50.",
                    "minimum" => 1,
                ],
                "filters" => [
                    "type" => "object",
                    "description" => "Optionale Query-Filter (siehe integrations.necta.resources.GET, resource=\"invoices\"). "
                        . "Filter duerfen alternativ als Top-Level-Argumente uebergeben werden. Unbekannte Filter "
                        . "fuehren zu einem Fehler statt zu einem stillschweigend ungefilterten Ergebnis.",
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
            $args = NectaListArguments::normalize("invoices", $arguments);
            $result = $svc->listForUser(
                $context->user,
                "invoices",
                $args["pageNumber"],
                $args["pageSize"],
                $args["filters"]
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
            "tags" => ["necta", "invoices", "list"],
            "read_only" => true,
            "requires_auth" => true,
            "risk_level" => "safe",
        ];
    }
}
