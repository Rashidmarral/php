<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-level "assembly" library for Estimates: a reusable group of line
 * items (e.g. "Standard Interior Wall" = framing + drywall + insulation +
 * paint) defined once and inserted into an estimate scaled by a single
 * applied quantity. Same loose-coupling style as materials/cost_codes — no
 * FK constraints, just company_id scoping — so this never risks the
 * existing estimate/line-item tables. AssemblyItem.cost_code_id is a
 * nullable, unenforced FK, mirroring estimate_items.cost_code_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('base_uom', 20);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assembly_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assembly_id')->index();
            $table->string('description', 255);
            $table->string('description_ar', 255)->nullable();
            $table->string('item_type', 20)->default('material');
            $table->string('uom', 20);
            $table->decimal('qty_per_unit', 10, 4);
            $table->decimal('unit_cost', 12, 2);
            $table->unsignedBigInteger('cost_code_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_items');
        Schema::dropIfExists('assemblies');
    }
};
