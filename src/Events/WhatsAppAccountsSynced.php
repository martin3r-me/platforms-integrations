<?php

namespace Platform\Integrations\Events;

use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event das nach dem Sync von WhatsApp-Accounts gefeuert wird
 *
 * Ermöglicht anderen Modulen (z.B. Core/Comms) auf den Sync zu reagieren
 */
class WhatsAppAccountsSynced
{
    use Dispatchable, SerializesModels;

    /**
     * @param IntegrationConnection $connection Die Meta-IntegrationConnection
     * @param Collection $accounts Die synchronisierten WhatsApp-Accounts
     */
    public function __construct(
        public IntegrationConnection $connection,
        public Collection $accounts,
    ) {}
}
