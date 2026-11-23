<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client-visible progress feed entry — the "Project Updates" feature: a plain-language
 * post a PM writes (optionally first drafted by AiProjectUpdateDrafter from the project's
 * own recent activity, then always reviewed/edited by a human) summarizing what's been
 * happening on a project. A draft (published_at null) is staff-only; once published_at is
 * set, PortalController::project() surfaces it to the client. Mirrors SiteLog/ProjectPhoto's
 * own shape (no updated_at, company_id denormalized alongside project_id).
 */
class ProjectUpdate extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
