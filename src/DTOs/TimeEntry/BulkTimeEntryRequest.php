<?php

namespace Platform\Integrations\DTOs\TimeEntry;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für Bulk-TimeEntry-Requests (mehrere Zeitstempel auf einmal)
 *
 * Validiert die Gesamtstruktur des Bulk-Requests.
 * Einzelne Einträge werden über TimeEntryRequest validiert.
 */
class BulkTimeEntryRequest
{
    public const MAX_BULK_SIZE = 100;

    /**
     * @param TimeEntryRequest[] $entries
     */
    public function __construct(
        public readonly array $entries,
    ) {
    }

    /**
     * Erstellt eine Instanz aus Request-Daten mit Validierung
     *
     * Validiert zuerst die Gesamtstruktur, dann jeden einzelnen Eintrag.
     * Gibt detaillierte Fehler pro Eintrag zurück.
     *
     * @return array{dto: self|null, errors: array}
     */
    public static function fromRequest(array $data): array
    {
        // Schritt 1: Gesamtstruktur validieren
        $structureValidator = self::structureValidator($data);

        if ($structureValidator->fails()) {
            return [
                'dto' => null,
                'errors' => [
                    'structure' => $structureValidator->errors()->toArray(),
                ],
            ];
        }

        $items = $data['entries'];
        $entries = [];
        $itemErrors = [];
        $successCount = 0;

        // Schritt 2: Jeden Eintrag einzeln validieren
        foreach ($items as $index => $item) {
            try {
                $entries[$index] = TimeEntryRequest::fromRequest($item);
                $successCount++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $itemErrors[$index] = $e->errors();
            }
        }

        // Wenn Fehler vorhanden, DTO trotzdem mit gültigen Einträgen erstellen
        if (!empty($itemErrors)) {
            return [
                'dto' => !empty($entries) ? new self($entries) : null,
                'errors' => [
                    'entries' => $itemErrors,
                    'summary' => [
                        'total' => count($items),
                        'valid' => $successCount,
                        'invalid' => count($itemErrors),
                    ],
                ],
            ];
        }

        return [
            'dto' => new self($entries),
            'errors' => [],
        ];
    }

    /**
     * Validiert die Gesamtstruktur des Bulk-Requests
     */
    public static function structureValidator(array $data): Validator
    {
        return ValidatorFacade::make($data, [
            'entries' => ['required', 'array', 'min:1', 'max:' . self::MAX_BULK_SIZE],
            'entries.*' => ['required', 'array'],
        ], [
            'entries.required' => 'Das Feld "entries" ist erforderlich.',
            'entries.array' => 'Das Feld "entries" muss ein Array sein.',
            'entries.min' => 'Es muss mindestens ein Eintrag vorhanden sein.',
            'entries.max' => 'Es dürfen maximal ' . self::MAX_BULK_SIZE . ' Einträge auf einmal erstellt werden.',
            'entries.*.required' => 'Jeder Eintrag muss ein Objekt sein.',
            'entries.*.array' => 'Jeder Eintrag muss ein Objekt sein.',
        ]);
    }

    /**
     * Gibt die Anzahl der Einträge zurück
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Konvertiert alle Einträge zu Arrays
     */
    public function toArray(): array
    {
        return array_map(fn (TimeEntryRequest $entry) => $entry->toArray(), $this->entries);
    }
}
