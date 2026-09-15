<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->string('description_en', 255)->nullable();
            $table->string('description_ar', 255)->nullable();
            $table->string('building_type', 100)->nullable();
            $table->string('icon', 10)->default('🏗️');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_choice')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('estimate_template_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id')->index();
            $table->string('section_number', 10)->default('1.0');
            $table->string('section_title_en', 150);
            $table->string('section_title_ar', 150);
            $table->string('item_number', 10)->default('1.1');
            $table->string('description_en', 255);
            $table->string('description_ar', 255);
            $table->string('item_type', 10)->default('material');
            $table->decimal('default_qty', 10, 2)->default(0);
            $table->string('uom', 20)->default('each');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('title', 150);
            $table->string('title_ar', 150)->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->decimal('markup_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('building_type', 100)->nullable();
            $table->string('job_address', 255)->nullable();
            $table->string('source', 20)->default('blank');
            $table->string('share_token', 64)->nullable()->index();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by_name', 150)->nullable();
            $table->longText('signature_data')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('estimate_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estimate_id')->index();
            $table->string('description', 255);
            $table->string('description_ar', 255)->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('item_type', 10)->default('material');
            $table->string('uom', 20)->default('each');
            $table->string('section_title', 150)->nullable();
            $table->string('section_title_ar', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('estimate_template_items');
        Schema::dropIfExists('estimate_templates');
    }
};
