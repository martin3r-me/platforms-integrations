<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für die NinjaOne Public API 2.0.
 *
 * NinjaOne nutzt Standard-HTTP-Status-Codes und OAuth2-Bearer-Token. Fehler
 * kommen als JSON mit "resultCode" und "errorMessage"; bei OAuth-Fehlern
 * stattdessen mit "error" und "error_description".
 *
 * @see https://app.ninjarmm.com/apidocs/
 */
class NinjaApiException extends Exception
{
    protected ?string $ninjaErrorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        400 => 'Bad Request - Ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Token fehlt, abgelaufen oder ungültig.',
        403 => 'Forbidden - Dem Token fehlt der nötige Scope (monitoring/management/control).',
        404 => 'Not Found - Ressource nicht gefunden (Region und ID prüfen).',
        405 => 'Method Not Allowed - Methode für diesen Endpunkt nicht erlaubt.',
        409 => 'Conflict - Die Ressource steht im Konflikt mit dem aktuellen Zustand.',
        422 => 'Unprocessable Entity - Validierungsfehler.',
        429 => 'Too Many Requests - Rate-Limit überschritten.',
        500 => 'Internal Server Error - Serverfehler bei NinjaOne.',
        502 => 'Bad Gateway - NinjaOne vorübergehend nicht erreichbar.',
        503 => 'Service Unavailable - NinjaOne vorübergehend nicht verfügbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $ninjaErrorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->ninjaErrorCode = $ninjaErrorCode;
        $this->responseData = $responseData;
    }

    /**
     * Baut die Exception aus einer Fehler-Response.
     *
     * NinjaOne antwortet je nach Schicht unterschiedlich: die API mit
     * resultCode/errorMessage, der OAuth-Server mit error/error_description.
     * Beides wird hier abgedeckt; sonst wird der Rohtext durchgereicht,
     * damit nichts verschluckt wird.
     */
    public static function fromResponse(int $httpStatusCode, ?array $responseData = null, ?string $rawBody = null): self
    {
        $responseData ??= [];

        $code = $responseData['resultCode']
            ?? $responseData['error']
            ?? null;

        $message = $responseData['errorMessage']
            ?? $responseData['error_description']
            ?? $responseData['message']
            ?? null;

        if (!is_string($message) || $message === '') {
            $message = is_string($code) && $code !== '' ? $code : null;
        }

        if ($message === null) {
            $body = is_string($rawBody) ? trim($rawBody) : '';
            $message = ($body !== '' && $body !== '[]' && $body !== '{}')
                ? mb_substr($body, 0, 800)
                : (self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler');
        }

        // Die incidentId ist NinjaOnes Referenz für den Support — mitnehmen.
        $incident = $responseData['incidentId'] ?? null;
        if (is_string($incident) && $incident !== '') {
            $message .= ' | incidentId: ' . $incident;
        }

        return new self(
            'HTTP ' . $httpStatusCode . ': ' . $message,
            $httpStatusCode,
            is_string($code) ? $code : null,
            $responseData
        );
    }

    public static function unauthorized(string $message = 'Kein gültiges NinjaOne-Token vorhanden.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function missingCredentials(): self
    {
        return new self(
            'Keine NinjaOne-Zugangsdaten hinterlegt (client_id/client_secret bzw. OAuth-Token).',
            400,
            'MISSING_CREDENTIALS'
        );
    }

    public static function unknownOperation(string $method, string $path): self
    {
        return new self(
            "Unbekannte NinjaOne-Operation: \"{$method} {$path}\". "
            . 'Gültige Endpunkte siehe integrations.ninja.overview.',
            400,
            'UNKNOWN_OPERATION'
        );
    }

    public static function missingPathParameter(string $name, string $path): self
    {
        return new self(
            "Pfad-Parameter \"{$name}\" fehlt für \"{$path}\".",
            400,
            'MISSING_PATH_PARAMETER'
        );
    }

    public static function tokenRequestFailed(string $message): self
    {
        return new self(
            'Token-Anforderung bei NinjaOne fehlgeschlagen: ' . $message,
            401,
            'TOKEN_REQUEST_FAILED'
        );
    }

    public static function connectionError(string $message): self
    {
        return new self(
            'Verbindungsfehler zur NinjaOne API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self('Keine NinjaOne-Verbindung konfiguriert.', 401, 'NO_CONNECTION');
    }

    public static function rateLimitExceeded(): self
    {
        return new self('Rate-Limit überschritten. Bitte später erneut versuchen.', 429, 'RATE_LIMIT_EXCEEDED');
    }

    public function getNinjaErrorCode(): ?string
    {
        return $this->ninjaErrorCode;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    public function getHttpStatusCode(): int
    {
        return $this->getCode();
    }

    public function isUnauthorized(): bool
    {
        return $this->getCode() === 401;
    }

    public function isRateLimited(): bool
    {
        return $this->getCode() === 429;
    }

    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->ninjaErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
