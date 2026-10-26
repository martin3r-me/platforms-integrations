<?php

namespace Platform\Integrations\Tools\Ninja;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Services\NinjaApiService;
use Platform\Integrations\Tools\Ninja\Concerns\GuardsArguments;

/**
 * Ein Gerät samt seiner Unterressourcen abrufen.
 */
class GetDeviceTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Unterressourcen eines Geräts. Schlüssel ist der "aspect"-Wert, Wert der
     * Pfad-Anhang hinter /v2/device/{id}.
     */
    private const ASPECTS = [
        'details' => '',
        'activities' => '/activities',
        'alerts' => '/alerts',
        'custom-fields' => '/custom-fields',
        'disks' => '/disks',
        'jobs' => '/jobs',
        'last-logged-on-user' => '/last-logged-on-user',
        'network-interfaces' => '/network-interfaces',
        'os-patch-installs' => '/os-patch-installs',
        'os-patches' => '/os-patches',
        'policy-overrides' => '/policy/overrides',
        'processors' => '/processors',
        'software' => '/software',
        'software-patch-installs' => '/software-patch-installs',
        'software-patches' => '/software-patches',
        'volumes' => '/volumes',
        'windows-services' => '/windows-services',
        'geolocation-history' => '/geolocation/history',
        'dashboard-url' => '/dashboard-url',
        'scripting-options' => '/scripting/options',
    ];

    public function getName(): string
    {
        return 'integrations.ninja.device.GET';
    }

    public function getDescription(): string
    {
        return 'Ruft ein Gerät ab — wahlweise die Stammdaten oder eine seiner Unterressourcen. '
            . 'Über "aspect" wählbar: ' . implode(', ', array_keys(self::ASPECTS)) . '. '
            . 'Beispiel: aspect="software" liefert das Software-Inventar, aspect="alerts" die '
            . 'ausgelösten Bedingungen des Geräts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'Geräte-ID aus NinjaOne.',
                ],
                'aspect' => [
                    'type' => 'string',
                    'enum' => array_keys(self::ASPECTS),
                    'description' => 'Welcher Teil des Geräts abgerufen wird. Standard: "details".',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese Felder zurückgeben (Dot-Notation für verschachtelte).',
                ],
                'connection_id' => ['type' => 'integer', 'description' => 'Optional: bestimmte NinjaOne-Verbindung.'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['id'])) {
            return $error;
        }

        $aspect = (string) ($arguments['aspect'] ?? 'details');

        if (!array_key_exists($aspect, self::ASPECTS)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf('Unbekannter aspect "%s". Erlaubt: %s.', $aspect, implode(', ', array_keys(self::ASPECTS)))
            );
        }

        $path = '/v2/device/{id}' . self::ASPECTS[$aspect];

        try {
            $result = app(NinjaApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->call($context->user, 'GET', $path, ['id' => (int) $arguments['id']]);

            if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                $result = NinjaApiService::projectFields($result, $arguments['fields']);
            }

            return ToolResult::success($result);
        } catch (NinjaApiException $e) {
            return ToolResult::error($e->getNinjaErrorCode() ?? 'NINJA_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['ninja', 'ninjaone', 'rmm', 'device', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
