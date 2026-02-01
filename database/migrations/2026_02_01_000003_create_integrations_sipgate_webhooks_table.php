<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration für Sipgate Webhooks
 *
 * Diese Tabelle speichert:
 * 1. Webhook-Registrierungen (welche Webhooks sind aktiv)
 * 2. Empfangene Webhook-Events (für Idempotency und Audit)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Webhook-Registrierungen
        Schema::create('integrations_sipgate_webhooks', function (Blueprint $table) {
            $table->id();

            // Verbindung zur Integration
            $table->foreignId('integration_connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            // Platform User-Referenz
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Sipgate Webhook-ID (von Sipgate vergeben)
            $table->string('sipgate_webhook_id')->nullable();

            // Webhook-Konfiguration
            $table->string('event_type'); // z.B. 'newCall', 'onAnswer', 'onHangup', 'dtmf'
            $table->string('direction')->nullable(); // 'in', 'out', 'both'
            $table->string('callback_url'); // URL die aufgerufen wird

            // Verifizierung
            $table->string('secret_hash', 64)->nullable(); // SHA-256 des Webhook-Secrets
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            // Status
            $table->string('status')->default('active'); // active, inactive, error
            $table->text('last_error')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('trigger_count')->default(0);

            // Metadaten
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indices
            $table->index(['integration_connection_id', 'event_type'], 'sipgate_wh_conn_event_idx');
            $table->index(['status'], 'sipgate_wh_status_idx');
            $table->unique(['sipgate_webhook_id'], 'sipgate_wh_id_uniq');
        });

        // Webhook-Event-Log (für Idempotency und Audit)
        Schema::create('integrations_sipgate_webhook_events', function (Blueprint $table) {
            $table->id();

            // Referenz zum Webhook
            $table->foreignId('webhook_id')
                ->nullable()
                ->constrained('integrations_sipgate_webhooks')
                ->nullOnDelete();

            // Verbindung zur Integration (redundant, für schnelle Abfragen)
            $table->foreignId('integration_connection_id')
                ->nullable()
                ->constrained('integration_connections')
                ->nullOnDelete();

            // Event-Identifikation (für Idempotency)
            $table->string('event_id', 128)->unique(); // Von Sipgate oder selbst generiert
            $table->string('idempotency_key', 128)->nullable(); // Für Duplikat-Erkennung

            // Event-Details
            $table->string('event_type'); // newCall, onAnswer, onHangup, dtmf
            $table->string('direction')->nullable(); // in, out
            $table->string('call_id')->nullable(); // Sipgate Call-ID

            // Anrufer/Angerufener
            $table->string('caller')->nullable(); // Anrufer-Nummer
            $table->string('callee')->nullable(); // Angerufene Nummer
            $table->string('caller_name')->nullable();

            // Payload (vollständig, für Debugging)
            $table->json('payload')->nullable();

            // Verarbeitung
            $table->string('processing_status')->default('pending'); // pending, processing, processed, failed
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            // Signatur-Verifizierung
            $table->boolean('signature_valid')->nullable();
            $table->string('signature_header')->nullable();

            // Request-Metadaten
            $table->string('ip_address', 45)->nullable();
            $table->text('headers')->nullable(); // Für Debugging
            $table->string('request_id', 64)->nullable();

            $table->timestamps();

            // Indices für Idempotency und Abfragen
            $table->index(['idempotency_key'], 'sipgate_whe_idempotency_idx');
            $table->index(['call_id'], 'sipgate_whe_call_idx');
            $table->index(['processing_status', 'next_retry_at'], 'sipgate_whe_retry_idx');
            $table->index(['integration_connection_id', 'event_type', 'created_at'], 'sipgate_whe_conn_event_date_idx');
            $table->index(['created_at'], 'sipgate_whe_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_sipgate_webhook_events');
        Schema::dropIfExists('integrations_sipgate_webhooks');
    }
};
