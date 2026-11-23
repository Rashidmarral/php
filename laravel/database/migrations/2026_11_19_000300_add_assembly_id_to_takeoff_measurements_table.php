<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a "length"/"area" takeoff measurement optionally link to one of the company's
 * Assemblies (see Assembly/AssemblyItem) instead of a single hand-typed unit cost —
 * loosely coupled exactly like every other FK-ish column on this table (no DB-level FK
 * constraint). Null (the default for every existing row) keeps today's 1:1
 * one-measurement-becomes-one-EstimateItem behavior in convertToEstimate() fully intact;
 * see TakeoffController::addMeasurement()/convertToEstimate() for how a set value expands
 * into multiple scaled EstimateItem rows instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('takeoff_measurements', function (Blueprint $table) {
            $table->unsignedBigInteger('assembly_id')->nullable()->after('gross_value')->index();
        });
    }

    public function down(): void
    {
        Schema::table('takeoff_measurements', function (Blueprint $table) {
            $table->dropColumn('assembly_id');
        });
    }
};
