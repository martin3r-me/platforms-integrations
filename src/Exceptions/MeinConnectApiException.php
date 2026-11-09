<?php

namespace Platform\Integrations\Exceptions;

use Exception;

/**
 * Fehler beim Auslesen einer MeinConnect-Gastfreigabe (CoolCooking-Boxen).
 * Meldungen sind bewusst ohne Freigabelink formuliert – der Link ist ein Geheimnis.
 */
class MeinConnectApiException extends Exception
{
    public function __construct(string $message, int $code = 500, protected ?string $errorCode = null, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function noConnection(): self
    {
        return new self('Keine MeinConnect-Verbindung konfiguriert.', 401, 'NO_CONNECTION');
    }

    public static function invalidShareUrl(): self
    {
        return new self('Bitte den vollständigen MeinConnect-Freigabelink eingeben (https://meinconnect.app/freigabe/…).', 422, 'INVALID_SHARE_URL');
    }

    public static function unreachable(int $status): self
    {
        return new self("MeinConnect nicht erreichbar (HTTP {$status}).", 503, 'UNREACHABLE');
    }

    public static function unrecognized(): self
    {
        return new self('Antwort ist keine MeinConnect-Seite. Freigabelink prüfen.', 502, 'UNRECOGNIZED');
    }

    public static function noDevices(): self
    {
        return new self('Keine freigegebenen Geräte gefunden. Freigabe prüfen.', 404, 'NO_DEVICES');
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
