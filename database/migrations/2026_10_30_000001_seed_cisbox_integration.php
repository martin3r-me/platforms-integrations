<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die cisbox Invoice API v1
 *
 * Registriert cisbox als verfügbare Integration mit API-Key-Auth. Der Key
 * wird pro Connection in credentials.api_key gespeichert, ein optionales Label
 * der Installation in credentials.instance und eine abweichende Adresse in
 * credentials.base_url.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'cisbox'],
            [
                'name' => 'cisbox',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'cisbox Invoice API v1 — Eingangsrechnungen, offene Posten, Kreditoren, '
                        . 'Lieferungen, Zahlungen und Buchhaltungs-Stammdaten. Die API ist vollständig lesend. '
                        . 'Verbindung über einen technischen API-Key, der für genau eine Installation gilt.',
                    'icon' => 'heroicon-o-document-currency-euro',
                    'website' => 'https://www.cisbox.com',
                    'documentation' => 'https://api.cisbox.com/docs/ui/invoice-api-v1',
                    'api_version' => 'v1',
                    'features' => [
                        'invoices' => 'Eingangsrechnungen mit Freigabe-, Kontierungs- und Zahlungsstatus',
                        'pending' => 'Offene Posten mit offenem Betrag, Fälligkeit und Skontofrist',
                        'items' => 'Rechnungspositionen samt Kontierungs-Splits (Konto, Steuerschlüssel, Code 1–3)',
                        'pdf' => 'Beleg-PDF einer Rechnung',
                        'creditors' => 'Kreditoren mit Bankverbindung, Zahlungsbedingungen und Artikelpreisen',
                        'deliveries' => 'Lieferscheine und Positionen zum Abgleich mit der Rechnung',
                        'payments' => 'Zahlungen und Zahlungsvorschläge mit Status',
                        'masterdata' => 'Sachkonten, Zuordnungen, Kostenstellen, Zahlungsarten, Steuerschlüssel',
                        'call' => 'Generischer Zugriff auf alle 27 Endpunkte der API v1',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('cisbox integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'cisbox')->delete();

        Log::info('cisbox integration removed');
    }
};
