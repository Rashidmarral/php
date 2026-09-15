<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_estimate_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->decimal('price_per_sqm', 10, 2)->default(0);
            $table->decimal('multiplier', 5, 2)->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('quick_estimate_foundations', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->string('description_en', 255)->nullable();
            $table->string('description_ar', 255)->nullable();
            $table->decimal('price_per_sqm', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('quick_estimate_addons', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->string('description_en', 255)->nullable();
            $table->string('description_ar', 255)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('unit_type', 20)->default('sqm');
            $table->boolean('is_pro')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('quick_estimates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('project_name', 150)->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('foundation_id')->nullable();
            $table->decimal('total_area', 10, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->text('addons_json')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('lang', 2)->default('en');
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_estimates');
        Schema::dropIfExists('quick_estimate_addons');
        Schema::dropIfExists('quick_estimate_foundations');
        Schema::dropIfExists('quick_estimate_regions');
    }
};
