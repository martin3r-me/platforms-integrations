<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für easybill REST API Fehler.
 *
 * easybill nutzt die Standard-HTTP-Status-Codes. 4xx = Client-Fehler,
 * 5xx = Server-Fehler. Bei 429 (Rate Limit) gibt das PLUS-Plan 10 req/min,
 * BUSINESS 60 req/min.
 *
 * @see https://www.easybill.de/api/
 */
class EasybillApiException extends Exception
{
    protected ?string $easybillErrorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich.',
        201 => 'Created - Ressource erstellt.',
        204 => 'No Content - Anfrage erfolgreich, keine Daten zurückgegeben.',

        400 => 'Bad Request - Ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - API-Token ungültig oder fehlend.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource.',
        404 => 'Not Found - Ressource nicht gefunden.',
        409 => 'Conflict - Konflikt mit dem aktuellen Zustand.',
        415 => 'Unsupported Media Type - Content-Type nicht unterstützt.',
        422 => 'Unprocessable Entity - Validierungsfehler.',
        429 => 'Too Many Requests - Rate-Limit überschritten.',

        500 => 'Internal Server Error - Serverfehler bei easybill.',
        503 => 'Service Unavailable - easybill vorübergehend nicht verfügbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $easybillErrorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->easybillErrorCode = $easybillErrorCode;
        $this->responseData = $responseData;
    }

    public static function fromResponse(int $httpStatusCode, ?array $responseData = null): self
    {
        $message = $responseData['message']
            ?? $responseData['error']
            ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode]
            ?? 'Unbekannter Fehler';
        $errorCode = $responseData['code'] ?? $responseData['error_code'] ?? null;

        if (!empty($responseData['errors']) && is_array($responseData['errors'])) {
            $details = [];
            foreach ($responseData['errors'] as $field => $issue) {
                $issueText = is_array($issue) ? implode(', ', $issue) : (string) $issue;
                $details[] = "{$field}: {$issueText}";
            }
            if ($details) {
                $message .= ' Details: ' . implode('; ', $details);
            }
        }

        return new self($message, $httpStatusCode, $errorCode, $responseData);
    }

    public static function unauthorized(string $message = 'Kein gültiger easybill API-Token vorhanden.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function notFound(string $resource = 'Ressource'): self
    {
        return new self("{$resource} wurde in easybill nicht gefunden.", 404, 'NOT_FOUND');
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
            'Verbindungsfehler zur easybill API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine easybill-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public function getEasybillErrorCode(): ?string
    {
        return $this->easybillErrorCode;
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
                'code' => $this->easybillErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
