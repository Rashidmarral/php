<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_estimate_quality_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->decimal('multiplier', 5, 2)->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        if (!Schema::hasColumn('quick_estimates', 'quality_tier_id')) {
            Schema::table('quick_estimates', function (Blueprint $table) {
                $table->unsignedBigInteger('quality_tier_id')->nullable()->index()->after('foundation_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quick_estimates', 'quality_tier_id')) {
            Schema::table('quick_estimates', function (Blueprint $table) {
                $table->dropColumn('quality_tier_id');
            });
        }
        Schema::dropIfExists('quick_estimate_quality_tiers');
    }
};
