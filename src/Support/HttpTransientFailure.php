<?php

namespace Platform\Integrations\Support;

use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Klassifiziert Fehler des HTTP-Clients.
 *
 * Hintergrund: Ein Timeout oder ein abgerissener Socket sagt nichts über die
 * Gültigkeit der hinterlegten Credentials aus. Wird eine Connection deswegen
 * trotzdem auf status='error' gesetzt, verschwindet sie für alle Kollegen, die
 * sie geteilt nutzen — der IntegrationConnectionResolver findet geteilte und
 * Team-Connections ausschliesslich mit status='active'. Ein einzelner langsamer
 * Request wuerde also das ganze Team aussperren, bis der Owner die Verbindung
 * manuell neu herstellt.
 *
 * Deshalb: transiente Fehler nur protokollieren, niemals den Status kippen.
 */
final class HttpTransientFailure
{
    /**
     * Netzwerk-/Verbindungsfehler (Timeout, DNS, Reset, TLS-Abbruch). Die
     * Connection selbst bleibt gueltig.
     */
    public static function isTransient(Throwable $e): bool
    {
        return $e instanceof ConnectionException;
    }

    /**
     * Retry nur, wenn ein zweiter Versuch realistisch anders ausgeht.
     *
     * Ein Read-Timeout ("Operation timed out ... with 0 bytes received") heisst:
     * der Server hat die Anfrage angenommen und rechnet zu lange. Ein Retry
     * wartet nur noch einmal dieselbe Zeit ab und verdoppelt die Last — also
     * nicht wiederholen. Connect-, DNS- und Reset-Fehler sind dagegen typisch
     * sporadisch und lohnen einen zweiten Versuch.
     */
    public static function isRetryable(Throwable $e): bool
    {
        if (!self::isTransient($e)) {
            return false;
        }

        return !str_contains(mb_strtolower($e->getMessage()), 'operation timed out');
    }

    /**
     * Fuer Endnutzer lesbare Einordnung statt roher cURL-Meldung — inklusive
     * der Information, dass die Verbindung weiter nutzbar ist.
     */
    public static function describe(Throwable $e, string $service, int $timeout): string
    {
        if (!self::isTransient($e)) {
            return $e->getMessage();
        }

        if (str_contains(mb_strtolower($e->getMessage()), 'operation timed out')) {
            return "Zeitüberschreitung nach {$timeout}s: {$service} hat die Anfrage angenommen, "
                . 'aber nicht rechtzeitig beantwortet. Die Verbindung bleibt aktiv — bitte die Abfrage '
                . 'verkleinern (kleineres pageSize, engerer Zeitraum, weniger Felder) und erneut versuchen.';
        }

        return "{$service} war vorübergehend nicht erreichbar. Die Verbindung bleibt aktiv, "
            . 'bitte erneut versuchen. (' . $e->getMessage() . ')';
    }
}
