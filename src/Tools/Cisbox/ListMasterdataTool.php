<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Tools\Cisbox\Concerns\GuardsArguments;

/**
 * Sammel-Tool für die Buchhaltungs-Stammdaten unter /masterdata.
 *
 * Die sieben Ressourcen sind bis auf den Namen des Schlüssels gleich
 * aufgebaut — je ein eigenes Tool brächte sieben fast identische Schemata in
 * den Katalog, ohne etwas zu erklären.
 */
class ListMasterdataTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Ressource => [Listen-Pfad, Einzel-Pfad, Name des Pfad-Parameters, Erläuterung]
     */
    private const RESOURCES = [
        'accounts' => [
            '/masterdata/accounts',
            '/masterdata/accounts/{accountNo}',
            'accountNo',
            'Sachkonten mit Kontenart, Steuersatz, Buchungsmodus und — beim Einzelabruf — den '
                . 'zulässigen Kostenstellen und Steuerschlüsseln.',
        ],
        'assignments' => [
            '/masterdata/assignments',
            '/masterdata/assignments/{assignmentNo}',
            'assignmentNo',
            'Zuordnungen (Betriebsstätten/Mandanten) mit Vertraulichkeits-Kennzeichen.',
        ],
        'code1' => [
            '/masterdata/code1',
            '/masterdata/code1/{code1No}',
            'code1No',
            'Kostenstellen — die erste Kontierungsdimension.',
        ],
        'code2' => [
            '/masterdata/code2',
            '/masterdata/code2/{code2No}',
            'code2No',
            'Zweite Kontierungsdimension, z.B. Kostenträger.',
        ],
        'code3' => [
            '/masterdata/code3',
            '/masterdata/code3/{code3No}',
            'code3No',
            'Dritte Kontierungsdimension, z.B. Projekt.',
        ],
        'paymenttypes' => [
            '/masterdata/paymenttypes',
            '/masterdata/paymenttypes/{paymentTypeId}',
            'paymentTypeId',
            'Zahlungsarten. Die ID taucht in Kreditoren und offenen Posten als "paymentMethod" auf.',
        ],
        'taxcodes' => [
            '/masterdata/taxcodes',
            '/masterdata/taxcodes/{taxCodeId}',
            'taxCodeId',
            'Steuerschlüssel mit Satz und Vorgabe-Kennzeichen.',
        ],
    ];

    /** Ressourcen, deren Liste "updatedSince" kennt. */
    private const SUPPORTS_UPDATED_SINCE = [
        'accounts', 'assignments', 'code1', 'code2', 'code3', 'taxcodes',
    ];

    public function getName(): string
    {
        return 'integrations.cisbox.masterdata.GET';
    }

    public function getDescription(): string
    {
        return 'Buchhaltungs-Stammdaten aus cisbox: Sachkonten (accounts), Zuordnungen (assignments), '
            . 'Kostenstellen und weitere Kontierungsdimensionen (code1, code2, code3), Zahlungsarten '
            . '(paymenttypes) und Steuerschlüssel (taxcodes). Ohne "id" kommt die Liste, mit "id" der '
            . 'einzelne Datensatz — bei einem Sachkonto zusätzlich die dort zulässigen Kostenstellen '
            . 'und Steuerschlüssel. Diese Schlüssel tauchen in Rechnungspositionen als account, '
            . 'taxCode und code1–3 auf.';
    }

    public function getSchema(): array
    {
        $descriptions = [];
        foreach (self::RESOURCES as $key => [, , , $hint]) {
            $descriptions[] = "{$key}: {$hint}";
        }

        return [
            'type' => 'object',
            'properties' => array_merge([
                'resource' => [
                    'type' => 'string',
                    'enum' => array_keys(self::RESOURCES),
                    'description' => 'Welche Stammdaten. ' . implode(' ', $descriptions),
                ],
                'id' => [
                    'type' => ['string', 'integer'],
                    'description' => 'Schlüssel des einzelnen Datensatzes — Kontonummer, Code-Nummer '
                        . 'oder numerische ID, je nach Ressource. Ohne Angabe kommt die Liste.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => [
                        'UNDEFINED', 'NONE', 'EXPENSES', 'INVENTORIES', 'ASSETS',
                        'SUBLEDGER_RECIEVABLES', 'SUBLEDGER_PAYABLES', 'REVENUES', 'ACTIVA', 'PASSIVA',
                    ],
                    'description' => 'Nur für resource="accounts": auf eine Kontenart einschränken.',
                ],
                'updatedSince' => [
                    'type' => 'string',
                    'description' => 'Nur seit diesem Zeitpunkt geänderte Datensätze (ISO-8601). '
                        . 'Nicht verfügbar für paymenttypes.',
                ],
            ], $this->paginationSchema()),
            'required' => ['resource'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Benutzer nicht authentifiziert.');
        }

        if ($error = $this->guardRequired($arguments, ['resource'])) {
            return $error;
        }

        $resource = mb_strtolower(trim((string) $arguments['resource']));

        if (!isset(self::RESOURCES[$resource])) {
            return ToolResult::error(
                'UNKNOWN_RESOURCE',
                sprintf(
                    'Unbekannte Stammdaten-Ressource "%s". Verfügbar: %s.',
                    $resource,
                    implode(', ', array_keys(self::RESOURCES))
                )
            );
        }

        if ($error = $this->guardSearch($arguments)) {
            return $error;
        }

        [$listPath, $singlePath, $pathParam] = self::RESOURCES[$resource];
        $id = $arguments['id'] ?? null;

        if (isset($arguments['type']) && $arguments['type'] !== '' && $resource !== 'accounts') {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Der Filter "type" gilt nur für resource="accounts".'
            );
        }

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            if ($id !== null && $id !== '') {
                $response = $service->call($context->user, 'GET', $singlePath, [$pathParam => $id]);
                $record = $service->single($response);

                if ($record === null) {
                    return ToolResult::error(
                        'NOT_FOUND',
                        sprintf('Kein Datensatz "%s" in %s gefunden.', $id, $resource)
                    );
                }

                if (!empty($arguments['fields']) && is_array($arguments['fields'])) {
                    $projected = CisboxApiService::projectFields($record, $arguments['fields']);
                    $record = is_array($projected) ? $projected : $record;
                }

                return ToolResult::success([
                    'resource' => $resource,
                    'data' => $record,
                    'request_id' => $response['request_id'] ?? null,
                ]);
            }

            $query = $this->listQuery($arguments, $resource === 'accounts' ? ['type'] : []);

            if (!in_array($resource, self::SUPPORTS_UPDATED_SINCE, true)) {
                unset($query['updatedSince']);
            }

            $result = $this->runList($service, $context->user, $listPath, [], $query, $arguments);

            return ToolResult::success(array_merge(['resource' => $resource], $result));
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'stammdaten', 'konten', 'kostenstellen', 'steuerschluessel'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
