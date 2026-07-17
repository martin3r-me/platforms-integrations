<?php

namespace Platform\Integrations\Tools\NectaV1;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Integrations\Services\NectaApiV1Service;
use Platform\Integrations\Exceptions\NectaApiException;

/**
 * Generisches REST-Tool für die necta.one API v1 (/api/v1/{tenantId}/…).
 * Deckt alle v1-Endpunkte ab (GET/POST/PUT/PATCH/DELETE), die kein dediziertes Tool haben.
 */
class CallTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.necta.v1.call';
    }

    public function getDescription(): string
    {
        return <<<TXT
        Generischer necta.one-API-v1-Aufruf. path = relativ zur Tenant-Basis, z.B. "/customers",
        "/customer-contacts/{id}", "/orders". method = GET/POST/PUT/PATCH/DELETE (Default GET).
        Die Basis {host}/api/v1/{tenantId} setzt der Service aus den Connection-Credentials selbst
        (tenant_id muss hinterlegt sein). Auth: X-Api-Key.

        payload: bei GET/DELETE Query-Parameter, bei POST/PUT/PATCH der JSON-Body. Felder je Endpunkt
        siehe https://docu.necta.one/necta.one-api (Spec spec/necta-one.json).

        Abgrenzung: Für die Raw-API (/rawapi, read-only) sind die integrations.necta.* Tools zuständig.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Pfad relativ zu /api/v1/{tenantId}, z.B. "/customers" oder "/orders/{id}".',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                    'description' => 'HTTP-Methode. Default: GET.',
                ],
                'payload' => [
                    'type' => 'object',
                    'description' => 'GET/DELETE: Query-Parameter. POST/PUT/PATCH: JSON-Body.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen necta-Connection.',
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

        $path = trim((string) ($arguments['path'] ?? ''));
        if ($path === '') {
            return ToolResult::error('VALIDATION_ERROR', 'Parameter "path" (z.B. "/customers") ist erforderlich.');
        }

        $method = strtoupper((string) ($arguments['method'] ?? 'GET'));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return ToolResult::error('VALIDATION_ERROR', 'method muss GET/POST/PUT/PATCH/DELETE sein.');
        }

        $payload = is_array($arguments['payload'] ?? null) ? $arguments['payload'] : [];

        try {
            $svc = app(NectaApiV1Service::class)->forConnection($arguments['connection_id'] ?? null);
            $result = $svc->call($context->user, $method, $path, $payload);

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
            'category' => 'action',
            'tags' => ['necta', 'v1', 'rest', 'generic'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'medium',
        ];
    }
}
