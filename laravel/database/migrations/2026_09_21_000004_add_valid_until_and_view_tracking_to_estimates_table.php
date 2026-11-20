<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quote validity/expiry date, plus client view-tracking (first/last viewed, view count) on the public share link. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->date('valid_until')->nullable()->after('job_address');
            $table->timestamp('first_viewed_at')->nullable()->after('signed_ip');
            $table->timestamp('last_viewed_at')->nullable()->after('first_viewed_at');
            $table->unsignedInteger('view_count')->default(0)->after('last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['valid_until', 'first_viewed_at', 'last_viewed_at', 'view_count']);
        });
    }
};
