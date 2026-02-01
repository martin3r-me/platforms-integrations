<?php

namespace Platform\Integrations\DTOs\Sipgate;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

/**
 * DTO für Sipgate Fax-Requests
 *
 * Validiert und kapselt die Daten für ein ausgehendes Fax.
 */
class SipgateFaxRequest
{
    public const MAX_FILE_SIZE_MB = 10;

    public function __construct(
        public readonly string $faxlineId,
        public readonly string $recipient,
        public readonly string $base64Content,
        public readonly ?string $filename = null,
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
            faxlineId: $data['faxline_id'] ?? $data['faxlineId'],
            recipient: $data['recipient'],
            base64Content: $data['base64_content'] ?? $data['base64Content'],
            filename: $data['filename'] ?? null,
        );
    }

    /**
     * Erstellt den Validator für die Request-Daten
     */
    public static function validator(array $data): Validator
    {
        $maxSize = self::MAX_FILE_SIZE_MB * 1024 * 1024; // In Bytes

        return ValidatorFacade::make($data, [
            'faxline_id' => ['required_without:faxlineId', 'string', 'max:50'],
            'faxlineId' => ['required_without:faxline_id', 'string', 'max:50'],
            'recipient' => ['required', 'string', 'regex:/^\+?[0-9]+$/', 'min:5', 'max:50'],
            'base64_content' => [
                'required_without:base64Content',
                'string',
                function ($attribute, $value, $fail) use ($maxSize) {
                    // Prüfe Größe des dekodierten Inhalts
                    $decoded = base64_decode($value, true);
                    if ($decoded === false) {
                        $fail('Der Inhalt ist kein gültiger Base64-String.');
                        return;
                    }
                    if (strlen($decoded) > $maxSize) {
                        $fail('Die Datei darf maximal ' . self::MAX_FILE_SIZE_MB . ' MB groß sein.');
                    }
                    // Prüfe PDF-Header
                    if (substr($decoded, 0, 4) !== '%PDF') {
                        $fail('Die Datei muss ein PDF sein.');
                    }
                },
            ],
            'base64Content' => [
                'required_without:base64_content',
                'string',
                function ($attribute, $value, $fail) use ($maxSize) {
                    $decoded = base64_decode($value, true);
                    if ($decoded === false) {
                        $fail('Der Inhalt ist kein gültiger Base64-String.');
                        return;
                    }
                    if (strlen($decoded) > $maxSize) {
                        $fail('Die Datei darf maximal ' . self::MAX_FILE_SIZE_MB . ' MB groß sein.');
                    }
                    if (substr($decoded, 0, 4) !== '%PDF') {
                        $fail('Die Datei muss ein PDF sein.');
                    }
                },
            ],
            'filename' => ['nullable', 'string', 'max:255', 'regex:/^[\w\-. ]+\.pdf$/i'],
        ], [
            'faxline_id.required_without' => 'Die Faxline-ID ist erforderlich.',
            'faxlineId.required_without' => 'Die Faxline-ID ist erforderlich.',
            'recipient.required' => 'Die Empfängernummer ist erforderlich.',
            'recipient.regex' => 'Die Empfängernummer enthält ungültige Zeichen.',
            'base64_content.required_without' => 'Der PDF-Inhalt ist erforderlich.',
            'base64Content.required_without' => 'Der PDF-Inhalt ist erforderlich.',
            'filename.regex' => 'Der Dateiname muss mit .pdf enden und darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
        ]);
    }

    /**
     * Erstellt eine Instanz aus einer Datei
     */
    public static function fromFile(string $faxlineId, string $recipient, string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Datei nicht gefunden: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $base64 = base64_encode($content);
        $filename = basename($filePath);

        return new self(
            faxlineId: $faxlineId,
            recipient: $recipient,
            base64Content: $base64,
            filename: $filename,
        );
    }

    /**
     * Konvertiert zu Array für API-Request
     */
    public function toArray(): array
    {
        $data = [
            'faxlineId' => $this->faxlineId,
            'recipient' => $this->recipient,
            'base64Content' => $this->base64Content,
        ];

        if ($this->filename) {
            $data['filename'] = $this->filename;
        }

        return $data;
    }

    /**
     * Gibt die Dateigröße in Bytes zurück
     */
    public function getFileSizeBytes(): int
    {
        return strlen(base64_decode($this->base64Content));
    }

    /**
     * Gibt die Dateigröße formatiert zurück
     */
    public function getFileSizeFormatted(): string
    {
        $bytes = $this->getFileSizeBytes();

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }
}
