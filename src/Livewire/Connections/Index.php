<?php

namespace Platform\Integrations\Livewire\Connections;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Models\IntegrationsWhatsAppAccount;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Services\IntegrationAccessService;
use Platform\Integrations\Services\IntegrationsFacebookPageService;
use Platform\Integrations\Services\IntegrationsInstagramAccountService;
use Platform\Integrations\Services\IntegrationsWhatsAppAccountService;
use Platform\Integrations\Services\IntegrationsGithubRepositoryService;

class Index extends Component
{
    use WithPagination;

    public bool $modalShow = false;
    public bool $editModalShow = false;

    public ?int $editingId = null;

    public string $integrationKey = '';
    public string $authScheme = 'oauth2'; // oauth2|api_key|basic|bearer|custom
    public string $status = 'draft';

    // UI-Form: wir speichern credentials als JSON-String und parsen beim Save
    public string $credentialsJson = "{}";

    public ?string $lastError = null;

    // Sync-Status
    public bool $isSyncing = false;
    public ?string $syncMessage = null;
    public ?string $syncError = null;

    public function render()
    {
        /** @var User $user */
        $user = auth()->user();

        $connections = IntegrationConnection::query()
            ->with(['integration', 'ownerUser'])
            ->where('owner_user_id', $user->id)
            ->orderByDesc('updated_at')
            ->paginate(15);

        $integrations = Integration::query()
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get();

        // Meta-Connection prüfen
        $metaConnection = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', function ($q) {
                $q->where('key', 'meta');
            })
            ->where('owner_user_id', $user->id)
            ->first();

        // GitHub-Connection prüfen
        $githubConnection = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', function ($q) {
                $q->where('key', 'github');
            })
            ->where('owner_user_id', $user->id)
            ->first();

        return view('integrations::livewire.connections.index', [
            'connections' => $connections,
            'integrations' => $integrations,
            'metaConnection' => $metaConnection,
            'githubConnection' => $githubConnection,
        ])->layout('platform::layouts.app');
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->integrationKey = '';
        $this->authScheme = 'oauth2';
        $this->status = 'draft';
        $this->credentialsJson = "{}";
        $this->lastError = null;
        $this->modalShow = true;
    }

    public function closeCreateModal(): void
    {
        $this->modalShow = false;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();

        $conn = IntegrationConnection::query()->with('integration')->findOrFail($id);
        $this->assertCanManage($conn);

        $this->editingId = $conn->id;
        $this->integrationKey = $conn->integration?->key ?? '';
        $this->authScheme = $conn->auth_scheme;
        $this->status = $conn->status;
        $this->credentialsJson = json_encode($conn->credentials ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: "{}";
        $this->lastError = $conn->last_error;

        $this->editModalShow = true;
    }

    public function closeEditModal(): void
    {
        $this->editModalShow = false;
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $integration = Integration::query()->where('key', $this->integrationKey)->first();
        if (!$integration) {
            $this->addError('integrationKey', 'Integration nicht gefunden.');
            return;
        }

        $ownerUserId = auth()->id();

        $credentials = $this->decodeCredentialsJson();
        if ($credentials === null) {
            return; // error already set
        }

        $connection = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $ownerUserId)
            ->first() ?? new IntegrationConnection();

        if ($connection->exists) {
            $this->assertCanManage($connection);
        }

        $connection->integration_id = $integration->id;
        $connection->owner_user_id = $ownerUserId;
        $connection->auth_scheme = $this->authScheme;
        $connection->status = $this->status;
        $connection->credentials = $credentials;
        $connection->last_error = null;
        $connection->save();

        $this->modalShow = false;
        $this->editModalShow = false;

        session()->flash('status', 'Connection gespeichert.');
    }

    public function deleteConnection(int $id): void
    {
        $conn = IntegrationConnection::findOrFail($id);
        $this->assertCanManage($conn);
        $conn->delete();
        session()->flash('status', 'Connection gelöscht.');
    }

    public function startOAuth(int $connectionId): void
    {
        $conn = IntegrationConnection::query()->with('integration')->findOrFail($connectionId);
        $this->assertCanManage($conn);

        $integrationKey = $conn->integration?->key;
        if (!$integrationKey) {
            session()->flash('status', 'Integration-Key fehlt.');
            return;
        }

        $url = route('integrations.oauth2.start', ['integrationKey' => $integrationKey]);
        $this->redirect($url);
    }

    protected function rules(): array
    {
        return [
            'integrationKey' => ['required', 'string'],
            'authScheme' => ['required', Rule::in(['oauth2', 'api_key', 'basic', 'bearer', 'custom'])],
            'status' => ['required', Rule::in(['draft', 'active', 'disabled', 'error'])],
            'credentialsJson' => ['required', 'string'],
        ];
    }

    protected function decodeCredentialsJson(): ?array
    {
        try {
            $decoded = json_decode($this->credentialsJson, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->addError('credentialsJson', 'Ungültiges JSON: ' . $e->getMessage());
            return null;
        }
    }

    public function syncFacebookPages(): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            /** @var User $user */
            $user = auth()->user();
            
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->whereHas('integration', function ($q) {
                    $q->where('key', 'meta');
                })
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$metaConnection) {
                $this->syncError = 'Keine Meta-Connection gefunden. Bitte zuerst mit Meta verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($metaConnection->status !== 'active') {
                $this->syncError = 'Meta-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $service = app(IntegrationsFacebookPageService::class);
            $result = $service->syncFacebookPagesForUser($metaConnection);
            
            $count = count($result);
            $this->syncMessage = "✅ {$count} Facebook Page(s) synchronisiert.";
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('Facebook Pages Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function syncInstagramAccounts(): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            /** @var User $user */
            $user = auth()->user();
            
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->whereHas('integration', function ($q) {
                    $q->where('key', 'meta');
                })
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$metaConnection) {
                $this->syncError = 'Keine Meta-Connection gefunden. Bitte zuerst mit Meta verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($metaConnection->status !== 'active') {
                $this->syncError = 'Meta-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $service = app(IntegrationsInstagramAccountService::class);
            $result = $service->syncInstagramAccountsForUser($metaConnection);
            
            $count = count($result);
            $this->syncMessage = "✅ {$count} Instagram Account(s) synchronisiert.";
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('Instagram Accounts Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function syncWhatsAppAccounts(): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            /** @var User $user */
            $user = auth()->user();
            
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->whereHas('integration', function ($q) {
                    $q->where('key', 'meta');
                })
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$metaConnection) {
                $this->syncError = 'Keine Meta-Connection gefunden. Bitte zuerst mit Meta verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($metaConnection->status !== 'active') {
                $this->syncError = 'Meta-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $service = app(IntegrationsWhatsAppAccountService::class);
            $result = $service->syncWhatsAppAccountsForUser($metaConnection);
            
            $count = count($result);
            $this->syncMessage = "✅ {$count} WhatsApp Account(s) synchronisiert.";
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('WhatsApp Accounts Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function syncAll(): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            /** @var User $user */
            $user = auth()->user();
            
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->whereHas('integration', function ($q) {
                    $q->where('key', 'meta');
                })
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$metaConnection) {
                $this->syncError = 'Keine Meta-Connection gefunden. Bitte zuerst mit Meta verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($metaConnection->status !== 'active') {
                $this->syncError = 'Meta-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $results = [];
            
            // Facebook Pages
            try {
                $fbService = app(IntegrationsFacebookPageService::class);
                $fbResult = $fbService->syncFacebookPagesForUser($metaConnection);
                $results['facebook'] = count($fbResult);
            } catch (\Exception $e) {
                \Log::error('Facebook Pages Sync Error in syncAll', ['error' => $e->getMessage()]);
                $results['facebook'] = 'error';
            }

            // Instagram Accounts
            try {
                $igService = app(IntegrationsInstagramAccountService::class);
                $igResult = $igService->syncInstagramAccountsForUser($metaConnection);
                $results['instagram'] = count($igResult);
            } catch (\Exception $e) {
                \Log::error('Instagram Accounts Sync Error in syncAll', ['error' => $e->getMessage()]);
                $results['instagram'] = 'error';
            }

            // WhatsApp Accounts
            try {
                $waService = app(IntegrationsWhatsAppAccountService::class);
                $waResult = $waService->syncWhatsAppAccountsForUser($metaConnection);
                $results['whatsapp'] = count($waResult);
            } catch (\Exception $e) {
                \Log::error('WhatsApp Accounts Sync Error in syncAll', ['error' => $e->getMessage()]);
                $results['whatsapp'] = 'error';
            }

            $message = "✅ Synchronisation abgeschlossen: ";
            $parts = [];
            if (isset($results['facebook'])) {
                $parts[] = "Facebook: {$results['facebook']}";
            }
            if (isset($results['instagram'])) {
                $parts[] = "Instagram: {$results['instagram']}";
            }
            if (isset($results['whatsapp'])) {
                $parts[] = "WhatsApp: {$results['whatsapp']}";
            }
            $this->syncMessage = $message . implode(', ', $parts);
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('Sync All Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function syncGithubRepositories(): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            /** @var User $user */
            $user = auth()->user();
            
            $githubConnection = IntegrationConnection::query()
                ->with('integration')
                ->whereHas('integration', function ($q) {
                    $q->where('key', 'github');
                })
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$githubConnection) {
                $this->syncError = 'Keine GitHub-Connection gefunden. Bitte zuerst mit GitHub verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($githubConnection->status !== 'active') {
                $this->syncError = 'GitHub-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $service = app(IntegrationsGithubRepositoryService::class);
            $result = $service->syncGithubRepositoriesForUser($githubConnection);
            
            $count = count($result);
            $this->syncMessage = "✅ {$count} GitHub Repository/Repositories synchronisiert.";
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('GitHub Repositories Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    protected function assertCanManage(IntegrationConnection $connection): void
    {
        /** @var User $user */
        $user = auth()->user();
        $access = app(IntegrationAccessService::class);

        if (!$access->canManage($user, $connection)) {
            abort(403, 'Keine Berechtigung (nur Owner darf verwalten).');
        }
    }
}
