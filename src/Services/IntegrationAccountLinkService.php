<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Collection;
use Platform\Integrations\Contracts\SocialMediaAccountLinkableInterface;
use Platform\Integrations\Models\IntegrationAccountLink;
use Platform\Integrations\Models\IntegrationsFacebookPage;
use Platform\Integrations\Models\IntegrationsInstagramAccount;

class IntegrationAccountLinkService
{
    /**
     * Verknüpfe eine Facebook Page mit einem linkable Objekt
     * 
     * @param IntegrationsFacebookPage $facebookPage
     * @param SocialMediaAccountLinkableInterface $linkable
     * @return bool True wenn erfolgreich verknüpft, false wenn bereits verknüpft
     */
    public function linkFacebookPage(IntegrationsFacebookPage $facebookPage, SocialMediaAccountLinkableInterface $linkable): bool
    {
        // Prüfe ob dieser Account bereits verknüpft ist (jeder Account nur einmal)
        $existingLink = IntegrationAccountLink::where('account_type', 'facebook_page')
            ->where('account_id', $facebookPage->id)
            ->first();

        if ($existingLink) {
            // Account ist bereits verknüpft - aktualisiere die Verknüpfung
            $existingLink->update([
                'linkable_type' => $linkable->getSocialMediaAccountLinkableType(),
                'linkable_id' => $linkable->getSocialMediaAccountLinkableId(),
                'team_id' => $linkable->getTeamId(),
                'created_by_user_id' => auth()->id(),
            ]);
            return true;
        }

        // Neue Verknüpfung erstellen
        IntegrationAccountLink::create([
            'linkable_type' => $linkable->getSocialMediaAccountLinkableType(),
            'linkable_id' => $linkable->getSocialMediaAccountLinkableId(),
            'account_type' => 'facebook_page',
            'account_id' => $facebookPage->id,
            'team_id' => $linkable->getTeamId(),
            'created_by_user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Verknüpfe einen Instagram Account mit einem linkable Objekt
     * 
     * @param IntegrationsInstagramAccount $instagramAccount
     * @param SocialMediaAccountLinkableInterface $linkable
     * @return bool True wenn erfolgreich verknüpft, false wenn bereits verknüpft
     */
    public function linkInstagramAccount(IntegrationsInstagramAccount $instagramAccount, SocialMediaAccountLinkableInterface $linkable): bool
    {
        // Prüfe ob dieser Account bereits verknüpft ist (jeder Account nur einmal)
        $existingLink = IntegrationAccountLink::where('account_type', 'instagram_account')
            ->where('account_id', $instagramAccount->id)
            ->first();

        if ($existingLink) {
            // Account ist bereits verknüpft - aktualisiere die Verknüpfung
            $existingLink->update([
                'linkable_type' => $linkable->getSocialMediaAccountLinkableType(),
                'linkable_id' => $linkable->getSocialMediaAccountLinkableId(),
                'team_id' => $linkable->getTeamId(),
                'created_by_user_id' => auth()->id(),
            ]);
            return true;
        }

        // Neue Verknüpfung erstellen
        IntegrationAccountLink::create([
            'linkable_type' => $linkable->getSocialMediaAccountLinkableType(),
            'linkable_id' => $linkable->getSocialMediaAccountLinkableId(),
            'account_type' => 'instagram_account',
            'account_id' => $instagramAccount->id,
            'team_id' => $linkable->getTeamId(),
            'created_by_user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Entferne Verknüpfung einer Facebook Page
     */
    public function unlinkFacebookPage(IntegrationsFacebookPage $facebookPage, SocialMediaAccountLinkableInterface $linkable): bool
    {
        $link = IntegrationAccountLink::where('account_type', 'facebook_page')
            ->where('account_id', $facebookPage->id)
            ->where('linkable_type', $linkable->getSocialMediaAccountLinkableType())
            ->where('linkable_id', $linkable->getSocialMediaAccountLinkableId())
            ->first();

        if ($link) {
            return $link->delete();
        }

        return false;
    }

    /**
     * Entferne Verknüpfung eines Instagram Accounts
     */
    public function unlinkInstagramAccount(IntegrationsInstagramAccount $instagramAccount, SocialMediaAccountLinkableInterface $linkable): bool
    {
        $link = IntegrationAccountLink::where('account_type', 'instagram_account')
            ->where('account_id', $instagramAccount->id)
            ->where('linkable_type', $linkable->getSocialMediaAccountLinkableType())
            ->where('linkable_id', $linkable->getSocialMediaAccountLinkableId())
            ->first();

        if ($link) {
            return $link->delete();
        }

        return false;
    }

    /**
     * Hole alle verknüpften Facebook Pages für ein linkable Objekt
     */
    public function getLinkedFacebookPages(SocialMediaAccountLinkableInterface $linkable): Collection
    {
        $links = IntegrationAccountLink::where('linkable_type', $linkable->getSocialMediaAccountLinkableType())
            ->where('linkable_id', $linkable->getSocialMediaAccountLinkableId())
            ->where('account_type', 'facebook_page')
            ->get();

        return $links->map(function ($link) {
            return $link->getAccount();
        })->filter();
    }

    /**
     * Hole alle verknüpften Instagram Accounts für ein linkable Objekt
     */
    public function getLinkedInstagramAccounts(SocialMediaAccountLinkableInterface $linkable): Collection
    {
        $links = IntegrationAccountLink::where('linkable_type', $linkable->getSocialMediaAccountLinkableType())
            ->where('linkable_id', $linkable->getSocialMediaAccountLinkableId())
            ->where('account_type', 'instagram_account')
            ->get();

        return $links->map(function ($link) {
            return $link->getAccount();
        })->filter();
    }

    /**
     * Prüfe ob eine Facebook Page bereits verknüpft ist
     */
    public function isFacebookPageLinked(IntegrationsFacebookPage $facebookPage): bool
    {
        return IntegrationAccountLink::where('account_type', 'facebook_page')
            ->where('account_id', $facebookPage->id)
            ->exists();
    }

    /**
     * Prüfe ob ein Instagram Account bereits verknüpft ist
     */
    public function isInstagramAccountLinked(IntegrationsInstagramAccount $instagramAccount): bool
    {
        return IntegrationAccountLink::where('account_type', 'instagram_account')
            ->where('account_id', $instagramAccount->id)
            ->exists();
    }

    /**
     * Hole das linkable Objekt, mit dem ein Account verknüpft ist
     */
    public function getLinkedObjectForAccount(string $accountType, int $accountId)
    {
        $link = IntegrationAccountLink::where('account_type', $accountType)
            ->where('account_id', $accountId)
            ->first();

        return $link ? $link->linkable : null;
    }
}
