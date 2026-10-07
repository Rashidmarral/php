<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A logged toolbox talk / safety briefing held on a project — a standalone record
 * (date, topic, who conducted it, how many attended), same shape as SiteLog: no
 * status lifecycle, no updated_at, just an append-only dated log.
 */
class ToolboxTalk extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'talk_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conductedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }
}
