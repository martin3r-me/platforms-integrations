<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für Fehler der cisbox Invoice API v1.
 *
 * cisbox liefert Fehler einheitlich als:
 *   { "status": "error", "errors": [ { "parameter": "page", "message": "must be >= 1" } ] }
 *
 * Jede Antwort — auch die fehlerhafte — trägt den Header X-Request-Id. Er wird
 * hier festgehalten und in die Meldung aufgenommen: Ohne ihn lässt sich ein
 * Vorfall im Support nicht in den Server-Logs wiederfinden.
 *
 * @see https://api.cisbox.com/docs/ui/invoice-api-v1
 */
class CisboxApiException extends Exception
{
    protected ?string $errorCode = null;

    protected ?array $responseData = null;

    protected ?string $requestId = null;

    public const HTTP_STATUS_MESSAGES = [
        400 => 'Bad Request - Die Anfrage enthält ungültige Parameter.',
        401 => 'Unauthorized - API-Key fehlt oder ist ungültig (Header X-API-Key).',
        403 => 'Forbidden - Der API-Key hat keinen Zugriff auf diese Ressource.',
        404 => 'Not Found - Datensatz nicht gefunden.',
        405 => 'Method Not Allowed - Die API kennt nur GET-Aufrufe.',
        422 => 'Unprocessable Entity - Validierungsfehler in den Query-Parametern.',
        429 => 'Too Many Requests - Zu viele Anfragen.',
        500 => 'Internal Server Error - Interner Fehler bei cisbox.',
        503 => 'Service Unavailable - Die API ist vorübergehend nicht erreichbar.',
    ];

    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        ?string $errorCode = null,
        ?array $responseData = null,
        ?string $requestId = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);

        $this->errorCode = $errorCode;
        $this->responseData = $responseData;
        $this->requestId = $requestId;
    }

    /**
     * Baut die Exception aus einer Fehlerantwort.
     *
     * Die Liste in "errors" nennt je Eintrag den beanstandeten Parameter — der
     * wird mit ausgegeben, weil die Meldung allein ("must be >= 1") sonst nicht
     * verrät, worauf sie sich bezieht.
     *
     * @param array<string, mixed> $responseData
     */
    public static function fromResponse(
        int $httpStatusCode,
        array $responseData = [],
        ?string $rawBody = null,
        ?string $requestId = null
    ): self {
        $errors = is_array($responseData['errors'] ?? null) ? $responseData['errors'] : [];

        $parts = [];
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            $parameter = trim((string) ($error['parameter'] ?? ''));
            $text = trim((string) ($error['message'] ?? ''));

            if ($text === '' && $parameter === '') {
                continue;
            }

            $parts[] = $parameter !== '' ? "{$parameter}: {$text}" : $text;
        }

        $message = $parts !== []
            ? implode(' · ', $parts)
            : (self::HTTP_STATUS_MESSAGES[$httpStatusCode] ?? 'Unbekannter Fehler');

        if ($parts === [] && $responseData === [] && $rawBody !== null && trim($rawBody) !== '') {
            $message .= ' — ' . mb_substr(trim($rawBody), 0, 300);
        }

        if ($requestId) {
            $message .= " (X-Request-Id: {$requestId})";
        }

        return new self($message, $httpStatusCode, 'HTTP_' . $httpStatusCode, $responseData, $requestId);
    }

    public static function unauthorized(string $message = 'Ungültiger cisbox API-Key (Header X-API-Key).'): self
    {
        return new self($message, 401, 'UNAUTHORIZED');
    }

    public static function notFound(string $message = 'Datensatz in cisbox nicht gefunden.'): self
    {
        return new self($message, 404, 'NOT_FOUND');
    }

    public static function connectionError(string $message): self
    {
        return new self('Verbindungsfehler zur cisbox API: ' . $message, 503, 'CONNECTION_ERROR');
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine cisbox-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function unknownOperation(string $method, string $path): self
    {
        return new self(
            sprintf(
                'Unbekannte Operation "%s %s". Verfügbare Endpunkte liefert integrations.cisbox.overview.',
                mb_strtoupper($method),
                $path
            ),
            400,
            'UNKNOWN_OPERATION'
        );
    }

    public static function missingPathParameter(string $name, string $path): self
    {
        return new self(
            sprintf('Pfad-Parameter "%s" fehlt für "%s".', $name, $path),
            400,
            'MISSING_PATH_PARAMETER'
        );
    }

    public static function invalidResponse(string $detail = ''): self
    {
        return new self(
            'Ungültige Antwort der cisbox API.' . ($detail !== '' ? ' ' . $detail : ''),
            500,
            'INVALID_RESPONSE'
        );
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Korrelations-ID der Antwort — für Rückfragen beim cisbox-Support. */
    public function getRequestId(): ?string
    {
        return $this->requestId;
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

    public function isClientError(): bool
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    public function isServerError(): bool
    {
        return $this->getCode() >= 500;
    }

    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'http_status' => $this->getCode(),
                'request_id' => $this->requestId,
            ],
        ];
    }
}
