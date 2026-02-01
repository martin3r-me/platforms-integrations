<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Lexware API Fehler
 *
 * Diese Exception kapselt alle Fehler, die von der Lexware API zurückgegeben werden.
 * Sie enthält sowohl den HTTP Status Code als auch den Lexware-spezifischen Fehlercode.
 *
 * HTTP Status Codes gemäß Lexware API Dokumentation:
 * @see https://developers.lexware.io/docs/#http-status-codes
 *
 * 2xx - Erfolgreiche Anfragen:
 * - 200 OK: Anfrage erfolgreich
 * - 201 Created: Ressource wurde erstellt
 * - 202 Accepted: Anfrage wurde akzeptiert, Verarbeitung läuft
 * - 204 No Content: Anfrage erfolgreich, keine Daten zurückgegeben
 *
 * 4xx - Client-Fehler:
 * - 400 Bad Request: Ungültige Anfrage (fehlende/ungültige Parameter)
 * - 401 Unauthorized: Authentifizierung erforderlich oder fehlgeschlagen
 * - 402 Payment Required: Funktion erfordert kostenpflichtiges Abonnement
 * - 403 Forbidden: Keine Berechtigung für diese Ressource
 * - 404 Not Found: Ressource nicht gefunden
 * - 405 Method Not Allowed: HTTP-Methode nicht erlaubt
 * - 406 Not Acceptable: Accept-Header nicht unterstützt
 * - 409 Conflict: Konflikt mit aktuellem Zustand der Ressource
 * - 415 Unsupported Media Type: Content-Type nicht unterstützt
 * - 429 Too Many Requests: Rate-Limit überschritten
 *
 * 5xx - Server-Fehler:
 * - 500 Internal Server Error: Interner Serverfehler
 * - 503 Service Unavailable: Service vorübergehend nicht verfügbar
 */
class LexwareApiException extends Exception
{
    protected ?string $lexwareErrorCode = null;
    protected ?array $responseData = null;

    /**
     * HTTP Status Code Beschreibungen für Lexware API
     */
    public const HTTP_STATUS_MESSAGES = [
        // 2xx Erfolg
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        201 => 'Created - Ressource wurde erfolgreich erstellt.',
        202 => 'Accepted - Anfrage wurde akzeptiert, Verarbeitung läuft.',
        204 => 'No Content - Anfrage erfolgreich, keine Daten zurückgegeben.',

        // 4xx Client-Fehler
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Authentifizierung erforderlich oder API-Token ungültig.',
        402 => 'Payment Required - Diese Funktion erfordert ein kostenpflichtiges Abonnement.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource.',
        404 => 'Not Found - Die angeforderte Ressource wurde nicht gefunden.',
        405 => 'Method Not Allowed - Die HTTP-Methode ist für diese Ressource nicht erlaubt.',
        406 => 'Not Acceptable - Der Accept-Header wird nicht unterstützt.',
        409 => 'Conflict - Konflikt mit dem aktuellen Zustand der Ressource.',
        415 => 'Unsupported Media Type - Der Content-Type wird nicht unterstützt.',
        429 => 'Too Many Requests - Rate-Limit überschritten. Bitte später erneut versuchen.',

        // 5xx Server-Fehler
        500 => 'Internal Server Error - Ein interner Serverfehler ist aufgetreten.',
        503 => 'Service Unavailable - Der Service ist vorübergehend nicht verfügbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $lexwareErrorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->lexwareErrorCode = $lexwareErrorCode;
        $this->responseData = $responseData;
    }

    /**
     * Erstellt eine Exception aus einer HTTP Response
     */
    public static function fromResponse(int $httpStatusCode, ?array $responseData = null): self
    {
        $message = $responseData['message'] ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler';
        $lexwareErrorCode = $responseData['error'] ?? $responseData['code'] ?? null;

        return new self($message, $httpStatusCode, $lexwareErrorCode, $responseData);
    }

    /**
     * Erstellt eine Exception für fehlende Authentifizierung
     */
    public static function unauthorized(string $message = 'Kein gültiger Lexware API-Token vorhanden.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    /**
     * Erstellt eine Exception für nicht gefundene Ressourcen
     */
    public static function notFound(string $resource = 'Ressource'): self
    {
        return new self("{$resource} wurde nicht gefunden.", 404, 'NOT_FOUND');
    }

    /**
     * Erstellt eine Exception für Rate-Limiting
     */
    public static function rateLimitExceeded(): self
    {
        return new self(
            'Rate-Limit überschritten. Bitte später erneut versuchen.',
            429,
            'RATE_LIMIT_EXCEEDED'
        );
    }

    /**
     * Erstellt eine Exception für Verbindungsfehler
     */
    public static function connectionError(string $message): self
    {
        return new self(
            'Verbindungsfehler zur Lexware API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    /**
     * Erstellt eine Exception wenn keine Integration Connection vorhanden ist
     */
    public static function noConnection(): self
    {
        return new self(
            'Keine Lexware-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    /**
     * Gibt den Lexware-spezifischen Fehlercode zurück
     */
    public function getLexwareErrorCode(): ?string
    {
        return $this->lexwareErrorCode;
    }

    /**
     * Gibt die vollständigen Response-Daten zurück
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * Gibt den HTTP Status Code zurück
     */
    public function getHttpStatusCode(): int
    {
        return $this->getCode();
    }

    /**
     * Prüft, ob es sich um einen Client-Fehler handelt (4xx)
     */
    public function isClientError(): bool
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    /**
     * Prüft, ob es sich um einen Server-Fehler handelt (5xx)
     */
    public function isServerError(): bool
    {
        return $this->getCode() >= 500;
    }

    /**
     * Prüft, ob der Fehler durch Rate-Limiting verursacht wurde
     */
    public function isRateLimited(): bool
    {
        return $this->getCode() === 429;
    }

    /**
     * Prüft, ob der Fehler durch fehlende Authentifizierung verursacht wurde
     */
    public function isUnauthorized(): bool
    {
        return $this->getCode() === 401;
    }

    /**
     * Konvertiert die Exception in ein Array für JSON-Responses
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->lexwareErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
