<?php

namespace Platform\Integrations\DTOs\Sipgate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für Sipgate Contact-Requests
 *
 * Validiert und kapselt die Daten für Kontakte.
 */
class SipgateContactRequest
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $family = null,
        public readonly ?string $given = null,
        public readonly ?string $organization = null,
        public readonly ?string $email = null,
        public readonly ?array $numbers = null,
        public readonly ?string $picture = null,
        public readonly ?array $addresses = null,
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

        return new self(
            name: $data['name'] ?? null,
            family: $data['family'] ?? null,
            given: $data['given'] ?? null,
            organization: $data['organization'] ?? null,
            email: $data['email'] ?? null,
            numbers: $data['numbers'] ?? null,
            picture: $data['picture'] ?? null,
            addresses: $data['addresses'] ?? null,
        );
    }

    /**
     * Erstellt den Validator für die Request-Daten
     */
    public static function validator(array $data): Validator
    {
        return ValidatorFacade::make($data, [
            'name' => ['nullable', 'string', 'max:255'],
            'family' => ['nullable', 'string', 'max:255'],
            'given' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'numbers' => ['nullable', 'array'],
            'numbers.*.number' => ['required_with:numbers', 'string', 'regex:/^\+?[0-9\s\-\(\)]+$/'],
            'numbers.*.type' => ['nullable', 'string', 'in:CELL,WORK,HOME,FAX,PAGER,OTHER'],
            'picture' => ['nullable', 'url', 'max:2048'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.street' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.postalCode' => ['nullable', 'string', 'max:20'],
            'addresses.*.country' => ['nullable', 'string', 'max:100'],
            'addresses.*.type' => ['nullable', 'string', 'in:WORK,HOME,OTHER'],
        ], [
            'email.email' => 'Die E-Mail-Adresse ist ungültig.',
            'numbers.*.number.regex' => 'Die Telefonnummer enthält ungültige Zeichen.',
            'numbers.*.type.in' => 'Der Nummerntyp muss einer der folgenden sein: CELL, WORK, HOME, FAX, PAGER, OTHER.',
            'addresses.*.type.in' => 'Der Adresstyp muss einer der folgenden sein: WORK, HOME, OTHER.',
        ]);
    }

    /**
     * Erstellt eine Instanz für einen einfachen Kontakt
     */
    public static function simple(string $name, string $phoneNumber, ?string $email = null): self
    {
        return new self(
            name: $name,
            email: $email,
            numbers: [['number' => $phoneNumber, 'type' => 'CELL']],
        );
    }

    /**
     * Konvertiert zu Array für API-Request
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->family !== null) {
            $data['family'] = $this->family;
        }

        if ($this->given !== null) {
            $data['given'] = $this->given;
        }

        if ($this->organization !== null) {
            $data['organization'] = $this->organization;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        if ($this->numbers !== null) {
            $data['numbers'] = $this->numbers;
        }

        if ($this->picture !== null) {
            $data['picture'] = $this->picture;
        }

        if ($this->addresses !== null) {
            $data['addresses'] = $this->addresses;
        }

        return $data;
    }

    /**
     * Gibt den Anzeigenamen zurück
     */
    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->given || $this->family) {
            return trim($this->given . ' ' . $this->family);
        }

        if ($this->organization) {
            return $this->organization;
        }

        return 'Unbekannt';
    }

    /**
     * Gibt die primäre Telefonnummer zurück
     */
    public function getPrimaryNumber(): ?string
    {
        if (!$this->numbers || empty($this->numbers)) {
            return null;
        }

        // Priorisiere CELL, dann WORK, dann den ersten Eintrag
        foreach (['CELL', 'WORK', 'HOME'] as $type) {
            foreach ($this->numbers as $number) {
                if (($number['type'] ?? 'OTHER') === $type) {
                    return $number['number'];
                }
            }
        }

        return $this->numbers[0]['number'] ?? null;
    }
}
