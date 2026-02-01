<?php

namespace Platform\Integrations\DTOs\Sipgate;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für Sipgate History-Filter
 *
 * Validiert und kapselt die Filter-Parameter für die Anrufhistorie.
 */
class SipgateHistoryFilter
{
    public const VALID_TYPES = ['CALL', 'SMS', 'FAX', 'VOICEMAIL'];
    public const VALID_DIRECTIONS = ['INCOMING', 'OUTGOING', 'MISSED'];
    public const MAX_LIMIT = 5000;
    public const DEFAULT_LIMIT = 100;

    public function __construct(
        public readonly ?array $types = null,
        public readonly ?array $directions = null,
        public readonly ?bool $archived = null,
        public readonly ?bool $starred = null,
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly ?string $phonenumber = null,
        public readonly int $limit = self::DEFAULT_LIMIT,
        public readonly int $offset = 0,
    ) {
    }

    /**
     * Erstellt eine Instanz aus Request-Daten mit Validierung
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function fromRequest(array $data): self
    {
        $validator = self::validator($data);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Types parsen
        $types = null;
        if (isset($data['types'])) {
            $types = is_array($data['types']) ? $data['types'] : explode(',', $data['types']);
            $types = array_map('strtoupper', $types);
        }

        // Directions parsen
        $directions = null;
        if (isset($data['directions'])) {
            $directions = is_array($data['directions']) ? $data['directions'] : explode(',', $data['directions']);
            $directions = array_map('strtoupper', $directions);
        }

        // Booleans parsen
        $archived = null;
        if (isset($data['archived'])) {
            $archived = filter_var($data['archived'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $starred = null;
        if (isset($data['starred'])) {
            $starred = filter_var($data['starred'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        // Dates parsen
        $from = isset($data['from']) ? Carbon::parse($data['from']) : null;
        $to = isset($data['to']) ? Carbon::parse($data['to']) : null;

        return new self(
            types: $types,
            directions: $directions,
            archived: $archived,
            starred: $starred,
            from: $from,
            to: $to,
            phonenumber: $data['phonenumber'] ?? null,
            limit: min((int) ($data['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT),
            offset: (int) ($data['offset'] ?? 0),
        );
    }

    /**
     * Erstellt den Validator für die Request-Daten
     */
    public static function validator(array $data): Validator
    {
        return ValidatorFacade::make($data, [
            'types' => ['nullable', 'string'],
            'types.*' => ['string', 'in:' . implode(',', self::VALID_TYPES)],
            'directions' => ['nullable', 'string'],
            'directions.*' => ['string', 'in:' . implode(',', self::VALID_DIRECTIONS)],
            'archived' => ['nullable'],
            'starred' => ['nullable'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'phonenumber' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ], [
            'types.*.in' => 'Gültige Typen sind: ' . implode(', ', self::VALID_TYPES),
            'directions.*.in' => 'Gültige Richtungen sind: ' . implode(', ', self::VALID_DIRECTIONS),
            'to.after_or_equal' => 'Das Enddatum muss nach dem Startdatum liegen.',
            'phonenumber.regex' => 'Die Telefonnummer enthält ungültige Zeichen.',
            'limit.max' => 'Das Limit darf maximal ' . self::MAX_LIMIT . ' sein.',
        ]);
    }

    /**
     * Konvertiert zu Array für API-Request
     */
    public function toArray(): array
    {
        $data = [
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];

        if ($this->types) {
            $data['types'] = implode(',', $this->types);
        }

        if ($this->directions) {
            $data['directions'] = implode(',', $this->directions);
        }

        if ($this->archived !== null) {
            $data['archived'] = $this->archived ? 'true' : 'false';
        }

        if ($this->starred !== null) {
            $data['starred'] = $this->starred ? 'true' : 'false';
        }

        if ($this->from) {
            $data['from'] = $this->from->toIso8601String();
        }

        if ($this->to) {
            $data['to'] = $this->to->toIso8601String();
        }

        if ($this->phonenumber) {
            $data['phonenumber'] = $this->phonenumber;
        }

        return $data;
    }

    /**
     * Erstellt einen Filter für nur Anrufe
     */
    public static function callsOnly(int $limit = self::DEFAULT_LIMIT): self
    {
        return new self(
            types: ['CALL'],
            limit: $limit,
        );
    }

    /**
     * Erstellt einen Filter für verpasste Anrufe
     */
    public static function missedCalls(int $limit = self::DEFAULT_LIMIT): self
    {
        return new self(
            types: ['CALL'],
            directions: ['MISSED'],
            limit: $limit,
        );
    }

    /**
     * Erstellt einen Filter für einen Zeitraum
     */
    public static function forPeriod(Carbon $from, Carbon $to, int $limit = self::DEFAULT_LIMIT): self
    {
        return new self(
            from: $from,
            to: $to,
            limit: $limit,
        );
    }

    /**
     * Erstellt einen Filter für heute
     */
    public static function today(int $limit = self::DEFAULT_LIMIT): self
    {
        return new self(
            from: Carbon::today(),
            to: Carbon::now(),
            limit: $limit,
        );
    }

    /**
     * Erstellt einen Filter für die letzten 7 Tage
     */
    public static function lastWeek(int $limit = self::DEFAULT_LIMIT): self
    {
        return new self(
            from: Carbon::now()->subDays(7),
            to: Carbon::now(),
            limit: $limit,
        );
    }
}
