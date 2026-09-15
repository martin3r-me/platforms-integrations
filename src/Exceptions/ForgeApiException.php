<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Laravel Forge API Fehler (API v2).
 *
 * Forge liefert Fehler im Laravel-Standardformat:
 *   { "message": "...", "errors": { "feld": ["..."] } }
 *
 * @see https://laravel.com/forge/docs/api-reference/introduction
 */
class ForgeApiException extends Exception
{
    protected ?string $errorCode = null;

    protected ?array $responseData = null;

    public const HTTP_STATUS_MESSAGES = [
        200 => 'OK - Anfrage erfolgreich verarbeitet.',
        201 => 'Created - Ressource wurde angelegt.',
        204 => 'No Content - Anfrage erfolgreich, keine Rückgabe.',
        400 => 'Bad Request - Die Anfrage enthält ungültige oder fehlende Parameter.',
        401 => 'Unauthorized - Kein gültiger Forge API-Token übermittelt.',
        403 => 'Forbidden - Der Token hat keine Berechtigung für diese Ressource.',
        404 => 'Not Found - Ressource nicht gefunden (falsche Organisation/Server/Site-ID?).',
        422 => 'Unprocessable Entity - Validierungsfehler, siehe "errors".',
        429 => 'Too Many Requests - Rate-Limit überschritten.',
        500 => 'Internal Server Error - Ein interner Serverfehler ist aufgetreten.',
        503 => 'Service Unavailable - Forge ist derzeit im Wartungsmodus.',
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
        $message = $responseData['message']
            ?? $responseData['error']
            ?? self::HTTP_STATUS_MESSAGES[$httpStatusCode]
            ?? 'Unbekannter Fehler';

        // Validierungsfehler (422) für den Aufrufer lesbar anhängen.
        if (!empty($responseData['errors']) && is_array($responseData['errors'])) {
            $details = [];
            foreach ($responseData['errors'] as $field => $messages) {
                $messages = is_array($messages) ? $messages : [$messages];
                $details[] = $field . ': ' . implode(' ', array_map('strval', $messages));
            }

            if ($details) {
                $message .= ' (' . implode('; ', $details) . ')';
            }
        }

        return new self($message, $httpStatusCode, (string) $httpStatusCode, $responseData);
    }

    public static function unauthorized(string $message = 'Ungültiger Laravel Forge API-Token.'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'Der Forge API-Token hat keine Berechtigung für diese Ressource.'): self
    {
        return new self($message, 403, 'FORBIDDEN');
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        $message = 'Forge Rate-Limit überschritten.';
        $message .= $retryAfter
            ? " Bitte in {$retryAfter} Sekunden erneut versuchen."
            : ' Bitte später erneut versuchen.';

        return new self($message, 429, 'RATE_LIMIT_EXCEEDED');
    }

    public static function connectionError(string $message): self
    {
        return new self('Verbindungsfehler zur Laravel Forge API: ' . $message, 503, 'CONNECTION_ERROR');
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine Laravel-Forge-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function missingOrganization(): self
    {
        return new self(
            'Keine Forge-Organisation angegeben. Übergib "organization" oder hinterlege eine Standard-Organisation '
            . 'in der Verbindung (credentials.organization).',
            400,
            'MISSING_ORGANIZATION'
        );
    }

    public static function invalidResponse(string $detail = ''): self
    {
        return new self(
            'Ungültige Antwort von der Laravel Forge API.' . ($detail !== '' ? ' ' . $detail : ''),
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
