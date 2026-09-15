<?php

namespace Platform\Integrations\Tools\Forge;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\ForgeApiException;
use Platform\Integrations\Services\ForgeApiService;
use Platform\Integrations\Tools\Forge\Concerns\GuardsArguments;

/**
 * Startet oder stoppt einen Server-Dienst (nginx, php, mysql, …) — SCHREIBEND.
 */
class ServiceActionTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Je Dienst die von der Forge-API erlaubten Aktionen.
     *
     * @var array<string, array<int, string>>
     */
    public const SERVICE_ACTIONS = [
        'nginx' => ['reboot', 'stop'],
        'php' => ['reboot', 'reload'],
        'mysql' => ['reboot', 'stop'],
        'postgres' => ['reboot', 'stop'],
        'redis' => ['reboot'],
        'supervisor' => ['reboot'],
    ];

    public function getName(): string
    {
        return 'integrations.forge.service.action';
    }

    public function getDescription(): string
    {
        return <<<TXT
        SCHREIBEND: Startet, stoppt oder lädt einen Dienst auf einem Laravel-Forge-Server neu
        (POST /orgs/{org}/servers/{server}/services/{service}/actions).

        Erlaubte Kombinationen:
        - nginx: reboot, stop
        - php: reboot, reload  (reload ist der schonendste Weg, um PHP-FPM neu einzulesen)
        - mysql: reboot, stop
        - postgres: reboot, stop
        - redis: reboot
        - supervisor: reboot

        Ein "stop" lässt den Dienst dauerhaft aus — bei nginx sind die Sites danach offline.
        Deshalb ist für jede Aktion "confirm": true erforderlich.
        TXT;
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'server' => [
                    'type' => 'string',
                    'description' => 'Server-ID.',
                ],
                'service' => [
                    'type' => 'string',
                    'enum' => array_keys(self::SERVICE_ACTIONS),
                    'description' => 'Betroffener Dienst.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['reboot', 'reload', 'stop'],
                    'description' => 'Auszuführende Aktion. Welche je Dienst erlaubt ist, steht in der Beschreibung.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Pflicht: muss true sein. Bestätigt den Eingriff in einen laufenden Dienst.',
                ],
                'organization' => [
                    'type' => 'string',
                    'description' => 'Organisations-Slug. Ohne Angabe: Standard-Organisation der Verbindung.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen Forge-Verbindung.',
                ],
            ],
            'required' => ['server', 'service', 'action', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['server', 'service', 'action'])) {
            return $error;
        }

        $service = strtolower(trim((string) $arguments['service']));
        $action = strtolower(trim((string) $arguments['action']));

        if (!isset(self::SERVICE_ACTIONS[$service])) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                "Unbekannter Dienst \"{$service}\". Erlaubt: " . implode(', ', array_keys(self::SERVICE_ACTIONS)) . '.'
            );
        }

        $allowed = self::SERVICE_ACTIONS[$service];

        if (!in_array($action, $allowed, true)) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                "Aktion \"{$action}\" ist für den Dienst \"{$service}\" nicht erlaubt. Möglich: " . implode(', ', $allowed) . '.'
            );
        }

        if (($arguments['confirm'] ?? false) !== true) {
            return ToolResult::error(
                'CONFIRMATION_REQUIRED',
                "Die Aktion \"{$action}\" greift in den laufenden Dienst \"{$service}\" ein. "
                . 'Bitte "confirm": true setzen, um sie auszuführen.'
            );
        }

        try {
            $result = app(ForgeApiService::class)
                ->forConnection($arguments['connection_id'] ?? null)
                ->runServiceAction(
                    $context->user,
                    (string) $arguments['server'],
                    $service,
                    $action,
                    isset($arguments['organization']) ? (string) $arguments['organization'] : null
                );

            return ToolResult::success([
                'service' => $service,
                'action' => $action,
                'server' => (string) $arguments['server'],
                'result' => $result,
            ]);
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
            'tags' => ['forge', 'laravel', 'services', 'nginx', 'php', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'high',
        ];
    }
}
