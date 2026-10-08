<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional/add-on line items a client can toggle on or off before signing, plus the accepted total once they've decided. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('total');
            $table->boolean('client_selected')->nullable()->after('is_optional');
        });
        Schema::table('estimates', function (Blueprint $table) {
            $table->decimal('accepted_total', 12, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn(['is_optional', 'client_selected']);
        });
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['accepted_total']);
        });
    }
};
