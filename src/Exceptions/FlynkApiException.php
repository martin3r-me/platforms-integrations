<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für FLYNK REST API Fehler.
 *
 * FLYNK nutzt Standard-HTTP-Status-Codes und Laravel-Sanctum-Auth.
 * 401 = Token ungültig/fehlend, 422 = Validierungsfehler, 429 = Rate-Limit (throttle:api).
 */
class FlynkApiException extends Exception
{
    protected ?string $flynkErrorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich.',
        201 => 'Created - Ressource erstellt.',
        204 => 'No Content - Anfrage erfolgreich, keine Daten zurückgegeben.',

        400 => 'Bad Request - Ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Sanctum-Token ungültig oder fehlend.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource.',
        404 => 'Not Found - Ressource nicht gefunden.',
        409 => 'Conflict - Konflikt mit dem aktuellen Zustand.',
        422 => 'Unprocessable Entity - Validierungsfehler.',
        429 => 'Too Many Requests - Rate-Limit überschritten (throttle:api).',

        500 => 'Internal Server Error - Serverfehler bei FLYNK.',
        503 => 'Service Unavailable - FLYNK vorübergehend nicht verfügbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $flynkErrorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->flynkErrorCode = $flynkErrorCode;
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

    public static function unauthorized(string $message = 'Kein gültiger FLYNK Sanctum-Token vorhanden.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function missingBaseUrl(): self
    {
        return new self(
            'Keine FLYNK base_url in den Connection-Credentials hinterlegt.',
            400,
            'MISSING_BASE_URL'
        );
    }

    public static function notFound(string $resource = 'Ressource'): self
    {
        return new self("{$resource} wurde in FLYNK nicht gefunden.", 404, 'NOT_FOUND');
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
            'Verbindungsfehler zur FLYNK API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine FLYNK-Verbindung konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public function getFlynkErrorCode(): ?string
    {
        return $this->flynkErrorCode;
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
                'code' => $this->flynkErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
