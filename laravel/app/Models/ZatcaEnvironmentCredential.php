<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A save slot for one company's onboarding progress in one ZATCA
 * environment (developer/simulation/production) — see the migration
 * that creates this table and Company::switchZatcaEnvironment() for why
 * this exists separately from the company's own zatca_* columns.
 *
 * Not cast as 'encrypted' on the sensitive fields (private_key, the CSID/
 * secret pairs) — unlike Daftri's own version of this model — because
 * BuildXact's Company model doesn't encrypt the equivalent zatca_*
 * columns either; keeping both sides plain text is what lets
 * switchZatcaEnvironment() move values between them directly.
 */
class ZatcaEnvironmentCredential extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'icv_counter' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
