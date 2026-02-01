<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration für Sipgate Accounts
 *
 * Diese Tabelle speichert synchronisierte Sipgate Account-Informationen
 * wie User-Profile, zugewiesene Telefonnummern und Geräte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations_sipgate_accounts', function (Blueprint $table) {
            $table->id();

            // Sipgate-spezifische IDs
            $table->string('sipgate_user_id')->index(); // Sipgate User/Account ID
            $table->string('sipgate_sub_id')->nullable(); // Sub-Account ID (für Business)

            // Verbindung zur Integration
            $table->foreignId('integration_connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            // Platform User-Referenz
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Account-Details
            $table->string('email')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('company')->nullable();
            $table->string('locale')->nullable(); // z.B. 'de_DE'
            $table->string('timezone')->nullable();
            $table->boolean('admin')->default(false);
            $table->boolean('active')->default(true);

            // Telefonnummern (als JSON Array)
            $table->json('phone_numbers')->nullable();

            // Faxnummern (als JSON Array)
            $table->json('fax_numbers')->nullable();

            // Geräte/Devices (als JSON Array)
            $table->json('devices')->nullable();

            // Voicemail-Einstellungen (als JSON)
            $table->json('voicemail_settings')->nullable();

            // Balance/Guthaben (für Prepaid)
            $table->decimal('balance', 10, 2)->nullable();
            $table->string('balance_currency', 3)->default('EUR');

            // Zusätzliche Metadaten
            $table->json('meta')->nullable();

            // Sync-Status
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('pending'); // pending, synced, error

            $table->timestamps();
            $table->softDeletes();

            // Eindeutigkeit: Ein Sipgate Account pro Connection
            $table->unique(
                ['sipgate_user_id', 'integration_connection_id'],
                'sipgate_acc_user_conn_uniq'
            );

            // Indices für schnelle Abfragen
            $table->index(['user_id', 'active'], 'sipgate_acc_user_active_idx');
            $table->index(['integration_connection_id', 'sync_status'], 'sipgate_acc_conn_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_sipgate_accounts');
    }
};
