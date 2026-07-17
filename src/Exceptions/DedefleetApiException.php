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

    public static function fromResponse(int $httpStatusCode, ?array $responseData = null, ?string $rawBody = null): self
    {
        $responseData ??= [];

        // Strukturierte Meldung (message/error/Message/title als String ODER Array).
        $raw = $responseData['message'] ?? $responseData['error'] ?? $responseData['Message'] ?? $responseData['title'] ?? null;
        $message = null;
        if (is_string($raw) && $raw !== '') {
            $message = $raw;
        } elseif (is_array($raw)) {
            $message = json_encode($raw, JSON_UNESCAPED_UNICODE);
        }

        $errorCode = $responseData['code'] ?? $responseData['error_code'] ?? null;

        $details = [];
        if (!empty($responseData['errors']) && is_array($responseData['errors'])) {
            foreach ($responseData['errors'] as $field => $issue) {
                if (is_array($issue)) {
                    $issueText = implode(', ', array_map(
                        static fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE),
                        $issue
                    ));
                } else {
                    $issueText = is_scalar($issue) ? (string) $issue : json_encode($issue, JSON_UNESCAPED_UNICODE);
                }
                $details[] = is_string($field) && $field !== '' ? "{$field}: {$issueText}" : $issueText;
            }
        }

        // Fallback: kompletten Rohbody durchreichen, wenn keine strukturierte Meldung da ist.
        if ($message === null || $message === '') {
            $body = is_string($rawBody) ? trim($rawBody) : '';
            if ($body !== '' && $body !== '[]' && $body !== '{}') {
                $message = mb_substr($body, 0, 800);
            } else {
                $message = self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler';
            }
        }

        if ($details) {
            $message .= ' | Details: ' . implode('; ', $details);
        }

        $message = 'HTTP ' . $httpStatusCode . ': ' . $message;

        return new self($message, $httpStatusCode, is_string($errorCode) ? $errorCode : null, $responseData);
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
