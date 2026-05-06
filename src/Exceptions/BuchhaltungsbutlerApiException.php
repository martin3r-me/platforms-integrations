<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Exception für BuchhaltungsButler API Fehler.
 *
 * Die API antwortet bei Fehlern typischerweise mit einem JSON-Body wie:
 * {"success": false, "error": "Beschreibung", "errors": [...]}.
 */
class BuchhaltungsbutlerApiException extends Exception
{
    protected ?string $errorCode = null;
    protected ?array $responseData = null;

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
        $message = $responseData['error']
            ?? $responseData['message']
            ?? 'BuchhaltungsButler API-Fehler (HTTP ' . $httpStatusCode . ')';

        if (!empty($responseData['errors']) && is_array($responseData['errors'])) {
            $details = [];
            foreach ($responseData['errors'] as $err) {
                if (is_string($err)) {
                    $details[] = $err;
                } elseif (is_array($err)) {
                    $details[] = json_encode($err, JSON_UNESCAPED_UNICODE);
                }
            }
            if ($details) {
                $message .= ' | Details: ' . implode('; ', $details);
            }
        }

        return new self(
            $message,
            $httpStatusCode,
            $responseData['code'] ?? null,
            $responseData
        );
    }

    public static function noConnection(): self
    {
        return new self(
            'Keine BuchhaltungsButler-Verbindung für diesen Benutzer konfiguriert.',
            401,
            'NO_CONNECTION'
        );
    }

    public static function missingCredentials(): self
    {
        return new self(
            'BuchhaltungsButler-Credentials unvollständig (api_client, api_secret oder api_key fehlt).',
            401,
            'MISSING_CREDENTIALS'
        );
    }

    public static function connectionError(string $message): self
    {
        return new self(
            'Verbindungsfehler zur BuchhaltungsButler API: ' . $message,
            503,
            'CONNECTION_ERROR'
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
