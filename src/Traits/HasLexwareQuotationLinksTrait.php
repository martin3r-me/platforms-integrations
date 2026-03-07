<?php

namespace Platform\Integrations\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Platform\Integrations\Models\IntegrationsLexwareQuotationLink;

trait HasLexwareQuotationLinksTrait
{
    /**
     * Alle verknüpften Lexware-Angebote.
     */
    public function lexwareQuotationLinks(): MorphMany
    {
        return $this->morphMany(IntegrationsLexwareQuotationLink::class, 'linkable');
    }

    /**
     * Angebote für ein bestimmtes Team.
     */
    public function lexwareQuotationLinksForTeam(int $teamId): MorphMany
    {
        return $this->lexwareQuotationLinks()->forTeam($teamId);
    }

    /**
     * Verknüpfe ein Lexware-Angebot.
     */
    public function attachLexwareQuotation(
        string $quotationExternalId,
        array $data = [],
        ?int $connectionId = null
    ): IntegrationsLexwareQuotationLink {
        // Prüfen ob bereits verlinkt
        $existing = $this->lexwareQuotationLinks()
            ->forQuotation($quotationExternalId)
            ->first();

        if ($existing) {
            // Update mit neuen Daten
            $existing->update(array_filter([
                'quotation_number' => $data['quotation_number'] ?? $existing->quotation_number,
                'voucher_status' => $data['voucher_status'] ?? $existing->voucher_status,
                'voucher_date' => $data['voucher_date'] ?? $existing->voucher_date,
                'expiration_date' => $data['expiration_date'] ?? $existing->expiration_date,
                'total_amount' => $data['total_amount'] ?? $existing->total_amount,
                'currency' => $data['currency'] ?? $existing->currency,
                'contact_name' => $data['contact_name'] ?? $existing->contact_name,
                'metadata' => $data['metadata'] ?? $existing->metadata,
            ]));

            return $existing->fresh();
        }

        return $this->lexwareQuotationLinks()->create([
            'quotation_external_id' => $quotationExternalId,
            'quotation_number' => $data['quotation_number'] ?? null,
            'voucher_status' => $data['voucher_status'] ?? null,
            'voucher_date' => $data['voucher_date'] ?? null,
            'expiration_date' => $data['expiration_date'] ?? null,
            'total_amount' => $data['total_amount'] ?? null,
            'currency' => $data['currency'] ?? 'EUR',
            'contact_name' => $data['contact_name'] ?? null,
            'integration_connection_id' => $connectionId,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Entferne Verknüpfung zu einem Lexware-Angebot.
     */
    public function detachLexwareQuotation(string $quotationExternalId): bool
    {
        return $this->lexwareQuotationLinks()
            ->forQuotation($quotationExternalId)
            ->delete() > 0;
    }

    /**
     * Prüfe ob ein bestimmtes Angebot verlinkt ist.
     */
    public function hasLexwareQuotation(string $quotationExternalId): bool
    {
        return $this->lexwareQuotationLinks()
            ->forQuotation($quotationExternalId)
            ->exists();
    }

    /**
     * Alle verlinkten Angebote als Collection.
     */
    public function lexwareQuotations(): Collection
    {
        return $this->lexwareQuotationLinks()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Anzahl verknüpfter Angebote.
     */
    public function lexwareQuotationsCount(): int
    {
        return $this->lexwareQuotationLinks()->count();
    }

    /**
     * Aktualisiere den Status eines verknüpften Angebots.
     */
    public function updateLexwareQuotationStatus(string $quotationExternalId, string $status): bool
    {
        return $this->lexwareQuotationLinks()
            ->forQuotation($quotationExternalId)
            ->update(['voucher_status' => $status]) > 0;
    }
}
