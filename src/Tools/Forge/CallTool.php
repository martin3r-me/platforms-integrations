<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\ForgeApiException;
use Platform\Integrations\Services\ForgeApiService;
use Platform\Integrations\Support\FieldProjection;
use Platform\Integrations\Tools\Forge\Concerns\GuardsArguments;

/**
 * Generisches Tool für die Laravel Forge API v2: ruft einen beliebigen
 * Endpunkt auf. Deckt alle 160 Endpunkte ab, die kein dediziertes Tool haben.
 */
class CallTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    public function getName(): string
    {
        return 'integrations.forge.call';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Generischer Laravel-Forge-API-Aufruf (v2). path = Pfad ohne Base-URL, z.B.
        "/orgs/{org}/servers/123/php/versions". Der Platzhalter "{org}" wird automatisch durch die
        Standard-Organisation der Verbindung ersetzt — alternativ "organization" mitgeben.
        Ein Pfad OHNE führenden Slash gilt als organisationsrelativ: "servers/123/sites" wird zu
        "/orgs/{org}/servers/123/sites".

        method: GET (Default), POST, PUT, PATCH oder DELETE.
        query: Query-Parameter bei GET. body: JSON-Body bei POST/PUT/PATCH/DELETE.

        Paginierung ist cursorbasiert: query = {"page": {"size": 50, "cursor": "..."}}.
        Den Wert aus meta.next_cursor der Vorantwort übernehmen.

        Den vollständigen Endpunkt-Katalog liefert integrations.forge.overview.

        SCHREIBEND: POST/PUT/PATCH/DELETE verändern echte Server und Sites in der Produktion.
        Deployments, Server-Aktionen und Dienst-Neustarts wirken sofort — sorgfältig nutzen.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'API-Pfad ohne Base-URL, z.B. "/orgs/{org}/servers" oder organisationsrelativ "servers/123/sites".',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'description' => 'HTTP-Methode. Default: GET.',
                ],
                'query' => [
                    'type' => 'object',
                    'description' => 'Query-Parameter (nur bei GET). Für Paginierung: {"page": {"size": 50, "cursor": "..."}}.',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'JSON-Body für POST/PUT/PATCH/DELETE.',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Organisations-Slug. Ohne Angabe wird die Standard-Organisation der Verbindung verwendet.',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: reduziert die Antwort auf diese Felder (Dot-Notation, z.B. "id", "attributes.name"). Forge antwortet im JSON:API-Format, die Nutzdaten liegen unter "attributes" — entsprechend "attributes.name" statt "name" angeben.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung.',
                ],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['path'])) {
            return $error;
        }

        $method = strtoupper((string) ($arguments['method'] ?? 'GET'));

        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return ToolResult::error('VALIDATION_ERROR', 'method muss GET, POST, PUT, PATCH oder DELETE sein.');
        }

        $query = $arguments['query'] ?? [];
        $body = $arguments['body'] ?? [];

        if (!is_array($query) || !is_array($body)) {
            return ToolResult::error('VALIDATION_ERROR', 'query und body müssen Objekte sein.');
        }

        try {
            $result = app(ForgeApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call(
                    $context->user,
                    $method,
                    (string) $arguments['path'],
                    $query,
                    $body,
                    isset($arguments['organization']) ? (string) $arguments['organization'] : null
                );

            $fields = $arguments['fields'] ?? [];

            if (is_array($fields) && $fields) {
                $result = FieldProjection::apply($result, $fields);
            }

            return ToolResult::success($result);
        } catch (ForgeApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'FORGE_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['forge', 'laravel', 'generic', 'rpc', 'api'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
