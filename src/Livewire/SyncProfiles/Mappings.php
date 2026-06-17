<?php

namespace Platform\Integrations\Livewire\SyncProfiles;

use Livewire\Component;
use Platform\Integrations\Models\IntegrationsDatevAccountMapping;
use Platform\Integrations\Models\IntegrationsLexofficeDatevBridge;
use Platform\Integrations\Services\DatevMappingService;

/**
 * Verwaltung der Konten-Mappings innerhalb einer Bridge.
 */
class Mappings extends Component
{
    public IntegrationsLexofficeDatevBridge $bridge;

    public string $filterType = '';

    public bool $modalShow = false;
    public ?int $editingId = null;

    public string $mappingType = IntegrationsDatevAccountMapping::TYPE_CONTACT;
    public string $sourceKey = '';
    public string $sourceLabel = '';
    public string $datevAccountNumber = '';
    public string $accountKind = IntegrationsDatevAccountMapping::KIND_DEBITOR;
    public string $costCenter1 = '';
    public string $costCenter2 = '';
    public string $taxKey = '';
    public bool $isActive = true;
    public string $notes = '';

    public ?string $flashSuccess = null;
    public ?string $flashError = null;

    public function mount(IntegrationsLexofficeDatevBridge $bridge): void
    {
        $user = auth()->user();
        if ($bridge->owner_user_id !== $user->id) {
            abort(403);
        }

        $this->bridge = $bridge;
    }

    public function render()
    {
        $query = $this->bridge->mappings()->getQuery();

        if ($this->filterType !== '') {
            $query->where('mapping_type', $this->filterType);
        }

        $mappings = $query
            ->orderBy('mapping_type')
            ->orderBy('source_label')
            ->orderBy('source_key')
            ->get();

        return view('integrations::livewire.sync-profiles.mappings', [
            'mappings' => $mappings,
            'typeOptions' => $this->typeOptions(),
            'kindOptions' => $this->kindOptions(),
        ]);
    }

    public function openCreateModal(): void
    {
        $this->reset([
            'editingId', 'sourceKey', 'sourceLabel', 'datevAccountNumber',
            'costCenter1', 'costCenter2', 'taxKey', 'notes',
        ]);
        $this->mappingType = IntegrationsDatevAccountMapping::TYPE_CONTACT;
        $this->accountKind = IntegrationsDatevAccountMapping::KIND_DEBITOR;
        $this->isActive = true;
        $this->modalShow = true;
    }

    public function openEditModal(int $id): void
    {
        $mapping = $this->bridge->mappings()->findOrFail($id);

        $this->editingId = $mapping->id;
        $this->mappingType = $mapping->mapping_type;
        $this->sourceKey = $mapping->source_key;
        $this->sourceLabel = $mapping->source_label ?? '';
        $this->datevAccountNumber = $mapping->datev_account_number;
        $this->accountKind = $mapping->account_kind;
        $this->costCenter1 = $mapping->cost_center_1 ?? '';
        $this->costCenter2 = $mapping->cost_center_2 ?? '';
        $this->taxKey = $mapping->tax_key ?? '';
        $this->isActive = (bool) $mapping->is_active;
        $this->notes = $mapping->notes ?? '';
        $this->modalShow = true;
    }

    public function closeModal(): void
    {
        $this->modalShow = false;
    }

    public function save(DatevMappingService $service): void
    {
        $data = $this->validate([
            'mappingType' => ['required', 'in:contact,posting_category,cost_center'],
            'sourceKey' => ['required', 'string', 'max:191'],
            'sourceLabel' => ['nullable', 'string', 'max:191'],
            'datevAccountNumber' => ['required', 'string', 'max:32'],
            'accountKind' => ['required', 'in:debitor,kreditor,sachkonto,kostenstelle'],
            'costCenter1' => ['nullable', 'string', 'max:32'],
            'costCenter2' => ['nullable', 'string', 'max:32'],
            'taxKey' => ['nullable', 'string', 'max:16'],
            'isActive' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->upsert($this->bridge, $data['mappingType'], $data['sourceKey'], [
                'source_label' => $data['sourceLabel'] ?: null,
                'datev_account_number' => $data['datevAccountNumber'],
                'account_kind' => $data['accountKind'],
                'cost_center_1' => $data['costCenter1'] ?: null,
                'cost_center_2' => $data['costCenter2'] ?: null,
                'tax_key' => $data['taxKey'] ?: null,
                'is_active' => (bool) ($data['isActive'] ?? true),
                'notes' => $data['notes'] ?: null,
            ]);

            $this->flashSuccess = $this->editingId ? 'Mapping aktualisiert.' : 'Mapping angelegt.';
            $this->modalShow = false;
        } catch (\Throwable $e) {
            $this->flashError = 'Speichern fehlgeschlagen: ' . $e->getMessage();
        }
    }

    public function delete(int $id): void
    {
        $this->bridge->mappings()->whereKey($id)->delete();
        $this->flashSuccess = 'Mapping gelöscht.';
    }

    private function typeOptions(): array
    {
        return [
            ['value' => IntegrationsDatevAccountMapping::TYPE_CONTACT, 'label' => 'Kontakt → Personenkonto'],
            ['value' => IntegrationsDatevAccountMapping::TYPE_POSTING_CATEGORY, 'label' => 'Kategorie → Sachkonto'],
            ['value' => IntegrationsDatevAccountMapping::TYPE_COST_CENTER, 'label' => 'Kontext → Kostenstelle'],
        ];
    }

    private function kindOptions(): array
    {
        return [
            ['value' => IntegrationsDatevAccountMapping::KIND_DEBITOR, 'label' => 'Debitor'],
            ['value' => IntegrationsDatevAccountMapping::KIND_KREDITOR, 'label' => 'Kreditor'],
            ['value' => IntegrationsDatevAccountMapping::KIND_SACHKONTO, 'label' => 'Sachkonto'],
            ['value' => IntegrationsDatevAccountMapping::KIND_KOSTENSTELLE, 'label' => 'Kostenstelle'],
        ];
    }
}
