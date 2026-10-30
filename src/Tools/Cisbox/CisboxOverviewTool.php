<?php

namespace Platform\Integrations\Tools\Cisbox;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Integrations\Services\CisboxApiService;
use Platform\Integrations\Services\CisboxResource;

/**
 * Referenz zur cisbox Invoice API v1 — Einstiegspunkt vor dem Call-Tool.
 */
class CisboxOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'integrations.cisbox.overview';
    }

    public function getDescription(): string
    {
        return 'Referenz zur cisbox Invoice API v1: Authentifizierung, Antwort-Hülle, Paginierung, Filter '
            . 'und der vollständige Endpunkt-Katalog (27 Operationen in 11 Themenbereichen, alle lesend). '
            . 'Ohne Argumente kommt die Übersicht; mit "tag" die Endpunkte eines Bereichs, mit "search" '
            . 'eine Freitextsuche, mit "fields_of" die Felder eines Datentyps (z.B. "InvoiceApiDto"). '
            . 'Read-only und ohne API-Aufruf — die Daten stammen aus der mitgelieferten Spezifikation.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tag' => [
                    'type' => 'string',
                    'description' => 'Themenbereich, z.B. "invoices", "creditors", "payments", "deliveries", '
                        . '"accounts". Ohne Angabe wird nur die Bereichsübersicht geliefert.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Freitextsuche über Pfad, Beschreibung und operationId, z.B. "pdf", "pending", "code".',
                ],
                'fields_of' => [
                    'type' => 'string',
                    'description' => 'Felder eines Datentyps auflisten — entweder ein DTO-Name wie "InvoiceApiDto" '
                        . 'oder ein Operations-Schlüssel wie "GET /invoices".',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximale Trefferzahl bei "search" (Standard: 40).',
                    'minimum' => 1,
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $tag = trim((string) ($arguments['tag'] ?? ''));
        $search = trim((string) ($arguments['search'] ?? ''));
        $fieldsOf = trim((string) ($arguments['fields_of'] ?? ''));
        $limit = max(1, (int) ($arguments['limit'] ?? 40));

        if ($fieldsOf !== '') {
            $fields = CisboxResource::fields($fieldsOf);

            if ($fields === []) {
                return ToolResult::error(
                    'UNKNOWN_TYPE',
                    sprintf(
                        'Unbekannter Datentyp "%s". Verfügbar: %s.',
                        $fieldsOf,
                        implode(', ', array_keys(CisboxResource::FIELDS))
                    )
                );
            }

            return ToolResult::success([
                'datentyp' => $fieldsOf,
                'felder' => $fields,
                'hinweis' => 'Diese Namen lassen sich als "fields" an die Tools übergeben, um die Antwort zu verschlanken.',
            ]);
        }

        if ($search !== '') {
            return ToolResult::success([
                'suche' => $search,
                'treffer' => $this->describe(CisboxResource::search($search, $limit)),
            ]);
        }

        if ($tag !== '') {
            $keys = CisboxResource::byTag($tag);

            if ($keys === []) {
                return ToolResult::error(
                    'UNKNOWN_TAG',
                    sprintf(
                        'Unbekannter Themenbereich "%s". Verfügbar: %s.',
                        $tag,
                        implode(', ', array_keys(CisboxResource::TAGS))
                    )
                );
            }

            return ToolResult::success([
                'themenbereich' => $tag,
                'endpunkte' => $this->describe($keys),
            ]);
        }

        return ToolResult::success([
            'api' => 'cisbox Invoice API v1',
            'dokumentation' => 'https://api.cisbox.com/docs/ui/invoice-api-v1',
            'spezifikation' => 'https://cisbox-global.cisbox.com/resteasy/api-docs/invoice/v1',
            'base_url' => (string) config('integrations.cisbox.api_base_url', 'https://api.cisbox.com/invoice/v1'),
            'authentifizierung' => [
                'verfahren' => 'API-Key im Header X-API-Key',
                'hinweis' => 'Der Key hängt an der Installation, nicht an einem Benutzerkonto, und sieht '
                    . 'deren gesamten Datenbestand — die Rechteverwaltung von cisbox greift bei API-Zugriffen nicht.',
            ],
            'umfang' => [
                'operationen' => count(CisboxResource::OPERATIONS),
                'schreibend' => 0,
                'hinweis' => 'Die API ist vollständig lesend. Es gibt keinen Endpunkt, der Daten verändert.',
            ],
            'antwort_huelle' => [
                'aufbau' => '{ "status": "...", "data": [ … ], "meta": { totalItems, totalPages, currentPage, itemsPerPage } }',
                'wichtig' => '"data" ist immer ein Array — auch bei Einzelabrufen wie GET /invoices/{barcodeNo}, '
                    . 'die dann genau einen Eintrag enthalten.',
                'meta' => 'Nur bei paginierten Listen vorhanden.',
                'request_id' => 'Jede Antwort trägt den Header X-Request-Id; er wird als "request_id" durchgereicht '
                    . 'und gehört in jede Support-Anfrage an cisbox.',
            ],
            'paginierung' => [
                'parameter' => 'page (ab 1) und itemsPerPage (Standard 200, Maximum '
                    . CisboxApiService::MAX_ITEMS_PER_PAGE . ').',
                'alle_seiten' => 'Die Listen-Tools kennen "all_pages": true und laufen dann bis zu 50 Seiten durch.',
            ],
            'filter' => [
                'q' => 'Volltextsuche, mindestens ' . CisboxApiService::MIN_SEARCH_LENGTH
                    . ' Zeichen — kürzere lehnt die API mit HTTP 422 ab.',
                'updatedSince' => 'ISO-8601-Zeitstempel; ohne Zeitzonen-Angabe gilt Serverzeit. '
                    . 'Der Weg für inkrementelle Abgleiche.',
                'datumsfilter' => 'invoiceDateFrom/-To als yyyy-MM-dd, accountingPeriodFrom/-To als YYYYMM.',
                'status' => 'Bei offenen Posten mehrfach setzbar — als Array übergeben, z.B. ["APPROVED","PAID"].',
            ],
            'themenbereiche' => array_map(
                static fn (string $tag, int $count) => ['tag' => $tag, 'operationen' => $count],
                array_keys(CisboxResource::TAGS),
                array_values(CisboxResource::TAGS)
            ),
            'datentypen' => array_keys(CisboxResource::FIELDS),
            'naechster_schritt' => 'integrations.cisbox.overview mit "tag" für die Endpunkte eines Bereichs '
                . 'oder "fields_of" für die Felder eines Datentyps, dann das passende Fach-Tool oder '
                . 'integrations.cisbox.call.',
        ]);
    }

    /**
     * @param  array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    protected function describe(array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            [$method, $path] = explode(' ', $key, 2);
            $op = CisboxResource::operation($method, $path);

            if (!$op) {
                continue;
            }

            $entry = [
                'operation' => $key,
                'beschreibung' => $op['summary'],
                'liefert' => $op['returns'],
            ];

            if ($op['path']) {
                $entry['pfad_parameter'] = $op['path'];
            }

            if ($op['query']) {
                $entry['query_parameter'] = $op['query'];
            }

            if ($op['enums']) {
                $entry['erlaubte_werte'] = $op['enums'];
            }

            if ($op['paginated']) {
                $entry['paginiert'] = 'ja — page/itemsPerPage, meta im Ergebnis';
            }

            if ($op['single']) {
                $entry['einzelabruf'] = 'ja — "data" enthält genau einen Eintrag';
            }

            $out[] = $entry;
        }

        return $out;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'reference',
            'tags' => ['cisbox', 'invoice', 'rechnungen', 'overview', 'reference'],
            'read_only' => true,
            'requires_auth' => false,
            'risk_level' => 'safe',
        ];
    }
}
