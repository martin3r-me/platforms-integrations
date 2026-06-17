<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Collection;
use Platform\Integrations\Models\IntegrationsDatevAccountMapping;
use Platform\Integrations\Models\IntegrationsLexofficeDatevBridge;

/**
 * Lookups + Upserts gegen die lokale Mapping-Tabelle innerhalb einer Bridge.
 *
 * Eine Bridge fixiert das Pairing (Lexoffice-Connection ↔ DATEV-Connection + Mandant).
 * Der EXTF-Builder ruft hier für jeden Lexoffice-Kontakt bzw. jede Posting-Category
 * das zugehörige DATEV-Konto ab. Fehlt ein Mapping, gibt die Lookup-Methode null
 * zurück — der Caller entscheidet, ob das ein harter Fehler oder eine Warnung ist.
 */
class DatevMappingService
{
    public function findContactMapping(
        IntegrationsLexofficeDatevBridge $bridge,
        string $lexofficeContactId
    ): ?IntegrationsDatevAccountMapping {
        return $this->baseQuery($bridge, IntegrationsDatevAccountMapping::TYPE_CONTACT)
            ->where('source_key', $lexofficeContactId)
            ->first();
    }

    public function findPostingCategoryMapping(
        IntegrationsLexofficeDatevBridge $bridge,
        string $categoryKey
    ): ?IntegrationsDatevAccountMapping {
        return $this->baseQuery($bridge, IntegrationsDatevAccountMapping::TYPE_POSTING_CATEGORY)
            ->where('source_key', $categoryKey)
            ->first();
    }

    public function findCostCenterMapping(
        IntegrationsLexofficeDatevBridge $bridge,
        string $sourceKey
    ): ?IntegrationsDatevAccountMapping {
        return $this->baseQuery($bridge, IntegrationsDatevAccountMapping::TYPE_COST_CENTER)
            ->where('source_key', $sourceKey)
            ->first();
    }

    /**
     * @return Collection<int, IntegrationsDatevAccountMapping>
     */
    public function listForBridge(
        IntegrationsLexofficeDatevBridge $bridge,
        ?string $type = null
    ): Collection {
        return $this->baseQuery($bridge, $type)
            ->orderBy('mapping_type')
            ->orderBy('source_label')
            ->get();
    }

    public function upsert(
        IntegrationsLexofficeDatevBridge $bridge,
        string $mappingType,
        string $sourceKey,
        array $attributes
    ): IntegrationsDatevAccountMapping {
        $mapping = IntegrationsDatevAccountMapping::query()
            ->where('bridge_id', $bridge->id)
            ->where('mapping_type', $mappingType)
            ->where('source_key', $sourceKey)
            ->first();

        $payload = array_merge($attributes, [
            'bridge_id' => $bridge->id,
            'mapping_type' => $mappingType,
            'source_key' => $sourceKey,
        ]);

        if ($mapping) {
            $mapping->fill($payload);
            $mapping->save();

            return $mapping;
        }

        return IntegrationsDatevAccountMapping::create($payload);
    }

    private function baseQuery(IntegrationsLexofficeDatevBridge $bridge, ?string $type = null)
    {
        $query = IntegrationsDatevAccountMapping::query()
            ->where('bridge_id', $bridge->id)
            ->active();

        if ($type !== null) {
            $query->ofType($type);
        }

        return $query;
    }
}
