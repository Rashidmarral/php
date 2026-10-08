<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an HOURLY rate for project-timesheet costing (Task #58) — a distinct concept/unit
 * from basic_salary/housing_allowance/other_earnings, which are MONTHLY payroll figures
 * for the WPS export (TeamController::exportWps()). Nullable: not every company sets this
 * up, and a timesheet entry for a member with no rate on file is still loggable (hours
 * tracked) even though its cost can't be computed — see TimesheetEntry.cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('other_earnings');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
