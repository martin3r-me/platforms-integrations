<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Hetzner Cloud API Fehler (API v1).
 *
 * Hetzner liefert Fehler einheitlich als:
 *   { "error": { "code": "unauthorized", "message": "unable to authenticate", "details": null } }
 *
 * @see https://docs.hetzner.cloud/reference/cloud
 */
class HetznerApiException extends Exception
{
    protected ?string $errorCode = null;

    protected ?array $responseData = null;

    /**
     * Wartezeit in Sekunden aus einer Rate-Limit-Antwort.
     *
     * Steht als Feld zur Verfügung, damit ein Aufrufer sie auswerten kann, ohne
     * den Meldungstext zu zerlegen.
     */
    protected ?int $retryAfter = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        201 => 'Created - Ressource wurde angelegt.',
        204 => 'No Content - Anfrage erfolgreich, keine Rückgabe.',
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Kein gültiges Hetzner Cloud API-Token übermittelt.',
        403 => 'Forbidden - Das Token hat keine Berechtigung für diese Ressource (Read-Only-Token?).',
        404 => 'Not Found - Ressource nicht gefunden.',
        409 => 'Conflict - Die Ressource ist gerade gesperrt oder in einem unpassenden Zustand.',
        422 => 'Unprocessable Entity - Ungültige Eingabe.',
        423 => 'Locked - Die Ressource ist gesperrt (laufende Aktion abwarten).',
        429 => 'Too Many Requests - Rate-Limit überschritten.',
        500 => 'Internal Server Error - Ein interner Serverfehler ist aufgetreten.',
        503 => 'Service Unavailable - Der Service ist vorübergehend nicht verfügbar.',
    ];

    /**
     * Häufige, von Hetzner dokumentierte Fehlercodes mit deutscher Erläuterung.
     */
    public const ERROR_CODE_HINTS = [
        'forbidden' => 'Keine Berechtigung — prüfe, ob das Token Schreibrechte hat.',
        'unauthorized' => 'Token ungültig oder abgelaufen.',
        'invalid_input' => 'Ungültige Eingabedaten — siehe "details".',
        'not_found' => 'Ressource existiert nicht (oder gehört zu einem anderen Projekt).',
        'locked' => 'Ressource ist gesperrt, weil bereits eine Aktion läuft.',
        'conflict' => 'Die Ressource befindet sich in einem Zustand, der die Aktion nicht erlaubt.',
        'rate_limit_exceeded' => 'Rate-Limit überschritten (Standard: 3600 Requests/Stunde je Projekt).',
        'resource_limit_exceeded' => 'Projekt-Limit erreicht (z.B. maximale Server-Anzahl).',
        'resource_unavailable' => 'Ressource derzeit nicht verfügbar (z.B. Servertyp am Standort ausverkauft).',
        'uniqueness_error' => 'Eine Ressource mit diesem Namen existiert bereits.',
        'protected' => 'Ressource ist geschützt (Löschschutz aktiv).',
        'server_not_stopped' => 'Der Server muss zuerst heruntergefahren werden.',
        'placement_error' => 'Server konnte nicht platziert werden (Placement Group).',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $errorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->errorCode = $errorCode;
        $this->responseData = $responseData;
    }

    public static function fromResponse(int $httpStatusCode, ?array $responseData = null): self
    {
        $error = $responseData['error'] ?? [];
        $code = is_array($error) ? ($error['code'] ?? null) : null;

        $message = (is_array($error) ? ($error['message'] ?? null) : null)
            ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode]
            ?? 'Unbekannter Fehler';

        if ($code && isset(self::ERROR_CODE_HINTS[$code])) {
            $message .= ' — ' . self::ERROR_CODE_HINTS[$code];
        }

        return new self($message, $httpStatusCode, $code ? (string) $code : null, $responseData);
    }

    public static function unauthorized(string $message = 'Ungültiges Hetzner Cloud API-Token.'): self
    {
        return new self($message, 401, 'unauthorized');
    }

    public static function forbidden(string $message = 'Das Hetzner-Token hat keine Berechtigung für diese Aktion (Read-Only-Token?).'): self
    {
        return new self($message, 403, 'forbidden');
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        $message = 'Hetzner Rate-Limit überschritten (Standard: 3600 Requests/Stunde je Projekt).';
        $message .= $retryAfter
            ? " Bitte in {$retryAfter} Sekunden erneut versuchen."
            : ' Bitte später erneut versuchen.';

        $exception = new self($message, 429, 'rate_limit_exceeded');
        $exception->retryAfter = $retryAfter;

        return $exception;
    }

    public static function connectionError(string $message): self
    {
        return new self('Verbindungsfehler zur Hetzner Cloud API: ' . $message, 503, 'CONNECTION_ERROR');
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine Hetzner-Cloud-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function invalidResponse(string $detail = ''): self
    {
        return new self(
            'Ungültige Antwort von der Hetzner Cloud API.' . ($detail !== '' ? ' ' . $detail : ''),
            500,
            'INVALID_RESPONSE'
        );
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Wartezeit in Sekunden, falls die Antwort eine genannt hat.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    public function getHttpStatusCode(): int
    {
        return $this->getCode();
    }

    public function isClientError(): bool
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    public function isServerError(): bool
    {
        return $this->getCode() >= 500;
    }

    public function isRateLimited(): bool
    {
        return $this->getCode() === 429;
    }

    public function isUnauthorized(): bool
    {
        return $this->getCode() === 401;
    }

    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
