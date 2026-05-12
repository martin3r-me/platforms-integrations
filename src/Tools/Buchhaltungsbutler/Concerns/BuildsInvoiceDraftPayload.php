<?php

namespace Platform\Integrations\Tools\Buchhaltungsbutler\Concerns;

/**
 * Stellt Schema und Body-Mapping für /invoices/create/draft bereit.
 *
 * Die BuchhaltungsButler-API verlangt Positionen als parallele Arrays
 * (item_name[], item_amount[], ...). Für LLMs ist eine Liste von Objekten
 * deutlich angenehmer — diese Brücke baut der Trait.
 */
trait BuildsInvoiceDraftPayload
{
    /**
     * Liefert das gemeinsame JSON-Schema für die drei Beleg-Entwurf-Tools.
     *
     * @param string $documentLabel "Rechnung" | "Angebot" | "Gutschrift" — wird in die Beschreibungen gemerged.
     */
    protected function invoiceDraftSchema(string $documentLabel): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'connection_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer spezifischen BuchhaltungsButler-Connection. Ohne Angabe wird die Standard-Connection verwendet.',
                ],
                'show_prices_type' => [
                    'type' => 'string',
                    'enum' => ['net', 'gross'],
                    'description' => "PFLICHT. 'net' (Netto-{$documentLabel}) oder 'gross' (Brutto-{$documentLabel}).",
                ],
                'company_name' => [
                    'type' => 'string',
                    'description' => 'PFLICHT. Firmenname des Empfängers.',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'PFLICHT laut API (Format YYYY-MM-DD), wird vom BuchhaltungsButler /invoices/create/draft-Endpoint aber empirisch ignoriert und immer auf das heutige Datum gesetzt. Der User muss das Datum nach dem Anlegen bei Bedarf im UI anpassen. Trotzdem mitschicken — die API lehnt den Request ohne date ab.',
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'PFLICHT. ' . $documentLabel . 'spositionen (mindestens eine).',
                    'items' => [
                        'type' => 'object',
                        'required' => ['name', 'amount', 'unit', 'vat', 'single_price'],
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'PFLICHT. Bezeichnung der Position, z.B. "Beratungsleistung".'],
                            'amount' => ['type' => 'number', 'description' => 'PFLICHT. Menge, z.B. 10.'],
                            'unit' => ['type' => 'string', 'description' => 'PFLICHT. Einheit, z.B. "Std." oder "Stk.".'],
                            'vat' => ['type' => 'number', 'description' => 'PFLICHT. MwSt.-Satz in Prozent (0–100), z.B. 19, 7 oder 0.'],
                            'single_price' => ['type' => 'number', 'description' => 'PFLICHT. Einzelpreis. Netto oder Brutto je nach show_prices_type.'],
                            'description' => ['type' => 'string', 'description' => 'Optional: ausführliche Beschreibung der Position.'],
                        ],
                    ],
                ],
                'contact_person_name' => ['type' => 'string', 'description' => 'Ansprechpartner beim Empfänger.'],
                'street' => ['type' => 'string', 'description' => 'Straße + Hausnummer des Empfängers.'],
                'additional_addressline' => ['type' => 'string', 'description' => 'Zusatzzeile zur Adresse.'],
                'zip' => ['type' => 'string', 'description' => 'PLZ.'],
                'city' => ['type' => 'string', 'description' => 'Ort.'],
                'country' => ['type' => 'string', 'description' => 'Land. Entweder ISO-Code (z.B. "DE") oder deutscher Ländername (z.B. "Deutschland").'],
                'email' => ['type' => 'string', 'description' => 'E-Mail-Adresse des Empfängers (für späteren Versand).'],
                'customer_number' => ['type' => 'string', 'description' => 'Kundennummer des Empfängers.'],
                'date_of_supply' => ['type' => 'string', 'description' => 'Liefer-/Leistungsdatum (YYYY-MM-DD oder Zeitraum als Text). DATEV-konform muss es ≤ date sein, sonst wird es ignoriert.'],
                'correspondence' => ['type' => 'string', 'description' => 'Anschreiben-/Einleitungstext.'],
                'final_provisions' => ['type' => 'string', 'description' => 'Schlusstext.'],
                'discount_type' => ['type' => 'string', 'enum' => ['percent', 'EUR'], 'description' => 'Rabatt-Typ.'],
                'discount_value' => ['type' => 'number', 'description' => 'Rabatt-Wert.'],
                'payment_conditions' => ['type' => 'string', 'description' => 'Zahlungsbedingungen-Text.'],
                'show_bankdata' => ['type' => 'boolean', 'description' => 'Bankdaten auf dem PDF anzeigen.'],
                'show_contactdata' => ['type' => 'boolean', 'description' => 'Kontaktdaten auf dem PDF anzeigen.'],
                'recurring_interval' => ['type' => 'string', 'enum' => ['weekly', 'monthly', 'quarterly', 'yearly'], 'description' => 'Falls wiederkehrend.'],
                'recurring_date_next' => ['type' => 'string', 'description' => 'Nächstes Ausführungsdatum bei wiederkehrender ' . $documentLabel . '. Pflicht wenn recurring_interval gesetzt ist.'],
            ],
            'required' => ['show_prices_type', 'company_name', 'date', 'items'],
        ];
    }

    /**
     * Wandelt die Tool-Argumente in den BuchhaltungsButler-API-Body um.
     * Konvertiert das items-Array in parallele item_*-Arrays.
     */
    protected function buildInvoiceDraftBody(array $args): array
    {
        $items = $args['items'] ?? [];

        $body = [
            'show_prices_type'   => $args['show_prices_type'],
            'company_name'       => $args['company_name'],
            'date'               => $args['date'],
            'item_name'          => array_map(static fn ($i) => (string) ($i['name'] ?? ''), $items),
            'item_amount'        => array_map(static fn ($i) => (string) ($i['amount'] ?? ''), $items),
            'item_unit'          => array_map(static fn ($i) => (string) ($i['unit'] ?? ''), $items),
            'item_vat'           => array_map(static fn ($i) => (string) ($i['vat'] ?? ''), $items),
            'item_single_price'  => array_map(static fn ($i) => (string) ($i['single_price'] ?? ''), $items),
        ];

        $descriptions = array_map(static fn ($i) => (string) ($i['description'] ?? ''), $items);
        if (array_filter($descriptions, static fn ($d) => $d !== '')) {
            $body['item_description'] = $descriptions;
        }

        $passthrough = [
            'contact_person_name', 'street', 'additional_addressline', 'zip', 'city', 'country',
            'email', 'customer_number', 'date_of_supply', 'correspondence', 'final_provisions',
            'discount_type', 'discount_value', 'payment_conditions',
            'show_bankdata', 'show_contactdata',
            'recurring_interval', 'recurring_date_next',
        ];
        foreach ($passthrough as $field) {
            if (array_key_exists($field, $args) && $args[$field] !== null && $args[$field] !== '') {
                $body[$field] = $args[$field];
            }
        }

        return $body;
    }
}
