<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company-level analog of users.permission_overrides: a JSON map of
 * {feature_key => bool} that can grant or deny one Feature::ALL key for this
 * ONE company beyond/instead of whatever its subscribed plan's feature_flags
 * says — a super-admin escape hatch for one-off deals, pilots, or support
 * cases, not a new plan tier. Null/absent key means "no override, fall
 * through to the plan default" — see Company::featureOverride() and
 * App\Support\Feature::allows()/allowsForCompany().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('feature_overrides')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('feature_overrides');
        });
    }
};
