<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_whatsapp_phone_numbers';

        // Rein additiv: eigene Kind-Tabelle je Rufnummer einer WABA.
        // Bestehende integrations_whatsapp_accounts bleiben unverändert
        // (phone_number/phone_number_id dort tragen weiterhin die primäre Nummer).
        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('whatsapp_account_id')
                ->constrained('integrations_whatsapp_accounts')
                ->onDelete('cascade');
            $table->string('phone_number')->nullable();       // display_phone_number
            $table->string('phone_number_id')->nullable();    // Meta phone_number_id
            $table->string('display_name')->nullable();       // verified_name
            $table->string('status')->nullable();             // z.B. CONNECTED
            $table->string('quality_rating')->nullable();     // GREEN/YELLOW/RED
            $table->timestamps();

            // phone_number_id ist bei Meta global eindeutig => Upsert-Key
            $table->unique('phone_number_id', 'iwpn_phone_number_id_uq');
            $table->index('whatsapp_account_id', 'iwpn_account_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_whatsapp_phone_numbers');
    }
};
