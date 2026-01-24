<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegrationAccountLink extends Model
{
    protected $table = 'integration_account_links';

    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'account_type',
        'account_id',
        'team_id',
        'created_by_user_id',
    ];

    /**
     * Polymorphe Beziehung zum verknüpften Objekt (z.B. BrandsBrand)
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Team, zu dem diese Verknüpfung gehört
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * User, der die Verknüpfung erstellt hat
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    /**
     * Hole das Account-Model basierend auf account_type und account_id
     */
    public function getAccount()
    {
        return match ($this->account_type) {
            'facebook_page' => IntegrationsFacebookPage::find($this->account_id),
            'instagram_account' => IntegrationsInstagramAccount::find($this->account_id),
            'github_repository' => IntegrationsGithubRepository::find($this->account_id),
            default => null,
        };
    }
}
