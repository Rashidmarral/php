<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supplier/Vendor Portal auth fields — identical column shape to clients' own password/portal_enabled columns (see 2026_01_01_000004_create_crm_tables.php). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('password')->nullable();
            $table->boolean('portal_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['password', 'portal_enabled']);
        });
    }
};
