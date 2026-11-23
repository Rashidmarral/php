<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-city override layer on top of material_library_items.material_cost: the
 * platform admin's answer to "no live Saudi material-price API exists" (GASTAT is
 * region-level PDF bulletins only, no API) — a manually/CSV-maintained price book,
 * one row per (library item, city), consulted only when a company first pulls a
 * reference price into its own materials table (see MaterialLibraryItem::
 * effectivePriceFor()). labor_cost is never city-adjusted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_library_item_city_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_library_item_id');
            $table->string('city', 100);
            $table->decimal('unit_cost', 10, 2);
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Explicit short names: the auto-generated ones (table name + both column
            // names + suffix) are 70/66 characters, over MySQL's 64-character identifier
            // limit — SQLite (this project's dev/test DB) doesn't enforce that limit, so
            // this only surfaces against a real MySQL database.
            $table->unique(['material_library_item_id', 'city'], 'mli_city_prices_item_city_unique');
            $table->foreign('material_library_item_id', 'mli_city_prices_item_foreign')->references('id')->on('material_library_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_library_item_city_prices');
    }
};
