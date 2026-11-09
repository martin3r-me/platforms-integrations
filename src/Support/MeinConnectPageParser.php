<?php

namespace Platform\Integrations\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Liest die öffentlichen HTML-Seiten einer MeinConnect-Gastfreigabe
 * (https://meinconnect.app/freigabe/{token}) aus. MeinConnect bietet keine
 * offizielle API, daher HTML-Auslesung. Reine Funktion, ohne Laravel/HTTP,
 * damit sie gegen gespeicherte Seiten getestet werden kann.
 *
 * Es gibt zwei Geräte-Layouts (Stand 2026-11, an echten Seiten geprüft):
 *  - "classic": Abschnitte per HTML-Kommentar markiert
 *    (<!-- Aktueller Status --> … <!-- Wöchentliche Zeiten -->), Zeitstempel
 *    "09.11.2026 10:28 Uhr (vor 4 Minuten)".
 *  - "eaton": Abschnitte per data-tour-Attribut, Tür-/Verbindungs-/Fehlerstatus als
 *    "cn-error-chip"-Spans HINTER dem Wochenplan, Zeitstempel "09.11.2026 10:32:39".
 *
 * Wichtig: "Tür geöffnet" steht in beiden Layouts auch in Diagramm-Legenden und
 * Export-JavaScript. Der Türstatus wird deshalb nur aus den Status-Elementen gelesen,
 * nie per Volltextsuche über die ganze Seite.
 */
final class MeinConnectPageParser
{
    public const ORIGIN = 'https://meinconnect.app';
    public const SHARE_URL_PATTERN = '~^https://meinconnect\.app/freigabe/[A-Za-z0-9]+$~';

    private const WEEKDAYS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    /** Erkennt, ob die Antwort überhaupt eine MeinConnect-Seite ist. */
    public static function looksLikeMeinConnect(string $html): bool
    {
        return stripos($html, 'meinconnect') !== false;
    }

    /**
     * Gerätelinks der Freigabe-Übersicht. Testgeräte werden ausgeblendet.
     *
     * @return list<array{id:string, name:string, url:string}>
     */
    public static function deviceLinks(string $html, string $shareUrl): array
    {
        $prefix = rtrim($shareUrl, '/') . '/geraet/';
        $found = [];

        if (!preg_match_all('~<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $m) {
            $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($href, '/')) {
                $href = self::ORIGIN . $href;
            }
            if (!str_starts_with($href, $prefix)) {
                continue;
            }

            $id = substr($href, strlen($prefix));
            if (!preg_match('~^[A-Za-z0-9-]+$~', $id)) {
                continue;
            }

            $name = self::text($m[2]);
            if ($name === '' || preg_match('~testger(ä|ae)t~iu', $name)) {
                continue;
            }

            $found[$id] = ['id' => $id, 'name' => $name, 'url' => $prefix . $id];
        }

        return array_values($found);
    }

    /**
     * Status eines Geräts.
     *
     * @return array{
     *   name:string, serial:string, layout:string, mode:string,
     *   temperature:array{actual:?string, target:?string},
     *   core:array{actual:?string, target:?string},
     *   door:?string, connected:?bool, alerts:list<string>,
     *   updated_at:?string, age_seconds:?int,
     *   schedule:?array{start:string, end:string}, scheduled_remaining_minutes:?int
     * }
     */
    public static function parseDevice(string $html, string $name, ?DateTimeImmutable $now = null): array
    {
        $tz = new DateTimeZone('Europe/Berlin');
        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone($tz);

        if (str_contains($html, '<!-- Aktueller Status -->')) {
            $layout = 'classic';
            $status = self::between($html, '<!-- Aktueller Status -->', '<!-- Wöchentliche Zeiten -->');
            $week = self::between($html, '<!-- Wöchentliche Zeiten -->', 'Letztes Update:');
            $chips = '';
        } elseif (str_contains($html, 'data-tour="eaton-weekly-times"')) {
            $layout = 'eaton';
            $status = self::between($html, 'Aktueller Status', 'data-tour="eaton-weekly-times"');
            $tail = self::between($html, 'data-tour="eaton-weekly-times"', 'Letztes Update:');
            // Wochenplan und Status-Chips teilen sich diesen Abschnitt; die Chips stehen
            // in der nachfolgenden Karte (Verbindung, Tür, Fehler).
            $cut = strpos($tail, 'cn-error-chip');
            if ($cut === false) {
                $cut = strpos($tail, 'cn-ok-line');
            }
            if ($cut !== false) {
                // Auf den Anfang des umschließenden Tags zurückgehen.
                $open = strrpos(substr($tail, 0, $cut), '<');
                $cut = $open === false ? $cut : $open;
            }
            $week = $cut === false ? $tail : substr($tail, 0, $cut);
            $chips = $cut === false ? '' : substr($tail, $cut);
        } else {
            throw new \RuntimeException('Statusformat nicht erkannt');
        }

        $fields = [];
        if (preg_match_all('~<h3\b[^>]*class="[^"]*mc-status-card-status[^"]*"[^>]*>(.*?)</h3>~is', $status, $m)) {
            $fields = array_map([self::class, 'text'], $m[1]);
        }

        $alerts = [];
        if (preg_match_all('~<h[34]\b[^>]*class="[^"]*(?:alert-heading|cn-alert-title)[^"]*"[^>]*>(.*?)</h[34]>~is', $status . $chips, $m)) {
            $alerts = array_values(array_filter(array_map([self::class, 'text'], $m[1])));
        }

        [$door, $connected, $chipAlerts] = self::readChips($chips);
        $alerts = array_values(array_unique(array_merge($alerts, $chipAlerts)));

        // Classic-Layout: Tür/Verbindung erscheinen (wenn überhaupt) als Alert im Statusbereich.
        $statusText = self::text($status);
        if ($door === null && preg_match('~Tür\s+(geöffnet|offen)~u', $statusText)) {
            $door = 'open';
        }
        if ($connected === null && str_contains($statusText, 'Gerät nicht verbunden')) {
            $connected = false;
        }
        if ($door === 'open' && !self::containsAlert($alerts, 'Tür')) {
            $alerts[] = 'Tür geöffnet';
        }
        if ($connected === false && !self::containsAlert($alerts, 'verbunden')) {
            $alerts[] = 'Gerät nicht verbunden';
        }

        if ($fields === [] && $alerts === [] && $door === null) {
            throw new \RuntimeException('Statusformat nicht erkannt');
        }

        $heading = preg_match('~<h4\b[^>]*>(.*?)</h4>~is', $html, $m) ? self::text($m[1]) : '';
        $serial = $heading !== '' && str_starts_with($heading, $name) ? trim(substr($heading, strlen($name))) : '';

        [$updatedAt, $age] = self::readUpdated($html, $now, $tz);
        [$schedule, $remaining] = self::readSchedule(self::text($week), $now);

        return [
            'name'                        => $name,
            'serial'                      => $serial,
            'layout'                      => $layout,
            'mode'                        => $fields[0] ?? '',
            'temperature'                 => self::pair($fields[1] ?? ''),
            'core'                        => self::pair($fields[2] ?? ''),
            'door'                        => $door,
            'connected'                   => $connected,
            'alerts'                      => $alerts,
            'updated_at'                  => $updatedAt,
            'age_seconds'                 => $age,
            'schedule'                    => $schedule,
            'scheduled_remaining_minutes' => $remaining,
        ];
    }

    /** HTML → normalisierter Text (Skripte, Styles, SVGs entfernt). */
    public static function text(string $html): string
    {
        $html = preg_replace('~<(script|style|svg)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('~\s+~u', ' ', $text) ?? $text);
    }

    // ---------------------------------------------------------------------

    /** @return array{0:?string, 1:?bool, 2:list<string>} Tür, Verbindung, weitere Fehler */
    private static function readChips(string $chips): array
    {
        $door = null;
        $connected = null;
        $alerts = [];

        if ($chips === '' || !preg_match_all('~<span\b([^>]*class="[^"]*cn-error-chip[^"]*"[^>]*)>(.*?)</span>~is', $chips, $m, PREG_SET_ORDER)) {
            return [$door, $connected, $alerts];
        }

        foreach ($m as $chip) {
            $attrs = $chip[1];
            $inner = $chip[2];
            $text = self::text($inner);
            $title = preg_match('~title="([^"]*)"~i', $attrs, $t) ? html_entity_decode($t[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';

            if (str_contains($inner, 'fa-door-open') || preg_match('~Tür\s+(geöffnet|offen)~u', $text)) {
                $door = 'open';
            } elseif (str_contains($inner, 'fa-door-closed') || str_contains($text, 'Tür geschlossen')) {
                $door = 'closed';
            } elseif (str_contains($inner, 'fa-wifi') || preg_match('~verbunden~iu', $title . ' ' . $text)) {
                $connected = !preg_match('~nicht|getrennt|offline~iu', $title . ' ' . $text);
            } elseif ($text !== '') {
                $alerts[] = $text;
            }
        }

        return [$door, $connected, $alerts];
    }

    /** @return array{0:?string, 1:?int} ISO-Zeitpunkt (UTC), Alter in Sekunden */
    private static function readUpdated(string $html, DateTimeImmutable $now, DateTimeZone $tz): array
    {
        if (!preg_match('~Letztes Update:\s*(?:</span>\s*<span[^>]*>)?\s*(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?~', $html, $m)) {
            return [null, null];
        }

        $stamp = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            sprintf('%s-%s-%s %s:%s:%s', $m[3], $m[2], $m[1], $m[4], $m[5], ($m[6] ?? '') !== '' ? $m[6] : '00'),
            $tz
        );

        if (!$stamp) {
            return [null, null];
        }

        $age = max(0, $now->getTimestamp() - $stamp->getTimestamp());

        return [$stamp->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM), $age];
    }

    /** @return array{0:?array{start:string,end:string}, 1:?int} Heute geplantes Fenster, Restminuten */
    private static function readSchedule(string $weekText, DateTimeImmutable $now): array
    {
        $day = self::WEEKDAYS[(int) $now->format('N') - 1];
        $parts = preg_split('~\b(' . implode('|', self::WEEKDAYS) . ')\b~u', $weekText, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $today = '';
        for ($i = 1; $i < count($parts); $i += 2) {
            if ($parts[$i] === $day) {
                $today = $parts[$i + 1] ?? '';
                break;
            }
        }

        if (!preg_match_all('~\b(\d{2}):(\d{2})\b~', $today, $t, PREG_SET_ORDER) || count($t) < 2) {
            return [null, null];
        }

        $start = (int) $t[0][1] * 60 + (int) $t[0][2];
        $end = (int) $t[1][1] * 60 + (int) $t[1][2];
        $current = (int) $now->format('G') * 60 + (int) $now->format('i');

        return [
            ['start' => $t[0][0], 'end' => $t[1][0]],
            $current >= $start && $current < $end ? $end - $current : null,
        ];
    }

    /** "75°C / 120°C" → [actual, target] */
    private static function pair(string $value): array
    {
        $parts = array_map('trim', explode('/', $value));

        return [
            'actual' => ($parts[0] ?? '') !== '' ? $parts[0] : null,
            'target' => ($parts[1] ?? '') !== '' ? $parts[1] : null,
        ];
    }

    private static function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        if ($start === false) {
            return '';
        }
        $rest = substr($html, $start + strlen($from));
        $end = strpos($rest, $to);

        return $end === false ? $rest : substr($rest, 0, $end);
    }

    private static function containsAlert(array $alerts, string $needle): bool
    {
        foreach ($alerts as $a) {
            if (mb_stripos($a, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
