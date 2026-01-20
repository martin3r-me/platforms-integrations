<?php

namespace Platform\Integrations\Contracts;

interface SocialMediaAccountLinkableInterface
{
    /**
     * Eindeutige ID des Objekts
     */
    public function getSocialMediaAccountLinkableId(): int;

    /**
     * Typ des Objekts (z.B. 'BrandsBrand')
     */
    public function getSocialMediaAccountLinkableType(): string;

    /**
     * Team-ID für den Kontext
     */
    public function getTeamId(): int;
}
