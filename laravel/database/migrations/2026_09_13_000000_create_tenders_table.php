<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 255);
            $table->string('title_ar', 255);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('entity_name_en', 150);
            $table->string('entity_name_ar', 150)->nullable();
            $table->string('category', 20)->default('government');
            $table->decimal('estimated_value_sar', 14, 2)->nullable();
            $table->date('submission_deadline');
            $table->string('location_city', 100)->nullable();
            $table->string('source_url', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};
