<?php

namespace Platform\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Models\User;

class TimeEntry extends Model
{
    use SoftDeletes;

    protected $table = 'integrations_time_entries';

    protected $fillable = [
        'user_id',
        'team_id',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'project_id',
        'project_name',
        'context',
        'description',
        'type',
        'tags',
        'source',
        'bulk_id',
    ];

    protected $casts = [
        'date' => 'date',
        'tags' => 'array',
        'duration_minutes' => 'integer',
        'project_id' => 'integer',
        'team_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Berechnet die Dauer als formatierte Zeichenkette (z.B. "2h 30min")
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}min";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$minutes}min";
    }
}
