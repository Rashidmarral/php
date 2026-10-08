<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Defects liability period end date (the point after which retention held on the
 * contract is due for release) plus a once-only reminder-sent marker for it —
 * mirrors BankGuarantee.reminder_sent_at, but scoped to the project since a single
 * reminder should cover all of a project's retention across every invoice/certificate,
 * not one email per invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('defects_liability_end_date')->nullable()->after('end_date');
            $table->timestamp('retention_reminder_sent_at')->nullable()->after('defects_liability_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['defects_liability_end_date', 'retention_reminder_sent_at']);
        });
    }
};
