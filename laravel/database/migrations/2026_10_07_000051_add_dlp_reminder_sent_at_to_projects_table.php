<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A separate once-only reminder marker for the defects-liability-period-ending nudge
 * (RunDailyTasks block below), distinct from retention_reminder_sent_at: that one guards
 * the retention-release-due reminder (a money concern), this one guards the "go check the
 * punch list before the warranty window closes" reminder (a defects concern). A project
 * needs both independently, so they can't share a single flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('dlp_reminder_sent_at')->nullable()->after('retention_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('dlp_reminder_sent_at');
        });
    }
};
