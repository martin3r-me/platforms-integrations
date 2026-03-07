<?php

namespace Platform\Integrations\Contracts;

interface LexwareQuotationLinkableInterface
{
    /**
     * Eindeutige ID des Objekts
     */
    public function getLexwareQuotationLinkableId(): int;

    /**
     * Typ des Objekts (z.B. 'Platform\Sales\Models\SalesDeal')
     */
    public function getLexwareQuotationLinkableType(): string;

    /**
     * Team-ID für den Kontext
     */
    public function getTeamId(): int;
}
