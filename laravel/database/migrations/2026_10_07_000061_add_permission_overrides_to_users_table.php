<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pragmatic, additive escape hatch on top of the 5 fixed roles in
 * AppServiceProvider::boot(): a per-user JSON map of {ability => bool} that can
 * override one specific Gate ability for this one user beyond what their role
 * would normally grant or deny (e.g. an estimator personally granted
 * 'approve_documents' without promoting them to admin). Null/absent key means
 * "no override, fall through to the role default" — see User::permissionOverride().
 * This does NOT add new roles or abilities; ASSIGNABLE_ROLES is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('permission_overrides')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permission_overrides');
        });
    }
};
