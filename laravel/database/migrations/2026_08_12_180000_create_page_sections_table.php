<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page_slug', 40)->index();
            $table->string('section_type', 20)->default('feature_grid');
            $table->string('title_en', 200)->nullable();
            $table->string('title_ar', 200)->nullable();
            $table->string('subtitle_en', 500)->nullable();
            $table->string('subtitle_ar', 500)->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_ar')->nullable();
            $table->string('button_text_en', 100)->nullable();
            $table->string('button_text_ar', 100)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->text('items')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
