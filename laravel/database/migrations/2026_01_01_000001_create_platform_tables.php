<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('tagline', 255)->nullable();
            $table->string('tagline_ar', 255)->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->integer('max_users')->default(5);
            $table->integer('max_projects')->default(10);
            $table->text('features')->nullable();
            $table->text('feature_flags')->nullable();
            $table->integer('consultation_quota_monthly')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->string('title_en', 200);
            $table->string('title_ar', 200);
            $table->longText('content_en')->nullable();
            $table->longText('content_ar')->nullable();
            $table->string('meta_description_en', 255)->nullable();
            $table->string('meta_description_ar', 255)->nullable();
            $table->string('nav_label_en', 80)->nullable();
            $table->string('nav_label_ar', 80)->nullable();
            $table->boolean('show_in_nav')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->integer('nav_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('translation_key', 190);
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->unique(['locale', 'translation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('plans');
    }
};
