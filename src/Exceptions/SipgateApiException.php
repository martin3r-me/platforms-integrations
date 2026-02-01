<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Sipgate API Fehler
 *
 * Diese Exception kapselt alle Fehler, die von der Sipgate API zurückgegeben werden.
 * Sie enthält sowohl den HTTP Status Code als auch den Sipgate-spezifischen Fehlercode.
 *
 * HTTP Status Codes gemäß Sipgate API Dokumentation:
 * @see https://developer.sipgate.io/rest-api/errors
 *
 * 2xx - Erfolgreiche Anfragen:
 * - 200 OK: Anfrage erfolgreich
 * - 201 Created: Ressource wurde erstellt
 * - 204 No Content: Anfrage erfolgreich, keine Daten zurückgegeben
 *
 * 4xx - Client-Fehler:
 * - 400 Bad Request: Ungültige Anfrage (fehlende/ungültige Parameter)
 * - 401 Unauthorized: Token ungültig oder abgelaufen
 * - 402 Payment Required: Feature erfordert kostenpflichtiges Abonnement
 * - 403 Forbidden: Keine Berechtigung für diese Aktion
 * - 404 Not Found: Ressource nicht gefunden
 * - 405 Method Not Allowed: HTTP-Methode nicht erlaubt
 * - 409 Conflict: Konflikt mit aktuellem Zustand
 * - 429 Too Many Requests: Rate-Limit überschritten
 *
 * 5xx - Server-Fehler:
 * - 500 Internal Server Error: Interner Serverfehler
 * - 502 Bad Gateway: Upstream-Fehler
 * - 503 Service Unavailable: Service vorübergehend nicht verfügbar
 * - 504 Gateway Timeout: Timeout bei Upstream-Anfrage
 */
class SipgateApiException extends Exception
{
    protected ?string $sipgateErrorCode = null;
    protected ?array $responseData = null;
    protected ?string $requestId = null;
    protected bool $retryable = false;
    protected ?int $retryAfter = null;

    /**
     * HTTP Status Code Beschreibungen für Sipgate API
     */
    public const HTTP_STATUS_MESSAGES = [
        // 2xx Erfolg
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        201 => 'Created - Ressource wurde erfolgreich erstellt.',
        204 => 'No Content - Anfrage erfolgreich, keine Daten zurückgegeben.',

        // 4xx Client-Fehler
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Der Token ist ungültig oder abgelaufen.',
        402 => 'Payment Required - Diese Funktion erfordert ein kostenpflichtiges Abonnement.',
        403 => 'Forbidden - Keine Berechtigung für diese Aktion.',
        404 => 'Not Found - Die angeforderte Ressource wurde nicht gefunden.',
        405 => 'Method Not Allowed - Die HTTP-Methode ist nicht erlaubt.',
        409 => 'Conflict - Konflikt mit dem aktuellen Zustand der Ressource.',
        422 => 'Unprocessable Entity - Die Anfrage konnte nicht verarbeitet werden.',
        429 => 'Too Many Requests - Rate-Limit überschritten. Bitte später erneut versuchen.',

        // 5xx Server-Fehler
        500 => 'Internal Server Error - Ein interner Serverfehler ist aufgetreten.',
        502 => 'Bad Gateway - Ein Upstream-Fehler ist aufgetreten.',
        503 => 'Service Unavailable - Der Service ist vorübergehend nicht verfügbar.',
        504 => 'Gateway Timeout - Timeout bei der Upstream-Anfrage.',
    ];

    /**
     * Sipgate-spezifische Fehlercodes
     */
    public const ERROR_CODES = [
        'INVALID_TOKEN' => 'Der OAuth-Token ist ungültig.',
        'TOKEN_EXPIRED' => 'Der OAuth-Token ist abgelaufen.',
        'INSUFFICIENT_SCOPE' => 'Der Token hat nicht die erforderlichen Berechtigungen.',
        'RATE_LIMITED' => 'Zu viele Anfragen. Bitte warten Sie.',
        'ACCOUNT_SUSPENDED' => 'Das Sipgate-Konto ist gesperrt.',
        'FEATURE_NOT_AVAILABLE' => 'Diese Funktion ist für Ihr Konto nicht verfügbar.',
        'INVALID_PHONE_NUMBER' => 'Ungültige Telefonnummer.',
        'CALL_FAILED' => 'Der Anruf konnte nicht initiiert werden.',
        'SMS_FAILED' => 'Die SMS konnte nicht gesendet werden.',
        'FAX_FAILED' => 'Das Fax konnte nicht gesendet werden.',
        'WEBHOOK_VERIFICATION_FAILED' => 'Die Webhook-Signatur ist ungültig.',
        'CONNECTION_ERROR' => 'Verbindungsfehler zur Sipgate API.',
        'TIMEOUT' => 'Die Anfrage hat das Zeitlimit überschritten.',
        'NO_CONNECTION' => 'Keine Sipgate-Verbindung konfiguriert.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $sipgateErrorCode = null,
        ?array $responseData = null,
        ?string $requestId = null,
        bool $retryable = false,
        ?int $retryAfter = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->sipgateErrorCode = $sipgateErrorCode;
        $this->responseData = $responseData;
        $this->requestId = $requestId;
        $this->retryable = $retryable;
        $this->retryAfter = $retryAfter;
    }

    /**
     * Erstellt eine Exception aus einer HTTP Response
     */
    public static function fromResponse(
        int $httpStatusCode,
        ?array $responseData = null,
        ?string $requestId = null
    ): self {
        $message = $responseData['message']
            ?? $responseData['error_description']
            ?? $responseData['error']
            ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode]
            ?? 'Unbekannter Fehler';

        $errorCode = $responseData['error']
            ?? $responseData['code']
            ?? null;

        // Bestimme ob Retry sinnvoll ist
        $retryable = in_array($httpStatusCode, [429, 500, 502, 503, 504]);
        $retryAfter = $responseData['retry_after'] ?? null;

        // Bei 429 Standard-Wartezeit falls nicht angegeben
        if ($httpStatusCode === 429 && $retryAfter === null) {
            $retryAfter = 60;
        }

        return new self(
            $message,
            $httpStatusCode,
            $errorCode,
            $responseData,
            $requestId,
            $retryable,
            $retryAfter
        );
    }

    /**
     * Erstellt eine Exception für fehlende/ungültige Authentifizierung
     */
    public static function unauthorized(string $message = 'Kein gültiger Sipgate OAuth-Token vorhanden.'): self
    {
        return new self($message, 401, 'INVALID_TOKEN', null, null, false);
    }

    /**
     * Erstellt eine Exception für abgelaufene Tokens
     */
    public static function tokenExpired(): self
    {
        return new self(
            'Der Sipgate OAuth-Token ist abgelaufen und muss erneuert werden.',
            401,
            'TOKEN_EXPIRED',
            null,
            null,
            true // Kann durch Refresh behoben werden
        );
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
    public static function rateLimitExceeded(?int $retryAfter = 60): self
    {
        return new self(
            'Rate-Limit überschritten. Bitte in ' . $retryAfter . ' Sekunden erneut versuchen.',
            429,
            'RATE_LIMITED',
            null,
            null,
            true,
            $retryAfter
        );
    }

    /**
     * Erstellt eine Exception für Verbindungsfehler
     */
    public static function connectionError(string $message, ?string $requestId = null): self
    {
        return new self(
            'Verbindungsfehler zur Sipgate API: ' . $message,
            503,
            'CONNECTION_ERROR',
            null,
            $requestId,
            true // Retry ist sinnvoll
        );
    }

    /**
     * Erstellt eine Exception für Timeout
     */
    public static function timeout(?string $requestId = null): self
    {
        return new self(
            'Die Anfrage an die Sipgate API hat das Zeitlimit überschritten.',
            504,
            'TIMEOUT',
            null,
            $requestId,
            true
        );
    }

    /**
     * Erstellt eine Exception wenn keine Integration Connection vorhanden ist
     */
    public static function noConnection(): self
    {
        return new self(
            'Keine Sipgate-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION',
            null,
            null,
            false
        );
    }

    /**
     * Erstellt eine Exception für fehlende Berechtigungen
     */
    public static function insufficientScope(string $requiredScope): self
    {
        return new self(
            "Fehlende Berechtigung: {$requiredScope}. Bitte erneut verbinden und die Berechtigung erteilen.",
            403,
            'INSUFFICIENT_SCOPE',
            ['required_scope' => $requiredScope]
        );
    }

    /**
     * Erstellt eine Exception für ungültige Webhook-Signatur
     */
    public static function webhookVerificationFailed(): self
    {
        return new self(
            'Die Webhook-Signatur konnte nicht verifiziert werden.',
            401,
            'WEBHOOK_VERIFICATION_FAILED'
        );
    }

    /**
     * Erstellt eine Exception für Circuit-Breaker (zu viele Fehler)
     */
    public static function circuitOpen(int $cooldownSeconds = 60): self
    {
        return new self(
            "Die Sipgate API ist vorübergehend nicht erreichbar. Bitte in {$cooldownSeconds} Sekunden erneut versuchen.",
            503,
            'CIRCUIT_OPEN',
            null,
            null,
            true,
            $cooldownSeconds
        );
    }

    /**
     * Gibt den Sipgate-spezifischen Fehlercode zurück
     */
    public function getSipgateErrorCode(): ?string
    {
        return $this->sipgateErrorCode;
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
     * Gibt die Request-ID für Tracing zurück
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Prüft, ob ein Retry sinnvoll ist
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * Gibt die empfohlene Wartezeit bis zum Retry zurück (in Sekunden)
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
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
     * Prüft, ob der Token abgelaufen ist
     */
    public function isTokenExpired(): bool
    {
        return $this->sipgateErrorCode === 'TOKEN_EXPIRED'
            || ($this->getCode() === 401 && str_contains($this->getMessage(), 'expired'));
    }

    /**
     * Prüft, ob ein Token-Refresh versucht werden sollte
     */
    public function shouldRefreshToken(): bool
    {
        return $this->isTokenExpired()
            || ($this->getCode() === 401 && $this->sipgateErrorCode !== 'NO_CONNECTION');
    }

    /**
     * Konvertiert die Exception in ein Array für JSON-Responses
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->sipgateErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
                'request_id' => $this->requestId,
                'retryable' => $this->retryable,
                'retry_after' => $this->retryAfter,
            ],
        ];
    }

    /**
     * Gibt eine benutzerfreundliche Fehlermeldung zurück
     */
    public function getUserMessage(): string
    {
        // Prüfe auf bekannte Fehlercodes
        if ($this->sipgateErrorCode && isset(self::ERROR_CODES[$this->sipgateErrorCode])) {
            return self::ERROR_CODES[$this->sipgateErrorCode];
        }

        // Fallback auf HTTP-Status-Meldung
        return self::HTTP_STATUS_MESSAGES[$this->getCode()] ?? $this->getMessage();
    }
}
