<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * One custom domain an admin has pointed at a company's panel (see the migration's own
 * docblock for the product shape). The `saved`/`deleted` boot hooks below forget
 * ResolveCompanyDomain's cached active-domain map on every write, so a newly
 * added/toggled/removed domain takes effect on the very next request instead of waiting
 * out the cache TTL — there are only ~10 companies, so re-querying on the rare write is
 * free, and every read in between stays cache-only.
 */
class CompanyDomain extends Model
{
    public const CACHE_KEY = 'company_domains_map';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
