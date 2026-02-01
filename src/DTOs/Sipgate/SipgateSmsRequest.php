<?php

namespace Platform\Integrations\DTOs\Sipgate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für Sipgate SMS-Requests
 *
 * Validiert und kapselt die Daten für eine ausgehende SMS.
 */
class SipgateSmsRequest
{
    public const MAX_MESSAGE_LENGTH = 1600; // Max 10 SMS-Teile

    public function __construct(
        public readonly string $smsId,
        public readonly string $recipient,
        public readonly string $message,
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
            smsId: $data['sms_id'] ?? $data['smsId'],
            recipient: $data['recipient'],
            message: $data['message'],
        );
    }

    /**
     * Erstellt den Validator für die Request-Daten
     */
    public static function validator(array $data): Validator
    {
        return ValidatorFacade::make($data, [
            'sms_id' => ['required_without:smsId', 'string', 'max:50'],
            'smsId' => ['required_without:sms_id', 'string', 'max:50'],
            'recipient' => ['required', 'string', 'regex:/^\+?[0-9]+$/', 'min:5', 'max:50'],
            'message' => ['required', 'string', 'max:' . self::MAX_MESSAGE_LENGTH],
        ], [
            'sms_id.required_without' => 'Die SMS-Extension-ID ist erforderlich.',
            'smsId.required_without' => 'Die SMS-Extension-ID ist erforderlich.',
            'recipient.required' => 'Die Empfängernummer ist erforderlich.',
            'recipient.regex' => 'Die Empfängernummer enthält ungültige Zeichen.',
            'message.required' => 'Die Nachricht darf nicht leer sein.',
            'message.max' => 'Die Nachricht darf maximal ' . self::MAX_MESSAGE_LENGTH . ' Zeichen lang sein.',
        ]);
    }

    /**
     * Konvertiert zu Array für API-Request
     */
    public function toArray(): array
    {
        return [
            'smsId' => $this->smsId,
            'recipient' => $this->recipient,
            'message' => $this->message,
        ];
    }

    /**
     * Berechnet die Anzahl der SMS-Teile
     */
    public function getPartCount(): int
    {
        $length = mb_strlen($this->message);

        // GSM-7: 160 Zeichen pro Teil, 153 wenn mehrteilig
        // UCS-2: 70 Zeichen pro Teil, 67 wenn mehrteilig
        $isGsm7 = $this->isGsm7Compatible();

        if ($isGsm7) {
            return $length <= 160 ? 1 : (int) ceil($length / 153);
        }

        return $length <= 70 ? 1 : (int) ceil($length / 67);
    }

    /**
     * Prüft, ob die Nachricht GSM-7 kompatibel ist
     */
    protected function isGsm7Compatible(): bool
    {
        // GSM-7 Basic Character Set + Extension
        $gsm7Chars = "@£\$¥èéùìòÇ\nØø\rÅå_ÆæßÉ !\"#%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $extensionChars = "^{}\\[~]|€";

        for ($i = 0; $i < mb_strlen($this->message); $i++) {
            $char = mb_substr($this->message, $i, 1);
            if (mb_strpos($gsm7Chars, $char) === false && mb_strpos($extensionChars, $char) === false) {
                return false;
            }
        }

        return true;
    }
}
