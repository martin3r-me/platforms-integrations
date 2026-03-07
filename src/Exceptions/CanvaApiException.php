<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Canva Connect API Fehler
 *
 * @see https://www.canva.dev/docs/connect/
 */
class CanvaApiException extends Exception
{
    protected ?string $errorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Authentifizierung erforderlich oder Token ungültig.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource oder fehlender OAuth-Scope.',
        404 => 'Not Found - Ressource nicht gefunden.',
        429 => 'Too Many Requests - Rate-Limit überschritten.',
        500 => 'Internal Server Error - Ein interner Serverfehler ist aufgetreten.',
        503 => 'Service Unavailable - Der Service ist vorübergehend nicht verfügbar.',
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
        $message = $responseData['message'] ?? $responseData['error']['message'] ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler';
        $errorCode = $responseData['code'] ?? $responseData['error']['code'] ?? null;

        return new self($message, $httpStatusCode, $errorCode, $responseData);
    }

    public static function unauthorized(string $message = 'Ungültiger oder abgelaufener Canva Access-Token.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function rateLimitExceeded(): self
    {
        return new self(
            'Rate-Limit überschritten. Bitte später erneut versuchen.',
            429,
            'RATE_LIMIT_EXCEEDED'
        );
    }

    public static function connectionError(string $message): self
    {
        return new self(
            'Verbindungsfehler zur Canva API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine Canva-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function invalidResponse(string $detail = ''): self
    {
        return new self(
            'Ungültige Antwort von der Canva API.' . ($detail ? ' ' . $detail : ''),
            500,
            'INVALID_RESPONSE'
        );
    }

    public static function jobTimeout(string $endpoint): self
    {
        return new self(
            'Canva Async-Job Timeout für: ' . $endpoint,
            504,
            'JOB_TIMEOUT'
        );
    }

    public static function jobFailed(string $jobId, ?string $detail = null): self
    {
        return new self(
            'Canva Job fehlgeschlagen (ID: ' . $jobId . ').' . ($detail ? ' ' . $detail : ''),
            500,
            'JOB_FAILED'
        );
    }

    public static function scopeMissing(string $scope): self
    {
        return new self(
            'Fehlender OAuth-Scope: ' . $scope . '. Bitte Canva erneut verbinden.',
            403,
            'SCOPE_MISSING'
        );
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
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
