<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings BuildXact's ZATCA Phase 2 schema up to parity with the ported
 * (Daftri-derived) implementation in App\Support\Zatca\*.
 *
 * Purely additive — no existing column is dropped or renamed:
 *  - zatca_environment gains a third real value. Daftri's ZatcaApiClient
 *    (and the CSR's certificateTemplateName, which ZATCA validates against
 *    the OTP's own environment) key off 'developer'/'simulation'/
 *    'production' rather than BuildXact's original 'sandbox'/'production'.
 *    Existing 'sandbox' rows are backfilled to 'developer' (ZATCA's actual
 *    sandbox/developer-portal environment) so no company silently loses
 *    its environment selection.
 *  - New zatca_* columns the ported ZatcaCryptoService/ZatcaSyncService
 *    read/write that BuildXact's original (pre-port) schema never needed:
 *    CSR subject fields (egs_serial/common_name/organization_unit_name/
 *    business_category), the B2B/B2C sync toggles that also drive which
 *    invoice-type capability the CSR declares, the two ZATCA request IDs
 *    returned alongside each CSID, and onboarding/sync timestamps.
 *
 * zatca_status (already present) keeps its column name but now carries
 * Daftri's richer onboarding_status vocabulary end-to-end: not_started,
 * csr_generated, compliance_pending, compliance_verified, onboarded,
 * failed — see CompanyZatcaController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('zatca_egs_serial', 100)->nullable()->after('zatca_environment');
            $table->string('zatca_common_name', 255)->nullable()->after('zatca_egs_serial');
            $table->string('zatca_organization_unit_name', 255)->nullable()->after('zatca_common_name');
            $table->string('zatca_business_category', 255)->nullable()->after('zatca_organization_unit_name');
            $table->boolean('zatca_sync_b2b')->default(true)->after('zatca_business_category');
            $table->boolean('zatca_sync_b2c')->default(true)->after('zatca_sync_b2b');
            $table->text('zatca_compliance_request_id')->nullable()->after('zatca_compliance_csid');
            $table->text('zatca_production_request_id')->nullable()->after('zatca_production_csid');
            $table->timestamp('zatca_linked_at')->nullable()->after('zatca_last_invoice_hash');
            $table->timestamp('zatca_last_sync_at')->nullable()->after('zatca_linked_at');
        });

        DB::table('companies')->where('zatca_environment', 'sandbox')->update(['zatca_environment' => 'developer']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'zatca_egs_serial', 'zatca_common_name', 'zatca_organization_unit_name', 'zatca_business_category',
                'zatca_sync_b2b', 'zatca_sync_b2c', 'zatca_compliance_request_id', 'zatca_production_request_id',
                'zatca_linked_at', 'zatca_last_sync_at',
            ]);
        });

        DB::table('companies')->where('zatca_environment', 'developer')->update(['zatca_environment' => 'sandbox']);
    }
};
