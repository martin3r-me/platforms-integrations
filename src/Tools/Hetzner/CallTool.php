<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\HetznerApiException;
use Platform\Integrations\Services\HetznerApiService;
use Platform\Integrations\Support\FieldProjection;
use Platform\Integrations\Tools\Hetzner\Concerns\GuardsArguments;

/**
 * Generisches Tool für die Hetzner Cloud API v1: ruft einen beliebigen
 * Endpunkt auf. Deckt alle 152 Endpunkte ab, die kein dediziertes Tool haben.
 */
class CallTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Aufrufe, die Ressourcen unwiderruflich zerstören und deshalb eine
     * ausdrückliche Bestätigung verlangen.
     */
    private const DESTRUCTIVE_METHODS = ['DELETE'];

    private const DESTRUCTIVE_PATH_HINTS = [
        '/actions/poweroff',
        '/actions/reset',
        '/actions/rebuild',
    ];

    public function getName(): string
    {
        return 'integrations.hetzner.call';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Generischer Hetzner-Cloud-API-Aufruf (v1). path = Pfad ohne Base-URL, z.B. "/servers",
        "/servers/42/actions/reboot", "/zones/example.com/rrsets".

        method: GET (Default), POST, PUT, PATCH oder DELETE.
        query: Query-Parameter bei GET (page, per_page bis 50, name, label_selector, sort, status).
        body: JSON-Body bei POST/PUT/PATCH.

        Das Token bestimmt das Projekt — es gibt keinen Projekt-Parameter. Für ein anderes Projekt
        die passende connection_id mitgeben.

        Verändernde Aufrufe sind ASYNCHRON und liefern ein action-Objekt mit status running|success|error.
        Den Fortschritt über integrations.hetzner.actions.GET mit der action-ID abfragen.

        Den vollständigen Endpunkt-Katalog liefert integrations.hetzner.overview.

        SCHREIBEND: POST/PUT/DELETE verändern echte Cloud-Ressourcen und verursachen Kosten.
        DELETE auf /servers/{id} sowie die Aktionen poweroff, reset und rebuild verlangen
        zusätzlich "confirm": true.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'API-Pfad ohne Base-URL, z.B. "/servers" oder "/servers/42/actions/reboot".',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'description' => 'HTTP-Methode. Default: GET.',
                ],
                'query' => [
                    'type' => 'object',
                    'description' => 'Query-Parameter (nur bei GET), z.B. {"page": 1, "per_page": 50, "label_selector": "env=prod"}.',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'JSON-Body für POST/PUT/PATCH.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Erforderlich für zerstörende Aufrufe (DELETE sowie die Aktionen poweroff, reset, rebuild).',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: reduziert die Antwort auf diese Felder (Dot-Notation, z.B. "id", "name", "public_net.ipv4.ip").',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
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
        $path = (string) $arguments['path'];

        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return ToolResult::error('VALIDATION_ERROR', 'method muss GET, POST, PUT, PATCH oder DELETE sein.');
        }

        $query = $arguments['query'] ?? [];
        $body = $arguments['body'] ?? [];

        if (!is_array($query) || !is_array($body)) {
            return ToolResult::error('VALIDATION_ERROR', 'query und body müssen Objekte sein.');
        }

        if ($this->isDestructive($method, $path) && ($arguments['confirm'] ?? false) !== true) {
            return ToolResult::error(
                'CONFIRMATION_REQUIRED',
                "Der Aufruf {$method} {$path} zerstört oder unterbricht eine Cloud-Ressource. "
                . 'Bitte "confirm": true setzen, um ihn auszuführen.'
            );
        }

        try {
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, $method, $path, $query, $body);

            $fields = $arguments['fields'] ?? [];

            if (is_array($fields) && $fields) {
                $result = FieldProjection::apply($result, $fields);
            }

            return ToolResult::success($result);
        } catch (HetznerApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'HETZNER_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Erkennt Aufrufe, die Ressourcen löschen oder hart unterbrechen.
     */
    private function isDestructive(string $method, string $path): bool
    {
        if (in_array($method, self::DESTRUCTIVE_METHODS, true)) {
            return true;
        }

        $normalized = mb_strtolower($path);

        foreach (self::DESTRUCTIVE_PATH_HINTS as $hint) {
            if (str_contains($normalized, $hint)) {
                return true;
            }
        }

        return false;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['hetzner', 'cloud', 'generic', 'rpc', 'api'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
