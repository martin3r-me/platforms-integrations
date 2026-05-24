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
use Platform\Integrations\Models\IntegrationsWhatsAppTemplate;
use Platform\Integrations\Models\IntegrationsGithubRepository;
use Platform\Integrations\Models\IntegrationsLexwareContact;
use Platform\Integrations\Services\IntegrationAccessService;
use Platform\Integrations\Services\IntegrationConnectionShareService;
use Platform\Integrations\Services\IntegrationsFacebookPageService;
use Platform\Integrations\Services\IntegrationsInstagramAccountService;
use Platform\Integrations\Services\IntegrationsWhatsAppAccountService;
use Platform\Integrations\Services\IntegrationsGithubRepositoryService;
use Platform\Integrations\Events\WhatsAppAccountsSynced;
use Platform\Integrations\Services\LexwareIntegrationService;
use Platform\Integrations\Services\IntegrationsLexwareContactService;
use Platform\Integrations\Services\SipgateIntegrationService;
use Platform\Integrations\Services\DataForSeoIntegrationService;
use Platform\Integrations\Services\MossIntegrationService;
use Platform\Integrations\Services\HubspotIntegrationService;
use Platform\Integrations\Services\HubspotCrmSyncService;
use Platform\Integrations\Services\BuchhaltungsbutlerIntegrationService;
use Platform\Integrations\Models\IntegrationsHubspotContact;
use Platform\Integrations\Models\IntegrationsHubspotCompany;
use Platform\Integrations\Models\IntegrationsHubspotDeal;
use Platform\Integrations\Models\IntegrationsHubspotEngagement;

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

    // Lexware Modal
    public bool $lexwareModalShow = false;
    public string $lexwareApiToken = '';

    // DataForSEO Modal
    public bool $dataforseoModalShow = false;
    public string $dataforseoLogin = '';
    public string $dataforseoPassword = '';

    // HubSpot Modal
    public bool $hubspotModalShow = false;
    public string $hubspotApiToken = '';
    public ?int $hubspotEditingConnectionId = null;

    // Moss Modal
    public bool $mossModalShow = false;
    public string $mossClientId = '';
    public string $mossClientSecret = '';
    public ?int $mossEditingConnectionId = null;

    // BuchhaltungsButler Modal
    public bool $buchhaltungsbutlerModalShow = false;
    public string $buchhaltungsbutlerApiClient = '';
    public string $buchhaltungsbutlerApiSecret = '';
    public string $buchhaltungsbutlerApiKey = '';
    public ?int $buchhaltungsbutlerEditingConnectionId = null;

    // Share Modal
    public bool $shareModalShow = false;
    public ?int $shareConnectionId = null;
    public ?string $shareConnectionName = null;
    public bool $shareConnectionHasResources = false;
    public string $shareType = 'team';       // 'team' | 'user'
    public ?int $shareTeamId = null;
    public ?int $shareUserId = null;
    public ?int $shareResourceId = null;
    public ?string $shareResourceType = null;
    public array $sharesList = [];
    public array $shareableResources = [];

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

        // Alle Connections pro Typ laden (Collections statt single)
        $metaConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'meta'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $githubConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'github'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $lexwareConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'lexoffice'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $sipgateConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'sipgate'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $dataforseoConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'dataforseo'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $hubspotConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'hubspot'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $mossConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'moss'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $buchhaltungsbutlerConnections = IntegrationConnection::query()
            ->with('integration')
            ->whereHas('integration', fn ($q) => $q->where('key', 'buchhaltungsbutler'))
            ->where('owner_user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        // Connections, die mir von anderen Usern freigegeben wurden
        $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
        $sharedWithMe = IntegrationConnection::query()
            ->with(['integration', 'ownerUser'])
            ->where('owner_user_id', '!=', $user->id)
            ->where('status', 'active')
            ->whereHas('shares', function ($query) use ($user, $userTeamIds) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })->where(function ($q) use ($userTeamIds) {
                    $q->whereNull('team_id');
                    if (!empty($userTeamIds)) {
                        $q->orWhereIn('team_id', $userTeamIds);
                    }
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        // Teams des Users für Share-Modal
        $userTeams = $user->teams()->orderBy('name')->get();

        // User für Share-Modal (alle User außer sich selbst)
        $teamUsers = User::query()
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        return view('integrations::livewire.connections.index', [
            'connections' => $connections,
            'integrations' => $integrations,
            'metaConnections' => $metaConnections,
            'githubConnections' => $githubConnections,
            'lexwareConnections' => $lexwareConnections,
            'sipgateConnections' => $sipgateConnections,
            'dataforseoConnections' => $dataforseoConnections,
            'hubspotConnections' => $hubspotConnections,
            'mossConnections' => $mossConnections,
            'buchhaltungsbutlerConnections' => $buchhaltungsbutlerConnections,
            'sharedWithMe' => $sharedWithMe,
            'userTeams' => $userTeams,
            'teamUsers' => $teamUsers,
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

        if ($this->editingId) {
            // Bestehende Connection bearbeiten
            $connection = IntegrationConnection::withTrashed()->findOrFail($this->editingId);
            if ($connection->trashed()) {
                $connection->restore();
            }
            $this->assertCanManage($connection);
        } else {
            // Neue Connection erstellen
            $isFirst = !IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $ownerUserId)
                ->exists();

            $connection = new IntegrationConnection([
                'name' => IntegrationConnection::generateName($integration->id, $ownerUserId, $integration->name),
                'is_default' => $isFirst,
            ]);
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

    public function syncFacebookPages(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
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
            $this->syncMessage = "{$count} Facebook Page(s) synchronisiert.";
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

    public function syncInstagramAccounts(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
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
            $this->syncMessage = "{$count} Instagram Account(s) synchronisiert.";
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

    public function syncWhatsAppAccounts(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
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
            $this->syncMessage = "{$count} WhatsApp Account(s) synchronisiert.";
            session()->flash('status', $this->syncMessage);

            // Event feuern für Comms Channel Sync
            WhatsAppAccountsSynced::dispatch($metaConnection, collect($result));
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

    public function syncWhatsAppTemplates(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
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
            $result = $service->syncAllWhatsAppTemplates($metaConnection);

            $count = count($result);
            $this->syncMessage = "{$count} WhatsApp Template(s) synchronisiert.";
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('WhatsApp Templates Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function syncAll(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $metaConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
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

                // Event feuern für Comms Channel Sync
                WhatsAppAccountsSynced::dispatch($metaConnection, collect($waResult));
            } catch (\Exception $e) {
                \Log::error('WhatsApp Accounts Sync Error in syncAll', ['error' => $e->getMessage()]);
                $results['whatsapp'] = 'error';
            }

            // WhatsApp Templates
            try {
                $waService = $waService ?? app(IntegrationsWhatsAppAccountService::class);
                $templateResult = $waService->syncAllWhatsAppTemplates($metaConnection);
                $results['whatsapp_templates'] = count($templateResult);
            } catch (\Exception $e) {
                \Log::error('WhatsApp Templates Sync Error in syncAll', ['error' => $e->getMessage()]);
                $results['whatsapp_templates'] = 'error';
            }

            $message = "Synchronisation abgeschlossen: ";
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
            if (isset($results['whatsapp_templates'])) {
                $parts[] = "WA Templates: {$results['whatsapp_templates']}";
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

    public function syncGithubRepositories(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $githubConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
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
            $this->syncMessage = "{$count} GitHub Repository/Repositories synchronisiert.";
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

    // ==================== LEXWARE METHODS ====================

    public function openLexwareModal(): void
    {
        $this->resetValidation();
        $this->lexwareApiToken = '';
        $this->lexwareModalShow = true;
    }

    public function closeLexwareModal(): void
    {
        $this->lexwareModalShow = false;
        $this->lexwareApiToken = '';
    }

    public function saveLexwareConnection(): void
    {
        $this->validate([
            'lexwareApiToken' => ['required', 'string', 'min:10'],
        ], [
            'lexwareApiToken.required' => 'Bitte gib deinen Lexware API-Token ein.',
            'lexwareApiToken.min' => 'Der API-Token muss mindestens 10 Zeichen lang sein.',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();

            $service = app(LexwareIntegrationService::class);
            $connection = $service->createOrUpdateConnectionForUser($user, $this->lexwareApiToken, $this->editingId);

            // Verbindung testen
            $testResult = $service->testConnection($connection);

            if ($testResult['success']) {
                $this->lexwareModalShow = false;
                $this->lexwareApiToken = '';
                $this->editingId = null;
                session()->flash('status', 'Lexware-Verbindung erfolgreich hergestellt.');
            } else {
                $this->addError('lexwareApiToken', $testResult['message']);
            }
        } catch (\Exception $e) {
            $this->addError('lexwareApiToken', 'Fehler: ' . $e->getMessage());
            \Log::error('Lexware connection error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncLexwareContacts(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $lexwareConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$lexwareConnection) {
                $this->syncError = 'Keine Lexware-Connection gefunden. Bitte zuerst mit Lexware verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($lexwareConnection->status !== 'active') {
                $this->syncError = 'Lexware-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $service = app(IntegrationsLexwareContactService::class);
            $result = $service->syncContactsForUser($lexwareConnection);

            $count = count($result);
            $this->syncMessage = "{$count} Lexware Kontakt(e) synchronisiert.";
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('Lexware Contacts Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function testLexwareConnection(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;

        try {
            $lexwareConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$lexwareConnection) {
                $this->syncError = 'Keine Lexware-Connection gefunden.';
                return;
            }

            $service = app(LexwareIntegrationService::class);
            $result = $service->testConnection($lexwareConnection);

            if ($result['success']) {
                $this->syncMessage = 'Lexware-Verbindung erfolgreich getestet.';
                session()->flash('status', $this->syncMessage);
            } else {
                $this->syncError = $result['message'];
            }
        } catch (\Exception $e) {
            $this->syncError = 'Fehler: ' . $e->getMessage();
        }
    }

    // ==================== SIPGATE METHODS ====================

    public function testSipgateConnection(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;

        try {
            $sipgateConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$sipgateConnection) {
                $this->syncError = 'Keine Sipgate-Connection gefunden.';
                return;
            }

            $service = app(SipgateIntegrationService::class);
            $result = $service->testConnection($sipgateConnection);

            if ($result['success']) {
                $this->syncMessage = 'Sipgate-Verbindung erfolgreich getestet.';
                session()->flash('status', $this->syncMessage);
            } else {
                $this->syncError = $result['message'];
            }
        } catch (\Exception $e) {
            $this->syncError = 'Fehler: ' . $e->getMessage();
        }
    }

    // ==================== DATAFORSEO METHODS ====================

    public function openDataforseoModal(): void
    {
        $this->resetValidation();
        $this->dataforseoLogin = '';
        $this->dataforseoPassword = '';
        $this->dataforseoModalShow = true;
    }

    public function closeDataforseoModal(): void
    {
        $this->dataforseoModalShow = false;
        $this->dataforseoLogin = '';
        $this->dataforseoPassword = '';
    }

    public function saveDataforseoConnection(): void
    {
        $this->validate([
            'dataforseoLogin' => ['required', 'string', 'min:3'],
            'dataforseoPassword' => ['required', 'string', 'min:3'],
        ], [
            'dataforseoLogin.required' => 'Bitte gib deinen DataForSEO Login ein.',
            'dataforseoLogin.min' => 'Der Login muss mindestens 3 Zeichen lang sein.',
            'dataforseoPassword.required' => 'Bitte gib dein DataForSEO Password ein.',
            'dataforseoPassword.min' => 'Das Password muss mindestens 3 Zeichen lang sein.',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();

            $service = app(DataForSeoIntegrationService::class);
            $connection = $service->createOrUpdateConnectionForUser($user, $this->dataforseoLogin, $this->dataforseoPassword, $this->editingId);

            // Verbindung testen
            $testResult = $service->testConnection($connection);

            if ($testResult['success']) {
                $this->dataforseoModalShow = false;
                $this->dataforseoLogin = '';
                $this->dataforseoPassword = '';
                $this->editingId = null;
                session()->flash('status', 'DataForSEO-Verbindung erfolgreich hergestellt.');
            } else {
                $this->addError('dataforseoLogin', $testResult['message']);
            }
        } catch (\Exception $e) {
            $this->addError('dataforseoLogin', 'Fehler: ' . $e->getMessage());
            \Log::error('DataForSEO connection error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function testDataforseoConnection(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;

        try {
            $dataforseoConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$dataforseoConnection) {
                $this->syncError = 'Keine DataForSEO-Connection gefunden.';
                return;
            }

            $service = app(DataForSeoIntegrationService::class);
            $result = $service->testConnection($dataforseoConnection);

            if ($result['success']) {
                $this->syncMessage = 'DataForSEO-Verbindung erfolgreich getestet.';
                session()->flash('status', $this->syncMessage);
            } else {
                $this->syncError = $result['message'];
            }
        } catch (\Exception $e) {
            $this->syncError = 'Fehler: ' . $e->getMessage();
        }
    }

    // ==================== HUBSPOT METHODS ====================

    public function openHubspotModal(?int $connectionId = null): void
    {
        $this->resetValidation();
        $this->hubspotEditingConnectionId = $connectionId;
        $this->hubspotApiToken = '';
        $this->hubspotModalShow = true;
    }

    public function closeHubspotModal(): void
    {
        $this->hubspotModalShow = false;
        $this->hubspotApiToken = '';
        $this->hubspotEditingConnectionId = null;
    }

    public function saveHubspotConnection(): void
    {
        $this->validate([
            'hubspotApiToken' => ['required', 'string', 'min:10'],
        ], [
            'hubspotApiToken.required' => 'Bitte gib dein HubSpot Private App Access Token ein.',
            'hubspotApiToken.min' => 'Der Token muss mindestens 10 Zeichen lang sein.',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();

            $service = app(HubspotIntegrationService::class);
            $connection = $service->createOrUpdateConnectionForUser(
                $user,
                $this->hubspotApiToken,
                $this->hubspotEditingConnectionId
            );

            $testResult = $service->testConnection($connection);

            if ($testResult['success']) {
                $this->hubspotModalShow = false;
                $this->hubspotApiToken = '';
                $this->hubspotEditingConnectionId = null;
                session()->flash('status', 'HubSpot-Verbindung erfolgreich hergestellt.');
            } else {
                $this->addError('hubspotApiToken', $testResult['message']);
            }
        } catch (\Exception $e) {
            $this->addError('hubspotApiToken', 'Fehler: ' . $e->getMessage());
            \Log::error('HubSpot connection error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncHubspotCrm(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;
        $this->isSyncing = true;

        try {
            $hubspotConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$hubspotConnection) {
                $this->syncError = 'Keine HubSpot-Connection gefunden. Bitte zuerst mit HubSpot verbinden.';
                $this->isSyncing = false;
                return;
            }

            if ($hubspotConnection->status !== 'active') {
                $this->syncError = 'HubSpot-Connection ist nicht aktiv.';
                $this->isSyncing = false;
                return;
            }

            $service = app(HubspotCrmSyncService::class);
            $results = $service->syncAllForConnection($hubspotConnection);

            $parts = [];
            $parts[] = ($results['contacts'] ?? 0) . ' Contacts';
            $parts[] = ($results['companies'] ?? 0) . ' Companies';
            $parts[] = ($results['deals'] ?? 0) . ' Deals';
            $parts[] = ($results['engagements'] ?? 0) . ' Engagements';

            $this->syncMessage = implode(', ', $parts) . ' synchronisiert.';
            session()->flash('status', $this->syncMessage);
        } catch (\Exception $e) {
            $this->syncError = 'Fehler beim Synchronisieren: ' . $e->getMessage();
            \Log::error('HubSpot CRM Sync Error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->isSyncing = false;
        }
    }

    public function testHubspotConnection(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;

        try {
            $hubspotConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$hubspotConnection) {
                $this->syncError = 'Keine HubSpot-Connection gefunden.';
                return;
            }

            $service = app(HubspotIntegrationService::class);
            $result = $service->testConnection($hubspotConnection);

            if ($result['success']) {
                $this->syncMessage = 'HubSpot-Verbindung erfolgreich getestet.';
                session()->flash('status', $this->syncMessage);
            } else {
                $this->syncError = $result['message'];
            }
        } catch (\Exception $e) {
            $this->syncError = 'Fehler: ' . $e->getMessage();
        }
    }

    // ==================== MOSS METHODS ====================

    public function openMossModal(?int $connectionId = null): void
    {
        $this->resetValidation();
        $this->mossEditingConnectionId = $connectionId;
        $this->mossClientId = '';
        $this->mossClientSecret = '';
        $this->mossModalShow = true;
    }

    public function openMossModalForEdit(int $connectionId): void
    {
        $this->openMossModal($connectionId);
    }

    public function closeMossModal(): void
    {
        $this->mossModalShow = false;
        $this->mossClientId = '';
        $this->mossClientSecret = '';
        $this->mossEditingConnectionId = null;
    }

    public function saveMossConnection(): void
    {
        $this->validate([
            'mossClientId' => ['required', 'string', 'regex:/^kid_/'],
            'mossClientSecret' => ['required', 'string', 'regex:/^sk_/'],
        ], [
            'mossClientId.required' => 'Bitte gib deine Moss Client ID ein.',
            'mossClientId.regex' => 'Die Client ID muss mit "kid_" beginnen.',
            'mossClientSecret.required' => 'Bitte gib dein Moss Client Secret ein.',
            'mossClientSecret.regex' => 'Das Client Secret muss mit "sk_" beginnen.',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();

            $service = app(MossIntegrationService::class);
            $connection = $service->createOrUpdateConnectionForUser(
                $user,
                $this->mossClientId,
                $this->mossClientSecret,
                $this->mossEditingConnectionId
            );

            $testResult = $service->testConnection($connection);

            if ($testResult['success']) {
                $this->mossModalShow = false;
                $this->mossClientId = '';
                $this->mossClientSecret = '';
                $this->mossEditingConnectionId = null;
                session()->flash('status', 'Moss-Verbindung erfolgreich hergestellt.');
            } else {
                $this->addError('mossClientId', $testResult['message']);
            }
        } catch (\Exception $e) {
            $this->addError('mossClientId', 'Fehler: ' . $e->getMessage());
            \Log::error('Moss connection error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function testMossConnection(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;

        try {
            $mossConnection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$mossConnection) {
                $this->syncError = 'Keine Moss-Connection gefunden.';
                return;
            }

            $service = app(MossIntegrationService::class);
            $result = $service->testConnection($mossConnection);

            if ($result['success']) {
                $this->syncMessage = 'Moss-Verbindung erfolgreich getestet.';
                session()->flash('status', $this->syncMessage);
            } else {
                $this->syncError = $result['message'];
            }
        } catch (\Exception $e) {
            $this->syncError = 'Fehler: ' . $e->getMessage();
        }
    }

    // ==================== BUCHHALTUNGSBUTLER METHODS ====================

    public function openBuchhaltungsbutlerModal(?int $connectionId = null): void
    {
        $this->resetValidation();
        $this->buchhaltungsbutlerEditingConnectionId = $connectionId;
        $this->buchhaltungsbutlerApiClient = '';
        $this->buchhaltungsbutlerApiSecret = '';
        $this->buchhaltungsbutlerApiKey = '';
        $this->buchhaltungsbutlerModalShow = true;
    }

    public function closeBuchhaltungsbutlerModal(): void
    {
        $this->buchhaltungsbutlerModalShow = false;
        $this->buchhaltungsbutlerApiClient = '';
        $this->buchhaltungsbutlerApiSecret = '';
        $this->buchhaltungsbutlerApiKey = '';
        $this->buchhaltungsbutlerEditingConnectionId = null;
    }

    public function saveBuchhaltungsbutlerConnection(): void
    {
        $this->validate([
            'buchhaltungsbutlerApiClient' => ['required', 'string', 'min:3'],
            'buchhaltungsbutlerApiSecret' => ['required', 'string', 'min:3'],
            'buchhaltungsbutlerApiKey'    => ['required', 'string', 'min:3'],
        ], [
            'buchhaltungsbutlerApiClient.required' => 'Bitte gib deinen API-Client ein.',
            'buchhaltungsbutlerApiSecret.required' => 'Bitte gib dein API-Secret ein.',
            'buchhaltungsbutlerApiKey.required'    => 'Bitte gib deinen kundenspezifischen API-Key ein.',
        ]);

        try {
            /** @var User $user */
            $user = auth()->user();

            $service    = app(BuchhaltungsbutlerIntegrationService::class);
            $connection = $service->createOrUpdateConnectionForUser(
                $user,
                $this->buchhaltungsbutlerApiClient,
                $this->buchhaltungsbutlerApiSecret,
                $this->buchhaltungsbutlerApiKey,
                $this->buchhaltungsbutlerEditingConnectionId
            );

            $testResult = $service->testConnection($connection);

            if ($testResult['success']) {
                $this->buchhaltungsbutlerModalShow = false;
                $this->buchhaltungsbutlerApiClient = '';
                $this->buchhaltungsbutlerApiSecret = '';
                $this->buchhaltungsbutlerApiKey = '';
                $this->buchhaltungsbutlerEditingConnectionId = null;
                session()->flash('status', 'BuchhaltungsButler-Verbindung erfolgreich hergestellt.');
            } else {
                $this->addError('buchhaltungsbutlerApiKey', $testResult['message']);
            }
        } catch (\Exception $e) {
            $this->addError('buchhaltungsbutlerApiKey', 'Fehler: ' . $e->getMessage());
            \Log::error('BuchhaltungsButler connection error', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function testBuchhaltungsbutlerConnection(int $connectionId): void
    {
        $this->syncError = null;
        $this->syncMessage = null;

        try {
            $connection = IntegrationConnection::query()
                ->with('integration')
                ->where('id', $connectionId)
                ->where('owner_user_id', auth()->id())
                ->first();

            if (!$connection) {
                $this->syncError = 'Keine BuchhaltungsButler-Connection gefunden.';
                return;
            }

            $result = app(BuchhaltungsbutlerIntegrationService::class)->testConnection($connection);

            if ($result['success']) {
                $this->syncMessage = 'BuchhaltungsButler-Verbindung erfolgreich getestet.';
                session()->flash('status', $this->syncMessage);
            } else {
                $this->syncError = $result['message'];
            }
        } catch (\Exception $e) {
            $this->syncError = 'Fehler: ' . $e->getMessage();
        }
    }

    public function setDefaultConnection(int $id): void
    {
        $connection = IntegrationConnection::findOrFail($id);
        $this->assertCanManage($connection);
        $connection->makeDefault();
        session()->flash('status', "'{$connection->name}' ist jetzt die Standard-Verbindung.");
    }

    public function renameConnection(int $id, string $name): void
    {
        $connection = IntegrationConnection::findOrFail($id);
        $this->assertCanManage($connection);

        $name = trim($name);
        if (empty($name)) {
            $this->addError('connectionName', 'Name darf nicht leer sein.');
            return;
        }

        $connection->name = $name;
        $connection->save();
        session()->flash('status', 'Connection umbenannt.');
    }

    public function openLexwareModalForEdit(int $connectionId): void
    {
        $this->resetValidation();
        $this->editingId = $connectionId;
        $this->lexwareApiToken = '';
        $this->lexwareModalShow = true;
    }

    public function openDataforseoModalForEdit(int $connectionId): void
    {
        $this->resetValidation();
        $this->editingId = $connectionId;
        $this->dataforseoLogin = '';
        $this->dataforseoPassword = '';
        $this->dataforseoModalShow = true;
    }

    // ==================== SHARE MODAL METHODS ====================

    public function openShareModal(int $connectionId): void
    {
        $connection = IntegrationConnection::query()->with('integration')->findOrFail($connectionId);
        $this->assertCanManage($connection);

        $this->shareConnectionId = $connection->id;
        $this->shareConnectionName = $connection->name ?? $connection->integration?->name ?? 'Connection';
        $this->shareConnectionHasResources = $connection->integration?->has_resources ?? false;
        $this->shareType = 'team';
        $this->shareTeamId = null;
        $this->shareUserId = null;
        $this->shareResourceId = null;
        $this->shareResourceType = null;

        // Bestehende Shares laden
        /** @var User $user */
        $user = auth()->user();
        $shareService = app(IntegrationConnectionShareService::class);
        $this->sharesList = $shareService->listShares($user, $connection)->toArray();

        // Ressourcen laden, wenn Integration has_resources=true
        $this->shareableResources = [];
        if ($this->shareConnectionHasResources) {
            $key = $connection->integration?->key;
            if ($key === 'meta') {
                $igAccounts = $connection->metaInstagramAccounts()->get()->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => 'instagram_account',
                    'label' => 'Instagram: ' . ($a->username ?? $a->name ?? "#{$a->id}"),
                ])->toArray();
                $fbPages = $connection->metaFacebookPages()->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'type' => 'facebook_page',
                    'label' => 'Facebook Page: ' . ($p->name ?? "#{$p->id}"),
                ])->toArray();
                $this->shareableResources = array_merge($igAccounts, $fbPages);
            } elseif ($key === 'github') {
                $this->shareableResources = $connection->githubRepos()->get()->map(fn ($r) => [
                    'id' => $r->id,
                    'type' => 'github_repo',
                    'label' => 'Repo: ' . ($r->full_name ?? $r->name ?? "#{$r->id}"),
                ])->toArray();
            }
        }

        $this->shareModalShow = true;
    }

    public function closeShareModal(): void
    {
        $this->shareModalShow = false;
        $this->shareConnectionId = null;
        $this->shareConnectionName = null;
        $this->shareConnectionHasResources = false;
        $this->shareType = 'team';
        $this->shareTeamId = null;
        $this->shareUserId = null;
        $this->shareResourceId = null;
        $this->shareResourceType = null;
        $this->sharesList = [];
        $this->shareableResources = [];
    }

    public function addShare(): void
    {
        $connection = IntegrationConnection::query()->with('integration')->findOrFail($this->shareConnectionId);
        $this->assertCanManage($connection);

        /** @var User $user */
        $user = auth()->user();
        $shareService = app(IntegrationConnectionShareService::class);

        $teamId = $this->shareType === 'team' ? $this->shareTeamId : null;
        $userId = $this->shareType === 'user' ? $this->shareUserId : null;

        // Ressourcen-Scope
        $resourceId = null;
        $resourceType = null;
        if ($this->shareConnectionHasResources && $this->shareResourceId) {
            $resource = collect($this->shareableResources)->firstWhere('id', $this->shareResourceId);
            if ($resource) {
                $resourceId = $resource['id'];
                $resourceType = $resource['type'];
            }
        }

        try {
            $shareService->createShare($user, $connection, $teamId, $userId, $resourceId, $resourceType);
            $this->sharesList = $shareService->listShares($user, $connection)->toArray();
            $this->shareTeamId = null;
            $this->shareUserId = null;
            $this->shareResourceId = null;
            $this->shareResourceType = null;
        } catch (\InvalidArgumentException $e) {
            $this->addError('shareType', $e->getMessage());
        }
    }

    public function removeShare(int $shareId): void
    {
        $connection = IntegrationConnection::query()->with('integration')->findOrFail($this->shareConnectionId);
        $this->assertCanManage($connection);

        /** @var User $user */
        $user = auth()->user();
        $shareService = app(IntegrationConnectionShareService::class);

        $shareService->deleteShare($user, $connection, $shareId);
        $this->sharesList = $shareService->listShares($user, $connection)->toArray();
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
