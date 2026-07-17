<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für die DedeFleet Ortungs-/Tourenplanungs-API.
 *
 * DedeFleet nutzt Standard-HTTP-Status-Codes und Bearer-Token-Auth
 * (Authorization: Bearer <token>, Dauertoken vom Typ "Api Vollzugriff").
 * 401 = Token ungültig/fehlend, 403 = keine Berechtigung.
 */
class DedefleetApiException extends Exception
{
    protected ?string $dedefleetErrorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich.',
        201 => 'Created - Ressource erstellt.',
        204 => 'No Content - Anfrage erfolgreich, keine Daten zurückgegeben.',

        400 => 'Bad Request - Ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Bearer-Token ungültig oder fehlend.',
        403 => 'Forbidden - Keine Berechtigung für diese Ressource/Aktion.',
        404 => 'Not Found - Ressource nicht gefunden.',
        409 => 'Conflict - Konflikt mit dem aktuellen Zustand.',
        422 => 'Unprocessable Entity - Validierungsfehler.',
        429 => 'Too Many Requests - Rate-Limit überschritten.',

        500 => 'Internal Server Error - Serverfehler bei DedeFleet.',
        503 => 'Service Unavailable - DedeFleet vorübergehend nicht verfügbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $dedefleetErrorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->dedefleetErrorCode = $dedefleetErrorCode;
        $this->responseData = $responseData;
    }

    public static function fromResponse(int $httpStatusCode, ?array $responseData = null): self
    {
        $message = $responseData['message']
            ?? $responseData['error']
            ?? $responseData['Message']
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

        return new self(is_string($message) ? $message : json_encode($message), $httpStatusCode, $errorCode, $responseData);
    }

    public static function unauthorized(string $message = 'Kein gültiger DedeFleet Bearer-Token vorhanden.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function notFound(string $resource = 'Ressource'): self
    {
        return new self("{$resource} wurde in DedeFleet nicht gefunden.", 404, 'NOT_FOUND');
    }

    public static function rateLimitExceeded(): self
    {
        return new self('Rate-Limit überschritten. Bitte später erneut versuchen.', 429, 'RATE_LIMIT_EXCEEDED');
    }

    public static function connectionError(string $message): self
    {
        return new self('Verbindungsfehler zur DedeFleet API: ' . $message, 503, 'CONNECTION_ERROR');
    }

    public static function noConnection(): self
    {
        return new self('Keine DedeFleet-Verbindung konfiguriert.', 401, 'NO_CONNECTION');
    }

    public function getDedefleetErrorCode(): ?string
    {
        return $this->dedefleetErrorCode;
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

    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->dedefleetErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
