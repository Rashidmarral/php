<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional free-text `city` to projects, mirroring the `city` column
 * already on companies/clients (see App\Support\SaudiCities). Purely
 * informational today — e.g. shown on the project form and reused as the
 * default "pricing city" when starting an Asphalt / Road Estimate for that
 * project — no behavior anywhere reads it yet, so this is purely additive
 * and safe on an existing projects table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('name_ar');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('city');
        });
    }
};
