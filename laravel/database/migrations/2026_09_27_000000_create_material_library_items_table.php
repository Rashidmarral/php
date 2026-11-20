<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->nullable();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('category', 100);
            $table->string('unit', 20);
            $table->decimal('material_cost', 10, 2);
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_library_items');
    }
};
