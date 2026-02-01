<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Models\IntegrationsSipgateWebhookEvent;
use Platform\Integrations\Services\SipgateWebhookService;

/**
 * Command zum Aufräumen alter Webhook-Events und Retry fehlgeschlagener Events
 *
 * Dieses Command sollte regelmäßig ausgeführt werden (z.B. täglich via Cron).
 *
 * Scheduler-Eintrag (Kernel.php):
 * $schedule->command('integrations:sipgate-webhook-cleanup')->daily();
 * $schedule->command('integrations:sipgate-webhook-cleanup --retry-failed')->everyFiveMinutes();
 */
class SipgateWebhookCleanup extends Command
{
    protected $signature = 'integrations:sipgate-webhook-cleanup
        {--days=30 : Anzahl Tage, nach denen Events gelöscht werden}
        {--retry-failed : Fehlgeschlagene Events erneut verarbeiten}
        {--dry-run : Zeigt was gemacht würde, ohne es zu tun}';

    protected $description = 'Bereinigt alte Sipgate Webhook-Events und verarbeitet fehlgeschlagene Events erneut';

    public function __construct(
        protected SipgateWebhookService $webhookService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $retryFailed = $this->option('retry-failed');
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');

        if ($retryFailed) {
            return $this->handleRetries($dryRun);
        }

        return $this->handleCleanup($days, $dryRun);
    }

    protected function handleRetries(bool $dryRun): int
    {
        $this->info('Verarbeite fehlgeschlagene Sipgate Webhook-Events erneut...');

        $pendingRetries = IntegrationsSipgateWebhookEvent::needsRetry()->count();

        $this->info("Gefundene Events für Retry: {$pendingRetries}");

        if ($pendingRetries === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Würde Events erneut verarbeiten.');
            return self::SUCCESS;
        }

        $processed = $this->webhookService->processRetries();

        $this->info("Events verarbeitet: {$processed}");

        Log::info('Sipgate webhook retries processed', [
            'processed' => $processed,
            'pending' => $pendingRetries,
        ]);

        return self::SUCCESS;
    }

    protected function handleCleanup(int $days, bool $dryRun): int
    {
        $this->info("Bereinige Sipgate Webhook-Events älter als {$days} Tage...");

        $cutoff = now()->subDays($days);

        // Zähle zu löschende Events
        $toDelete = IntegrationsSipgateWebhookEvent::where('created_at', '<', $cutoff)
            ->where('processing_status', IntegrationsSipgateWebhookEvent::STATUS_PROCESSED)
            ->count();

        $this->info("Zu löschende Events: {$toDelete}");

        if ($toDelete === 0) {
            $this->info('Keine Events zum Löschen gefunden.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Würde Events löschen.');
            return self::SUCCESS;
        }

        $deleted = $this->webhookService->cleanupOldEvents($days);

        $this->info("Events gelöscht: {$deleted}");

        // Statistiken ausgeben
        $this->outputStats();

        return self::SUCCESS;
    }

    protected function outputStats(): void
    {
        $total = IntegrationsSipgateWebhookEvent::count();
        $pending = IntegrationsSipgateWebhookEvent::where('processing_status', IntegrationsSipgateWebhookEvent::STATUS_PENDING)->count();
        $processed = IntegrationsSipgateWebhookEvent::where('processing_status', IntegrationsSipgateWebhookEvent::STATUS_PROCESSED)->count();
        $failed = IntegrationsSipgateWebhookEvent::where('processing_status', IntegrationsSipgateWebhookEvent::STATUS_FAILED)->count();

        $this->info('Aktuelle Statistiken:');
        $this->table(
            ['Status', 'Anzahl'],
            [
                ['Gesamt', $total],
                ['Pending', $pending],
                ['Verarbeitet', $processed],
                ['Fehlgeschlagen', $failed],
            ]
        );
    }
}
