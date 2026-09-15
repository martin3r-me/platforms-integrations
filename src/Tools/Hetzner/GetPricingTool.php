<?php

namespace Platform\Integrations\Tools\Hetzner;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\HetznerApiException;
use Platform\Integrations\Services\HetznerApiService;
use Platform\Integrations\Support\FieldProjection;

/**
 * Ruft die für das Projekt gültige Preisliste ab.
 */
class GetPricingTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.hetzner.pricing.GET';
    }

    public function getDescription(): string
    {
        return 'GET /pricing — Ruft die für das Hetzner-Projekt gültige Preisliste ab: Servertypen, Volumes, '
            . 'Traffic, Floating IPs, Load Balancer und Image-Speicher, jeweils netto und brutto in der '
            . 'Projektwährung. Grundlage für Kostenabschätzungen vor einer Bestellung.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: reduziert die Antwort auf diese Felder (Dot-Notation).',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Hetzner-Verbindung (= Projekt).',
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
            $result = app(HetznerApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->getPricing($context->user);

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

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['hetzner', 'cloud', 'pricing', 'costs'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
