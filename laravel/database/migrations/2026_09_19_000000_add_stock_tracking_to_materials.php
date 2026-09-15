<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('qty_on_hand', 12, 2)->default(0)->after('unit_cost');
            // Null means no reorder alert is shown for this material — not everyone bothers to set a threshold.
            $table->decimal('reorder_level', 12, 2)->nullable()->after('qty_on_hand');
        });

        Schema::create('material_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('material_id')->index();
            // 'receive': stock arriving into the warehouse (increases qty_on_hand).
            // 'issue': stock going out, usually to a project (decreases qty_on_hand).
            // 'adjustment': a manual correction — see the `direction` column below for
            // which way it moves qty_on_hand.
            $table->string('type', 20);
            // Always stored positive, for every type — `type` (and, for 'adjustment',
            // `direction`) decides whether it increases or decreases qty_on_hand, never
            // the sign of this column. A negative qty on an 'issue' row would be a
            // confusing double-negative, so it's simply never allowed.
            $table->decimal('qty', 12, 2);
            // Only meaningful for type='adjustment' ('up' increases qty_on_hand, 'down'
            // decreases it) — null for 'receive' and 'issue', whose direction is implied
            // by their type alone.
            $table->string('direction', 10)->nullable();
            // Set when material is issued out to a specific project; null for a
            // warehouse-level receive or adjustment.
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_movements');
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['qty_on_hand', 'reorder_level']);
        });
    }
};
