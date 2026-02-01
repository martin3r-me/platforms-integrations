<?php

namespace Platform\Integrations\DTOs\Sipgate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für Sipgate Call-Requests (Click-to-Call)
 *
 * Validiert und kapselt die Daten für einen ausgehenden Anruf.
 */
class SipgateCallRequest
{
    public function __construct(
        public readonly string $caller,
        public readonly string $callee,
        public readonly ?string $callerId = null,
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
            caller: $data['caller'],
            callee: $data['callee'],
            callerId: $data['caller_id'] ?? $data['callerId'] ?? null,
        );
    }

    /**
     * Erstellt den Validator für die Request-Daten
     */
    public static function validator(array $data): Validator
    {
        return ValidatorFacade::make($data, [
            'caller' => ['required', 'string', 'max:100'],
            'callee' => ['required', 'string', 'regex:/^\+?[0-9\s\-\(\)]+$/', 'max:50'],
            'caller_id' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'max:50'],
            'callerId' => ['nullable', 'string', 'regex:/^\+?[0-9]+$/', 'max:50'],
        ], [
            'caller.required' => 'Die Absender-Nummer oder Device-ID ist erforderlich.',
            'callee.required' => 'Die Zielnummer ist erforderlich.',
            'callee.regex' => 'Die Zielnummer enthält ungültige Zeichen.',
            'caller_id.regex' => 'Die Caller-ID enthält ungültige Zeichen.',
        ]);
    }

    /**
     * Konvertiert zu Array für API-Request
     */
    public function toArray(): array
    {
        $data = [
            'caller' => $this->caller,
            'callee' => $this->callee,
        ];

        if ($this->callerId) {
            $data['callerId'] = $this->callerId;
        }

        return $data;
    }
}
