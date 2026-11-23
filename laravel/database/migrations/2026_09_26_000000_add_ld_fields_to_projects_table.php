<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidated Damages (LD) exposure inputs, plus the actual completion date needed to
 * compute delay against Project.end_date (the project's own existing contractual
 * completion date — see ProjectController::portfolioStats()/index(), which already use
 * end_date as the "behind schedule" baseline; LD reuses that same field rather than
 * introducing a second, competing notion of "when this contract is due").
 *
 * ld_rate_per_day is a fixed SAR amount per day of delay (the standard KSA contract
 * convention), and ld_cap_percent is the near-universal cap on total LD as a % of
 * contract value. Both are informational inputs for Project::ldExposure() — see that
 * method's docblock — never an auto-applied deduction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('actual_completion_date')->nullable()->after('defects_liability_end_date');
            $table->decimal('ld_rate_per_day', 12, 2)->nullable()->after('actual_completion_date');
            $table->decimal('ld_cap_percent', 5, 2)->nullable()->after('ld_rate_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['actual_completion_date', 'ld_rate_per_day', 'ld_cap_percent']);
        });
    }
};
