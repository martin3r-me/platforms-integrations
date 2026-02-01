<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Services\SipgateIntegrationService;

/**
 * Command zum proaktiven Erneuern von Sipgate Tokens
 *
 * Dieses Command sollte regelmäßig ausgeführt werden (z.B. alle 30 Minuten via Cron),
 * um Tokens zu erneuern, bevor sie ablaufen.
 *
 * Scheduler-Eintrag (Kernel.php):
 * $schedule->command('integrations:sipgate-refresh-tokens')->everyThirtyMinutes();
 */
class SipgateRefreshTokens extends Command
{
    protected $signature = 'integrations:sipgate-refresh-tokens
        {--force : Erzwingt Refresh auch wenn Token noch gültig}
        {--buffer=300 : Sekunden vor Ablauf ab denen refreshed wird}
        {--dry-run : Zeigt was gemacht würde, ohne es zu tun}';

    protected $description = 'Erneuert ablaufende Sipgate OAuth-Tokens proaktiv';

    public function __construct(
        protected SipgateIntegrationService $integrationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starte Sipgate Token-Refresh...');

        $integration = Integration::where('key', 'sipgate')->first();

        if (!$integration) {
            $this->warn('Sipgate-Integration nicht gefunden.');
            return self::SUCCESS;
        }

        $connections = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('status', 'active')
            ->get();

        if ($connections->isEmpty()) {
            $this->info('Keine aktiven Sipgate-Verbindungen gefunden.');
            return self::SUCCESS;
        }

        $buffer = (int) $this->option('buffer');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $refreshed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($connections as $connection) {
            $credentials = $connection->credentials ?? [];
            $expiresAt = $credentials['oauth']['expires_at'] ?? null;
            $refreshToken = $credentials['oauth']['refresh_token'] ?? null;

            // Kein Refresh Token verfügbar
            if (!$refreshToken) {
                $this->warn("Connection {$connection->id}: Kein Refresh-Token vorhanden.");
                $skipped++;
                continue;
            }

            // Prüfen ob Refresh nötig
            $needsRefresh = $force;

            if (!$needsRefresh && $expiresAt) {
                $secondsUntilExpiry = $expiresAt - now()->timestamp;
                $needsRefresh = $secondsUntilExpiry <= $buffer;

                if (!$needsRefresh) {
                    $this->line("Connection {$connection->id}: Token noch gültig für " .
                        round($secondsUntilExpiry / 60) . " Minuten, überspringe.");
                    $skipped++;
                    continue;
                }
            }

            if ($dryRun) {
                $this->info("[DRY-RUN] Würde Token für Connection {$connection->id} erneuern.");
                $refreshed++;
                continue;
            }

            try {
                $this->line("Connection {$connection->id}: Erneuere Token...");

                $this->integrationService->refreshToken($connection);

                $this->info("Connection {$connection->id}: Token erfolgreich erneuert.");
                $refreshed++;

                Log::info('Sipgate token refreshed via command', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->owner_user_id,
                ]);
            } catch (\Exception $e) {
                $errors++;
                $this->error("Connection {$connection->id}: {$e->getMessage()}");

                Log::error('Sipgate token refresh failed', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->owner_user_id,
                    'error' => $e->getMessage(),
                ]);

                // Connection als fehlerhaft markieren wenn Refresh fehlschlägt
                $connection->update([
                    'status' => 'error',
                    'last_error' => 'Token-Refresh fehlgeschlagen: ' . $e->getMessage(),
                ]);
            }
        }

        $this->info("Token-Refresh abgeschlossen:");
        $this->info("  - Erneuert: {$refreshed}");
        $this->info("  - Übersprungen: {$skipped}");
        $this->info("  - Fehler: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
