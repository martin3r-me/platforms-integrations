<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Google Search Console API Fehler
 *
 * @see https://developers.google.com/webmaster-tools/v3/errors
 */
class GoogleSearchConsoleApiException extends Exception
{
    protected ?string $errorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Authentifizierung erforderlich oder Token ungültig.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource.',
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
        $message = $responseData['error']['message']
            ?? $responseData['message']
            ?? $responseData['error']
            ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode]
            ?? 'Unbekannter Fehler';
        $errorCode = isset($responseData['error']['code']) ? (string) $responseData['error']['code'] : null;

        return new self($message, $httpStatusCode, $errorCode, $responseData);
    }

    public static function unauthorized(string $message = 'Ungültiger Google Search Console Token.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        $message = 'Rate-Limit überschritten.';
        if ($retryAfter) {
            $message .= " Bitte in {$retryAfter} Sekunden erneut versuchen.";
        } else {
            $message .= ' Bitte später erneut versuchen.';
        }

        return new self($message, 429, 'RATE_LIMIT_EXCEEDED');
    }

    public static function connectionError(string $message): self
    {
        return new self(
            'Verbindungsfehler zur Google Search Console API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine Google Search Console-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function invalidResponse(string $detail = ''): self
    {
        return new self(
            'Ungültige Antwort von der Google Search Console API.' . ($detail ? ' ' . $detail : ''),
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
