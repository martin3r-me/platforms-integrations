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
 * Kontierung über viele Rechnungen hinweg auswerten.
 *
 * WARUM ES DIESES TOOL GIBT
 * In cisbox steht die Kontierung — Sachkonto und Kostenstellen — nicht an der
 * Rechnung, sondern an deren Positionen. Wer einen Betrieb für einen Monat
 * prüfen will, braucht also einen Aufruf je Rechnung. Für einen Betriebsmonat
 * sind das rund 250 Aufrufe mit zusammen etwa 700.000 Zeichen Rohdaten. Durch
 * ein Sprachmodell gereicht ist das weder bezahlbar noch lesbar, und niemand
 * prüft 250 Einzelantworten.
 *
 * Deshalb läuft die Schleife hier und nicht im Chat: Das Tool holt die
 * Positionen gebündelt, summiert sie nach den gewünschten Dimensionen und gibt
 * eine Tabelle zurück, die man am Stück lesen kann — aus 700.000 Zeichen
 * werden rund 3.000.
 *
 * DREI FALLSTRICKE, DIE DAS TOOL ABRÄUMT
 *
 * 1. Der Filter code1 an GET /invoices wählt RECHNUNGEN aus, die irgendeine
 *    Zeile auf dieser Kostenstelle haben — nicht die Zeilen selbst. In einem
 *    gemessenen Monat enthielten die so gefundenen Rechnungen 23.035,75 EUR,
 *    von denen nur 18.365,10 EUR wirklich auf der gesuchten Kostenstelle
 *    lagen. Wer die Rechnungsbeträge summiert, liegt um ein Viertel daneben.
 *    Hier wird deshalb immer auf Zeilenebene gefiltert und summiert.
 *
 * 2. Die Kontierung summiert sich auf den NETTO-Betrag, der Rechnungskopf
 *    nennt BRUTTO. Beides gegeneinander zu halten sieht nach einer Lücke aus,
 *    wo keine ist. Das Ergebnis weist beide Summen getrennt aus.
 *
 * 3. GET /invoices kennt keinen Filter auf den Debitor, obwohl genau der den
 *    Betrieb bezeichnet. Dieses Tool filtert ihn nach dem Laden — die Periode
 *    muss also vollständig geholt werden. Das ist der Grund, warum es neben
 *    debtorNo immer eine Zeitraumangabe verlangt.
 */
class AccountingSummaryTool implements ToolContract, ToolMetadataContract
{
    use GuardsArguments;

    /**
     * Dimensionen, nach denen gruppiert und gefiltert werden kann.
     *
     * "line" steht für ein Feld der Kontierungszeile, "invoice" für eines des
     * Rechnungskopfes — davon hängt ab, woher der Wert kommt.
     */
    private const DIMENSIONS = [
        'account' => ['line', 'account', 'Sachkonto'],
        'code1' => ['line', 'code1', 'Kostenstelle (Code 1)'],
        'code2' => ['line', 'code2', 'Code 2'],
        'code3' => ['line', 'code3', 'Code 3'],
        'taxCode' => ['line', 'taxCode', 'Steuerschlüssel'],
        'creditorNo' => ['invoice', 'creditorNo', 'Kreditor'],
        'debtorNo' => ['invoice', 'debtorNo', 'Debitor (Betrieb)'],
        'accountingPeriod' => ['invoice', 'accountingPeriod', 'Buchungsperiode'],
        'invoice' => ['invoice', 'barcodeNo', 'einzelne Rechnung'],
    ];

    /** Mehr Rechnungen als das wertet ein Aufruf nicht aus. */
    private const MAX_INVOICES = 2000;

    /** So viele Gruppen stehen im Ergebnis, der Rest wird zusammengefasst. */
    private const DEFAULT_TOP = 60;

    /**
     * Plausibles Verhältnis brutto/netto.
     *
     * Unter 1 kontiert eine Rechnung mehr, als sie kostet — das ist immer ein
     * Fehler. Über 1,19 läge mehr Steuer darauf, als es in Deutschland gibt.
     * Beides wird gemeldet, statt es im Summenstrich verschwinden zu lassen.
     */
    private const RATIO_MIN = 0.995;

    private const RATIO_MAX = 1.195;

    public function getName(): string
    {
        return 'integrations.cisbox.accounting.summary';
    }

    public function getDescription(): string
    {
        return 'Wertet die Kontierung vieler cisbox-Rechnungen auf einmal aus und gibt Summen statt '
            . 'Einzelsätze zurück. Gruppiert Sachkonto, Kostenstellen (Code 1/2/3), Steuerschlüssel, '
            . 'Kreditor, Debitor und Buchungsperiode in beliebiger Kombination. Das ist der Weg, einen '
            . 'Betrieb für einen Monat zu prüfen: Die Kontierung steht in cisbox nur an den Positionen, '
            . 'ein Einzelabruf je Rechnung wäre für einen Betriebsmonat zu viel. Meldet außerdem '
            . 'Rechnungen ohne Kontierung, Zeilen ohne Konto oder Kostenstelle und Rechnungen, deren '
            . 'Kontierungssumme nicht zum Bruttobetrag passt. Für die Belege hinter einer auffälligen '
            . 'Zeile group_by=["invoice"] mit den passenden Filtern setzen.';
    }

    public function getSchema(): array
    {
        $dimensions = array_keys(self::DIMENSIONS);

        return [
            'type' => 'object',
            'properties' => [
                'accountingPeriodFrom' => [
                    'type' => 'string',
                    'description' => 'Buchungsperiode ab (einschließlich), Format YYYYMM — z.B. "202610". '
                        . 'Für Monatsabschlüsse in der Regel der richtige Zeitraum, weil er dem folgt, '
                        . 'was die Buchhaltung gebucht hat, nicht dem Rechnungsdatum.',
                ],
                'accountingPeriodTo' => [
                    'type' => 'string',
                    'description' => 'Buchungsperiode bis (einschließlich), Format YYYYMM.',
                ],
                'invoiceDateFrom' => [
                    'type' => 'string',
                    'description' => 'Rechnungsdatum ab (einschließlich), ISO yyyy-MM-dd.',
                ],
                'invoiceDateTo' => [
                    'type' => 'string',
                    'description' => 'Rechnungsdatum bis (einschließlich), ISO yyyy-MM-dd.',
                ],
                'debtorNo' => [
                    'type' => 'string',
                    'description' => 'Nur Rechnungen dieses Debitors — in dieser Installation die '
                        . 'Betriebsnummer. Die API kennt dafür keinen Filter, es wird nach dem Laden '
                        . 'gefiltert: Der Zeitraum wird also vollständig geholt.',
                ],
                'creditorNo' => [
                    'type' => 'integer',
                    'description' => 'Nur Rechnungen dieses Kreditors.',
                ],
                'account' => [
                    'type' => 'string',
                    'description' => 'Nur Kontierungszeilen auf diesem Sachkonto.',
                ],
                'code1' => [
                    'type' => 'string',
                    'description' => 'Nur Kontierungszeilen auf dieser Kostenstelle. Wirkt auf die '
                        . 'ZEILEN, nicht auf die Rechnungen — anders als derselbe Filter an '
                        . 'integrations.cisbox.invoices.GET.',
                ],
                'code2' => [
                    'type' => 'string',
                    'description' => 'Nur Kontierungszeilen mit diesem Code 2.',
                ],
                'group_by' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => $dimensions],
                    'description' => 'Gruppierung, Standard ["account","code1"]. Erlaubt: '
                        . implode(', ', $dimensions) . '. "invoice" zeigt die einzelnen Belege.',
                ],
                'min_amount' => [
                    'type' => 'number',
                    'description' => 'Gruppen unterhalb dieses Betrags (absolut, netto) weglassen. '
                        . 'Sie werden in der Restzeile mitgezählt, nicht verschwiegen.',
                ],
                'top' => [
                    'type' => 'integer',
                    'description' => 'Höchstzahl ausgewiesener Gruppen, Standard ' . self::DEFAULT_TOP
                        . '. Der Rest wird zu einer Zeile zusammengefasst.',
                ],
                'resolve_names' => [
                    'type' => 'boolean',
                    'description' => 'Bezeichnungen zu Konten und Kostenstellen mitliefern '
                        . '(Standard true, kostet wenige zusätzliche Aufrufe).',
                ],
                'max_invoices' => [
                    'type' => 'integer',
                    'description' => 'Obergrenze auszuwertender Rechnungen, Standard und Maximum '
                        . self::MAX_INVOICES . '. Wird sie erreicht, sagt das Ergebnis es über truncated.',
                ],
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Bestimmte cisbox-Verbindung, falls mehrere eingerichtet sind.',
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

        $period = array_filter([
            'accountingPeriodFrom' => $arguments['accountingPeriodFrom'] ?? null,
            'accountingPeriodTo' => $arguments['accountingPeriodTo'] ?? null,
            'invoiceDateFrom' => $arguments['invoiceDateFrom'] ?? null,
            'invoiceDateTo' => $arguments['invoiceDateTo'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        // Ohne Zeitraum liefe die Auswertung über den gesamten Bestand — in der
        // gemessenen Installation 228.658 Rechnungen. Das ist nie gemeint.
        if ($period === []) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Ein Zeitraum ist nötig: accountingPeriodFrom/-To (YYYYMM) oder invoiceDateFrom/-To '
                    . '(yyyy-MM-dd). Ohne ihn liefe die Auswertung über den gesamten Rechnungsbestand.'
            );
        }

        $groupBy = $arguments['group_by'] ?? ['account', 'code1'];

        if (!is_array($groupBy) || $groupBy === []) {
            $groupBy = ['account', 'code1'];
        }

        $unknown = array_diff($groupBy, array_keys(self::DIMENSIONS));

        if ($unknown !== []) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                sprintf(
                    'Unbekannte Gruppierung: %s. Erlaubt: %s.',
                    implode(', ', $unknown),
                    implode(', ', array_keys(self::DIMENSIONS))
                )
            );
        }

        $groupBy = array_values(array_unique($groupBy));

        try {
            $service = app(CisboxApiService::class)->forConnection($arguments['connection_id'] ?? null);

            return ToolResult::success($this->summarise($service, $context->user, $arguments, $period, $groupBy));
        } catch (CisboxApiException $e) {
            return ToolResult::error($e->getErrorCode() ?? 'CISBOX_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $period
     * @param array<int, string> $groupBy
     * @return array<string, mixed>
     *
     * @throws CisboxApiException
     */
    private function summarise(
        CisboxApiService $service,
        $user,
        array $arguments,
        array $period,
        array $groupBy
    ): array {
        $maxInvoices = (int) ($arguments['max_invoices'] ?? self::MAX_INVOICES);
        $maxInvoices = max(1, min($maxInvoices, self::MAX_INVOICES));

        // creditorNo kann die API selbst filtern, debtorNo nicht. code1 bleibt
        // bewusst draußen: Der Filter dort wählt ganze Rechnungen aus, hier
        // wird auf Zeilenebene gefiltert — beides zu mischen verfälscht nichts,
        // kostet aber Genauigkeit in der Fehlersuche.
        $query = $period;

        if (($arguments['creditorNo'] ?? null) !== null && $arguments['creditorNo'] !== '') {
            $query['creditorNo'] = $arguments['creditorNo'];
        }

        $list = $service->fetchAll($user, '/invoices', [], $query, CisboxApiService::MAX_ITEMS_PER_PAGE);
        $invoices = $service->items($list);
        $fetched = count($invoices);

        $debtor = $arguments['debtorNo'] ?? null;

        if ($debtor !== null && $debtor !== '') {
            $invoices = array_values(array_filter(
                $invoices,
                static fn ($i) => (string) ($i['debtorNo'] ?? '') === (string) $debtor
            ));
        }

        $matched = count($invoices);
        $truncated = (bool) ($list['meta']['truncated'] ?? false);

        if ($matched > $maxInvoices) {
            $invoices = array_slice($invoices, 0, $maxInvoices);
            $truncated = true;
        }

        $paramSets = [];

        foreach ($invoices as $invoice) {
            $barcode = $invoice['barcodeNo'] ?? null;

            if ($barcode !== null) {
                $paramSets[(string) $barcode] = ['barcodeNo' => $barcode];
            }
        }

        $fetchedItems = $service->fetchMany($user, '/invoices/{barcodeNo}/items', $paramSets);

        return $this->buildResult(
            $service,
            $user,
            $arguments,
            $period,
            $groupBy,
            $invoices,
            $fetchedItems,
            ['fetched' => $fetched, 'matched' => $matched, 'truncated' => $truncated]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $invoices
     * @param array{responses: array<string|int, array<mixed>>, errors: array<string|int, string>, calls: int} $fetchedItems
     * @param array{fetched: int, matched: int, truncated: bool} $counts
     * @return array<string, mixed>
     */
    private function buildResult(
        CisboxApiService $service,
        $user,
        array $arguments,
        array $period,
        array $groupBy,
        array $invoices,
        array $fetchedItems,
        array $counts
    ): array {
        $lineFilters = array_filter([
            'account' => $arguments['account'] ?? null,
            'code1' => $arguments['code1'] ?? null,
            'code2' => $arguments['code2'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $groups = [];
        $groupInvoices = [];
        $netTotal = 0.0;
        $lineCount = 0;
        $grossTotal = 0.0;
        $withoutAccounting = [];
        $withoutAccount = ['net' => 0.0, 'lines' => 0];
        $withoutCode1 = ['net' => 0.0, 'lines' => 0];
        $ratioIssues = [];
        $statusCount = [];

        foreach ($invoices as $invoice) {
            $barcode = (string) ($invoice['barcodeNo'] ?? '');
            $gross = (float) ($invoice['amountGross'] ?? 0);
            $grossTotal += $gross;

            $status = (string) ($invoice['accountingStatus'] ?? 'UNBEKANNT');
            $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;

            if (!isset($fetchedItems['responses'][$barcode])) {
                continue; // Fehlgeschlagene Abrufe stehen unten unter "errors".
            }

            $lines = $this->accountingLines($service->items($fetchedItems['responses'][$barcode]));

            if ($lines === []) {
                $withoutAccounting[] = [
                    'barcodeNo' => $invoice['barcodeNo'] ?? null,
                    'invoiceNo' => $invoice['invoiceNo'] ?? null,
                    'creditorNo' => $invoice['creditorNo'] ?? null,
                    'amountGross' => round($gross, 2),
                    'accountingStatus' => $status,
                ];

                continue;
            }

            $invoiceNet = 0.0;

            foreach ($lines as $line) {
                $price = (float) ($line['price'] ?? 0);
                $invoiceNet += $price;

                if (($line['account'] ?? null) === null || $line['account'] === '') {
                    $withoutAccount['net'] += $price;
                    $withoutAccount['lines']++;
                }

                if (($line['code1'] ?? null) === null || $line['code1'] === '') {
                    $withoutCode1['net'] += $price;
                    $withoutCode1['lines']++;
                }

                foreach ($lineFilters as $field => $wanted) {
                    if ((string) ($line[$field] ?? '') !== (string) $wanted) {
                        continue 2;
                    }
                }

                $key = [];

                foreach ($groupBy as $dimension) {
                    [$source, $field] = self::DIMENSIONS[$dimension];
                    $value = $source === 'line' ? ($line[$field] ?? null) : ($invoice[$field] ?? null);
                    $key[$dimension] = $value === '' ? null : $value;
                }

                $hash = json_encode($key);

                if (!isset($groups[$hash])) {
                    $groups[$hash] = $key + ['net' => 0.0, 'lines' => 0];
                    $groupInvoices[$hash] = [];
                }

                $groups[$hash]['net'] += $price;
                $groups[$hash]['lines']++;
                $groupInvoices[$hash][$barcode] = true;

                $netTotal += $price;
                $lineCount++;
            }

            // Nur ohne Zeilenfilter aussagekräftig: Sonst wäre der Rest der
            // Rechnung gar nicht mitgezählt und jede Rechnung sähe falsch aus.
            if ($lineFilters === [] && $invoiceNet != 0.0 && $gross != 0.0) {
                $ratio = $gross / $invoiceNet;

                if ($ratio < self::RATIO_MIN || $ratio > self::RATIO_MAX) {
                    $ratioIssues[] = [
                        'barcodeNo' => $invoice['barcodeNo'] ?? null,
                        'invoiceNo' => $invoice['invoiceNo'] ?? null,
                        'creditorNo' => $invoice['creditorNo'] ?? null,
                        'amountGross' => round($gross, 2),
                        'accounting_net' => round($invoiceNet, 2),
                        'ratio' => round($ratio, 4),
                        'accountingStatus' => $status,
                    ];
                }
            }
        }

        [$names, $nameCalls] = ($arguments['resolve_names'] ?? true)
            ? $this->resolveNames($service, $user, $groupBy, $groups)
            : [[], 0];

        return array_filter([
            'filter' => array_filter($period + [
                'debtorNo' => $arguments['debtorNo'] ?? null,
                'creditorNo' => $arguments['creditorNo'] ?? null,
            ] + $lineFilters, static fn ($v) => $v !== null && $v !== ''),
            'group_by' => $groupBy,
            'invoices' => array_filter([
                'in_period' => $counts['fetched'],
                'matched' => $counts['matched'],
                'evaluated' => count($invoices),
                'gross_total' => round($grossTotal, 2),
                'by_accounting_status' => $statusCount,
                'truncated' => $counts['truncated'] ?: null,
            ], static fn ($v) => $v !== null),
            'accounting' => [
                'net_total' => round($netTotal, 2),
                'lines' => $lineCount,
                'groups' => count($groups),
                'hint' => $lineFilters === []
                    ? 'Die Kontierung summiert netto, gross_total ist brutto — die Differenz ist die Steuer.'
                    : 'net_total zählt nur die gefilterten Zeilen; gross_total nennt weiterhin den '
                        . 'vollen Rechnungsbetrag der getroffenen Belege. Die beiden gehören hier '
                        . 'nicht zusammen.',
            ],
            'groups' => $this->renderGroups($groups, $groupInvoices, $groupBy, $names, $arguments, $netTotal),
            // Die Hinweise beschreiben den ganzen Zeitraum, nicht die
            // gefilterten Zeilen. In einer Detailabfrage stünden sie also
            // neben Zahlen, auf die sie sich nicht beziehen.
            'issues' => $lineFilters === []
                ? $this->renderIssues($withoutAccounting, $withoutAccount, $withoutCode1, $ratioIssues)
                : ['note' => 'Hinweise zur Datenqualität gelten für den ganzen Zeitraum und stehen '
                    . 'deshalb nur in der Abfrage ohne account/code1/code2-Filter.'],
            'errors' => $fetchedItems['errors'] ?: null,
            'api_calls' => 1 + $fetchedItems['calls'] + $nameCalls,
        ], static fn ($v) => $v !== null);
    }

    /**
     * Zieht die Kontierungszeilen aus den Positionen.
     *
     * Eine Position kann auf mehrere Konten und Kostenstellen aufgeteilt sein —
     * die Aufteilung steht in "accounting", die Position selbst trägt keine.
     *
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function accountingLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach ($item['accounting'] ?? [] as $entry) {
                if (is_array($entry)) {
                    $lines[] = $entry;
                }
            }
        }

        return $lines;
    }

    /**
     * Bezeichnungen zu Konten und Kostenstellen.
     *
     * Die Listen werden am Stück geholt statt je Treffer einzeln: 4.500 Konten
     * passen in drei Aufrufe, 130 Kostenstellen in einen. Einzelabrufe wären
     * je Gruppe einer.
     *
     * @param array<int, string> $groupBy
     * @param array<string, array<string, mixed>> $groups
     * @return array{0: array<string, array<string, string>>, 1: int} Namen und verbrauchte Aufrufe
     */
    private function resolveNames(CisboxApiService $service, $user, array $groupBy, array $groups): array
    {
        $sources = [
            'account' => ['/masterdata/accounts', 'accountNo'],
            'code1' => ['/masterdata/code1', 'code1No'],
            'code2' => ['/masterdata/code2', 'code2No'],
            'code3' => ['/masterdata/code3', 'code3No'],
        ];

        $names = [];
        $calls = 0;

        foreach ($groupBy as $dimension) {
            if (!isset($sources[$dimension]) || $groups === []) {
                continue;
            }

            [$path, $field] = $sources[$dimension];

            try {
                $response = $service->fetchAll($user, $path, [], [], CisboxApiService::MAX_ITEMS_PER_PAGE, 5);
                $calls += (int) ($response['meta']['fetchedPages'] ?? 1);
                $all = $service->items($response);
            } catch (CisboxApiException) {
                // Namen sind Beiwerk. Fehlen sie, bleibt die Auswertung gültig.
                continue;
            }

            $map = [];

            foreach ($all as $record) {
                if (is_array($record) && ($record[$field] ?? null) !== null) {
                    $map[(string) $record[$field]] = (string) ($record['name'] ?? '');
                }
            }

            $names[$dimension] = $map;
        }

        return [$names, $calls];
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     * @param array<string, array<string, bool>> $groupInvoices
     * @param array<int, string> $groupBy
     * @param array<string, array<string, string>> $names
     * @param array<string, mixed> $arguments
     * @return array<int, array<string, mixed>>
     */
    private function renderGroups(
        array $groups,
        array $groupInvoices,
        array $groupBy,
        array $names,
        array $arguments,
        float $netTotal
    ): array {
        $rows = [];

        foreach ($groups as $hash => $group) {
            $row = [];

            foreach ($groupBy as $dimension) {
                $value = $group[$dimension];
                $row[$dimension] = $value;

                $name = $value !== null ? ($names[$dimension][(string) $value] ?? null) : null;

                if ($name !== null && $name !== '') {
                    $row[$dimension . '_name'] = $name;
                }
            }

            $row['net'] = round($group['net'], 2);
            $row['lines'] = $group['lines'];
            $row['invoices'] = count($groupInvoices[$hash]);

            if ($netTotal != 0.0) {
                $row['share_pct'] = round($group['net'] / $netTotal * 100, 1);
            }

            $rows[] = $row;
        }

        usort($rows, static fn ($a, $b) => abs($b['net']) <=> abs($a['net']));

        $minAmount = (float) ($arguments['min_amount'] ?? 0);
        $top = (int) ($arguments['top'] ?? self::DEFAULT_TOP);
        $top = max(1, $top);

        $kept = [];
        $restNet = 0.0;
        $restLines = 0;
        $restGroups = 0;

        foreach ($rows as $row) {
            if (count($kept) < $top && abs($row['net']) >= $minAmount) {
                $kept[] = $row;

                continue;
            }

            $restNet += $row['net'];
            $restLines += $row['lines'];
            $restGroups++;
        }

        if ($restGroups > 0) {
            $kept[] = [
                'summary' => "weitere {$restGroups} Gruppen",
                'net' => round($restNet, 2),
                'lines' => $restLines,
            ];
        }

        return $kept;
    }

    /**
     * @param array<int, array<string, mixed>> $withoutAccounting
     * @param array{net: float, lines: int} $withoutAccount
     * @param array{net: float, lines: int} $withoutCode1
     * @param array<int, array<string, mixed>> $ratioIssues
     * @return array<string, mixed>|null
     */
    private function renderIssues(
        array $withoutAccounting,
        array $withoutAccount,
        array $withoutCode1,
        array $ratioIssues
    ): ?array {
        $issues = [];

        if ($withoutAccounting !== []) {
            usort($withoutAccounting, static fn ($a, $b) => $b['amountGross'] <=> $a['amountGross']);

            $issues['invoices_without_accounting'] = [
                'count' => count($withoutAccounting),
                'gross_total' => round(array_sum(array_column($withoutAccounting, 'amountGross')), 2),
                'invoices' => array_slice($withoutAccounting, 0, 20),
            ];
        }

        if ($withoutAccount['lines'] > 0) {
            $issues['lines_without_account'] = [
                'lines' => $withoutAccount['lines'],
                'net' => round($withoutAccount['net'], 2),
            ];
        }

        if ($withoutCode1['lines'] > 0) {
            $issues['lines_without_code1'] = [
                'lines' => $withoutCode1['lines'],
                'net' => round($withoutCode1['net'], 2),
            ];
        }

        if ($ratioIssues !== []) {
            usort($ratioIssues, static fn ($a, $b) => $b['amountGross'] <=> $a['amountGross']);

            $issues['gross_net_mismatch'] = [
                'count' => count($ratioIssues),
                'hint' => 'Verhältnis brutto/netto außerhalb ' . self::RATIO_MIN . '–' . self::RATIO_MAX
                    . '. Unter 1 kontiert die Rechnung mehr, als sie kostet; über 1,19 läge mehr '
                    . 'Steuer darauf, als es gibt.',
                'invoices' => array_slice($ratioIssues, 0, 20),
            ];
        }

        return $issues ?: null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['cisbox', 'kontierung', 'sachkonto', 'kostenstelle', 'auswertung', 'summe', 'monatsabschluss'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
        ];
    }
}
