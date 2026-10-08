<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** The previous version this document replaces, if any. */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'supersedes_id');
    }

    /** The later document(s) that replaced this one — normally at most one. */
    public function supersededBy(): HasMany
    {
        return $this->hasMany(Document::class, 'supersedes_id');
    }

    /** Scope: only the current (latest) version of every document — the default for list views. */
    public function scopeCurrentVersion(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * The full version chain for this document, oldest first, ending with whichever row
     * is_current — i.e. every past and present version of the same uploaded file, walked
     * back through supersedes_id. Never edits/deletes a row; each version is its own
     * immutable documents row (same precedent as SubcontractPayment's cumulative chain).
     *
     * @return array<int, Document>
     */
    public function versionChain(): array
    {
        $chain = [$this];

        $cursor = $this;
        while ($cursor->supersedes_id) {
            $cursor = Document::find($cursor->supersedes_id);
            if (!$cursor) {
                break;
            }
            $chain[] = $cursor;
        }

        $cursor = $this;
        while (true) {
            $next = Document::where('supersedes_id', $cursor->id)->first();
            if (!$next) {
                break;
            }
            $chain[] = $next;
            $cursor = $next;
        }

        $unique = [];
        foreach ($chain as $doc) {
            $unique[$doc->id] = $doc;
        }
        ksort($unique);
        return array_values($unique);
    }
}
