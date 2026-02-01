<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration für Sipgate Token-History/Audit
 *
 * Diese Tabelle speichert Token-Events für Audit-Zwecke:
 * - Token-Erstellung (OAuth)
 * - Token-Refresh
 * - Token-Revoke
 * - Token-Rotation
 *
 * WICHTIG: Sensible Token-Werte werden NICHT direkt gespeichert,
 * sondern nur gehashte Referenzen für Audit/Debugging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations_sipgate_tokens', function (Blueprint $table) {
            $table->id();

            // Verbindung zur Integration Connection
            $table->foreignId('integration_connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            // Platform User-Referenz
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Token-Event-Typ
            $table->string('event_type'); // created, refreshed, revoked, rotated, expired, error

            // Token-Referenz (gehashter Prefix des Tokens für Debugging)
            $table->string('token_hash', 64)->nullable(); // SHA-256 des access_token
            $table->string('refresh_token_hash', 64)->nullable(); // SHA-256 des refresh_token

            // Token-Metadaten (keine sensiblen Daten!)
            $table->integer('expires_in')->nullable(); // Gültigkeit in Sekunden
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('issued_at')->nullable();

            // Scopes die gewährt wurden
            $table->json('scopes')->nullable();

            // Event-Details
            $table->string('trigger_source')->nullable(); // oauth_callback, refresh_job, manual, api_call
            $table->text('error_message')->nullable(); // Bei Fehlern
            $table->string('error_code')->nullable();

            // IP/User-Agent für Audit
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Request-ID für Tracing
            $table->string('request_id', 64)->nullable();

            // Zusätzliche Metadaten
            $table->json('meta')->nullable();

            $table->timestamps();

            // Indices für schnelle Abfragen
            $table->index(['integration_connection_id', 'event_type'], 'sipgate_tok_conn_event_idx');
            $table->index(['user_id', 'created_at'], 'sipgate_tok_user_created_idx');
            $table->index(['event_type', 'created_at'], 'sipgate_tok_event_created_idx');
            $table->index(['request_id'], 'sipgate_tok_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_sipgate_tokens');
    }
};
