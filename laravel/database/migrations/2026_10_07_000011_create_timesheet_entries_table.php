<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project-level labor hours, feeding the Estimated-vs-Actual cost dashboard
 * (ReportController::costVariance()) as an ADDITIONAL term on top of the existing
 * VendorBill-based 'labor' category — never replacing it.
 *
 * hourly_rate_snapshot/cost are computed ONCE at entry-creation time and stored, never
 * re-derived from the user's current hourly_rate later — same "snapshot a rate/value at
 * entry time" precedent SubcontractPayment already established for retention_percent.
 * Both are nullable: a team member with no hourly_rate set on file can still have hours
 * logged against them (hours worked is still real and trackable) but gets cost = null
 * rather than a silently-wrong 0.00 charge — excluded from the cost sum, flagged in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->decimal('hourly_rate_snapshot', 8, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('logged_by')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['project_id', 'work_date']);
            $table->index(['company_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_entries');
    }
};
