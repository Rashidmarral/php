<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employer-level ID a WPS salary file header requires: the company's
 * Ministry of Human Resources and Social Development (MHRSD) establishment
 * number, also used as the GOSI establishment ID banks key WPS files
 * against. Distinct from cr_number/vat_number (commercial registration and
 * ZATCA tax fields already on this table) — nullable, since it isn't
 * collected anywhere else in this app yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('mol_establishment_number', 50)->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('mol_establishment_number');
        });
    }
};
