<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für die necta.one Raw-API.
 *
 * necta.one nutzt Standard-HTTP-Status-Codes und API-Key-Auth via Header
 * `X-Api-Key`. 401 = Key fehlt/ungültig, 403 = Key ohne RAW-API-Rechte,
 * 404 = Ressource/Instanz nicht gefunden.
 */
class NectaApiException extends Exception
{
    protected ?string $nectaErrorCode = null;
    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich.',
        204 => 'No Content - Anfrage erfolgreich, keine Daten zurückgegeben.',

        400 => 'Bad Request - Ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - API-Key ungültig oder fehlend (Header X-Api-Key).',
        403 => 'Forbidden - Der API-Key besitzt keine RAW-API-Berechtigung.',
        404 => 'Not Found - Ressource oder Instanz nicht gefunden (base_url prüfen).',
        422 => 'Unprocessable Entity - Validierungsfehler.',
        429 => 'Too Many Requests - Rate-Limit überschritten.',

        500 => 'Internal Server Error - Serverfehler bei necta.one.',
        503 => 'Service Unavailable - necta.one vorübergehend nicht verfügbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $nectaErrorCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->nectaErrorCode = $nectaErrorCode;
        $this->responseData = $responseData;
    }

    public static function fromResponse(int $httpStatusCode, ?array $responseData = null): self
    {
        $responseData ??= [];

        // ASP.NET/necta liefert message/error teils als String, teils als Array
        // (ProblemDetails). Immer sicher zu String machen — sonst "Array to string".
        $raw = $responseData['message'] ?? $responseData['error'] ?? $responseData['title'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $message = $raw;
        } elseif (is_array($raw)) {
            $message = json_encode($raw, JSON_UNESCAPED_UNICODE);
        } else {
            $message = self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler';
        }

        $errorCode = $responseData['code'] ?? $responseData['error_code'] ?? null;

        if (!empty($responseData['errors']) && is_array($responseData['errors'])) {
            $details = [];
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
            if ($details) {
                $message .= ' Details: ' . implode('; ', $details);
            }
        }

        return new self($message, $httpStatusCode, is_string($errorCode) ? $errorCode : null, $responseData);
    }

    public static function unauthorized(string $message = 'Kein gültiger necta.one API-Key vorhanden.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function forbidden(): self
    {
        return new self(
            'Der API-Key besitzt keine RAW-API-Berechtigung (HTTP 403).',
            403,
            'FORBIDDEN'
        );
    }

    public static function missingBaseUrl(): self
    {
        return new self(
            'Keine necta.one base_url in den Connection-Credentials hinterlegt.',
            400,
            'MISSING_BASE_URL'
        );
    }

    public static function notFound(string $resource = 'Ressource'): self
    {
        return new self("{$resource} wurde in necta.one nicht gefunden.", 404, 'NOT_FOUND');
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
            'Verbindungsfehler zur necta.one API: ' . $message,
            503,
            'CONNECTION_ERROR'
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine necta.one-Verbindung konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public function getNectaErrorCode(): ?string
    {
        return $this->nectaErrorCode;
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
                'code' => $this->nectaErrorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
            ],
        ];
    }
}
