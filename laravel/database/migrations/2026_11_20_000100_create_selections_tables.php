<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allowance-tracking "Selections" module: a project-scoped budgeted allowance for
 * something not yet finalized (e.g. "Kitchen Countertop — 3,000 SAR allowance"), with a
 * handful of priced options the client picks from in the portal. Same loose-coupling
 * style as assemblies/materials — no FK constraints, just company_id/project_id/
 * selection_id scoping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('category', 100);
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('allowance_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('selection_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('selection_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_chosen')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_options');
        Schema::dropIfExists('selections');
    }
};
