<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable, indexed company_id to audit_logs so a tenant-level
 * activity log can filter `where('company_id', ...)` for strict tenant
 * isolation. Existing admin-panel rows (AuditLog::record() called with no
 * company_id) keep company_id = null — those are platform-level actions
 * not scoped to one company — so nothing about the existing admin audit
 * log changes. See App\Models\AuditLog::recordForCompany().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
