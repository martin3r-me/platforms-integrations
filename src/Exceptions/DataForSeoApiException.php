<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für DataForSEO API Fehler
 *
 * DataForSEO Keywords Data API v3
 * @see https://docs.dataforseo.com/v3/keywords_data/google_ads/
 */
class DataForSeoApiException extends Exception
{
    protected ?string $errorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Authentifizierung erforderlich oder Credentials ungültig.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource.',
        404 => 'Not Found - Endpoint nicht gefunden.',
        429 => 'Too Many Requests - Rate-Limit überschritten (max 2000 req/min).',
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
        $message = $responseData['status_message'] ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler';
        $errorCode = isset($responseData['status_code']) ? (string) $responseData['status_code'] : null;

        return new self($message, $httpStatusCode, $errorCode, $responseData);
    }

    public static function unauthorized(string $message = 'Ungültige DataForSEO Credentials (Login/Password).'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function rateLimitExceeded(): self
    {
        return new self(
            'Rate-Limit überschritten (max 2000 req/min). Bitte später erneut versuchen.',
            429,
            'RATE_LIMIT_EXCEEDED'
        );
    }

    public static function connectionError(string $message): self
    {
        return new self(
            'Verbindungsfehler zur DataForSEO API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine DataForSEO-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function invalidResponse(string $detail = ''): self
    {
        return new self(
            'Ungültige Antwort von der DataForSEO API.' . ($detail ? ' ' . $detail : ''),
            500,
            'INVALID_RESPONSE'
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
