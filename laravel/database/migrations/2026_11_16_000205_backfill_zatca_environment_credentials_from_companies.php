<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every existing company's zatca_* columns already hold whatever
 * onboarding progress exists for zatca_environment (their currently
 * selected environment) — save that into the new per-environment table
 * under that same environment, so a switch away and back to it (via
 * Company::switchZatcaEnvironment(), see CompanyZatcaController) finds it
 * waiting instead of starting from a blank slate.
 *
 * Adapted from Daftri's 2026_09_02_182901_backfill_zatca_environment_
 * credentials_from_companies — field names are BuildXact's own existing
 * companies columns (zatca_status, not zatca_onboarding_status; no
 * zatca_organization_unit_name-style gap — see
 * 2026_09_11_000000_add_zatca_phase2_fields_to_companies_table for where
 * each of these columns actually came from).
 *
 * Unlike Daftri's version, values are NOT copied under an 'encrypted'
 * Eloquent cast on either side — BuildXact's own Company model doesn't
 * encrypt these columns either (see Company::casts()), so copying the
 * raw column values here keeps the two interchangeable without a
 * decrypt/re-encrypt mismatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')
            ->where('zatca_status', '!=', 'not_started')
            ->orWhereNotNull('zatca_csr')
            ->select([
                'id', 'zatca_environment', 'zatca_status', 'zatca_egs_serial',
                'zatca_common_name', 'zatca_organization_unit_name', 'zatca_business_category',
                'zatca_csr', 'zatca_private_key',
                'zatca_compliance_request_id', 'zatca_compliance_csid', 'zatca_compliance_secret',
                'zatca_production_request_id', 'zatca_production_csid', 'zatca_production_secret',
                'zatca_last_invoice_hash', 'zatca_icv_counter', 'zatca_linked_at', 'zatca_last_sync_at',
            ])
            ->orderBy('id')
            ->chunk(200, function ($companies) {
                $now = now();

                foreach ($companies as $company) {
                    DB::table('zatca_environment_credentials')->updateOrInsert(
                        ['company_id' => $company->id, 'environment' => $company->zatca_environment ?: 'developer'],
                        [
                            'onboarding_status' => $company->zatca_status ?: 'not_started',
                            'egs_serial' => $company->zatca_egs_serial,
                            'common_name' => $company->zatca_common_name,
                            'organization_unit_name' => $company->zatca_organization_unit_name,
                            'business_category' => $company->zatca_business_category,
                            'csr' => $company->zatca_csr,
                            'private_key' => $company->zatca_private_key,
                            'compliance_request_id' => $company->zatca_compliance_request_id,
                            'compliance_csid' => $company->zatca_compliance_csid,
                            'compliance_secret' => $company->zatca_compliance_secret,
                            'production_request_id' => $company->zatca_production_request_id,
                            'production_csid' => $company->zatca_production_csid,
                            'production_secret' => $company->zatca_production_secret,
                            'last_invoice_hash' => $company->zatca_last_invoice_hash,
                            'icv_counter' => $company->zatca_icv_counter ?: 0,
                            'linked_at' => $company->zatca_linked_at,
                            'last_sync_at' => $company->zatca_last_sync_at,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        // Irreversible on purpose — the flat companies.zatca_* columns are
        // untouched by up() (they stay the live/active copy either way),
        // so there's nothing to restore by deleting the backfilled rows.
    }
};
