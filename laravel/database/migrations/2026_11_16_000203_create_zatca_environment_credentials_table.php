<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (company, ZATCA environment) — a save slot for that
 * environment's onboarding progress. Before this, every zatca_* field
 * lived directly on companies, so switching zatca_environment (Developer/
 * Simulation/Production) had nowhere to keep the environment being
 * switched away from — every environment change would otherwise wipe the
 * flat columns back to a blank slate, forcing a full CSR/OTP/compliance-
 * check redo even when switching back to an environment that had already
 * completed onboarding.
 *
 * companies.zatca_* stays the single source of truth every other read
 * site (admin filtering, CompanyZatcaController, ZatcaSyncService, this
 * company's own dashboard) already depends on — it's just now a cache of
 * whichever environment is currently selected. Company::switchZatcaEnvironment()
 * saves the flat columns into this table for the environment being left,
 * then loads (or defaults) the row for the environment being entered back
 * onto the flat columns, so no other code needs to change.
 *
 * Column names here deliberately keep Daftri's own generic naming
 * (onboarding_status, egs_serial, ...) rather than BuildXact's zatca_-
 * prefixed column names — this table only ever holds ZATCA data, so the
 * prefix would be redundant, and it's Company::switchZatcaEnvironment()'s
 * job (not this schema's) to map between the two naming conventions.
 *
 * Both timestamps are kept (see the matching comment on the
 * zatca_invoice_logs migration) since a slot's onboarding_status/
 * last_sync_at genuinely does get updated repeatedly as onboarding
 * progresses and as the company keeps using this environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_environment_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20);
            $table->string('onboarding_status', 30)->default('not_started');
            $table->string('egs_serial', 100)->nullable();
            $table->string('common_name')->nullable();
            $table->string('organization_unit_name')->nullable();
            $table->string('business_category')->nullable();
            $table->text('csr')->nullable();
            $table->text('private_key')->nullable();
            $table->text('compliance_request_id')->nullable();
            $table->text('compliance_csid')->nullable();
            $table->text('compliance_secret')->nullable();
            $table->text('production_request_id')->nullable();
            $table->text('production_csid')->nullable();
            $table->text('production_secret')->nullable();
            $table->string('last_invoice_hash', 128)->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_environment_credentials');
    }
};
