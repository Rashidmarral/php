<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gives Client the same VAT/CR/structured-Saudi-address shape Company
     * already has (see 2026_01_01_000002_create_companies_table.php) so
     * ZatcaXmlGenerator can populate a real standard/B2B buyer party
     * (cac:AccountingCustomerParty) instead of always falling back to the
     * simplified/B2C profile. All columns are nullable additions — a
     * client with none of these set keeps producing exactly the same
     * simplified-invoice XML as before (see ZatcaXmlGenerator::
     * isB2bEligible()).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('vat_number', 50)->nullable()->after('address');
            $table->string('cr_number', 50)->nullable()->after('vat_number');
            $table->string('building_number', 10)->nullable()->after('cr_number');
            $table->string('street_name', 255)->nullable()->after('building_number');
            $table->string('district', 255)->nullable()->after('street_name');
            $table->string('city', 100)->nullable()->after('district');
            $table->string('postal_code', 10)->nullable()->after('city');
            $table->string('additional_number', 10)->nullable()->after('postal_code');
            $table->string('country_code', 2)->default('SA')->after('additional_number');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'vat_number', 'cr_number', 'building_number', 'street_name',
                'district', 'city', 'postal_code', 'additional_number', 'country_code',
            ]);
        });
    }
};
