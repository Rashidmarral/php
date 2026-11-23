<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Asphalt / Road Estimate" tool for paving/road contractors — a parent
 * estimate broken into one or more road segments (see
 * App\Models\AsphaltEstimateSegment), each with its own geometry and
 * pavement-layer structure. App\Support\AsphaltEstimateCalc computes every
 * segment's area/volumes/tons/cost and the parent's overall totals; both are
 * cached on their own rows (never trust a stale read elsewhere — the
 * controller recomputes and re-saves them on every store()/update()).
 *
 * company_id uses a real FK with cascade delete (deleting a company removes
 * its asphalt estimates outright), matching this app's few genuinely
 * FK-constrained tables (e.g. approval_chain_steps). project_id/client_id/
 * tax_rate_id stay loose unsignedBigInteger columns with no FK constraint —
 * same convention Estimate itself uses — since a referenced row may be
 * deleted independently and ownership is always re-checked at the
 * application layer (never trust a raw id without a company_id check), not
 * enforced by the database. asphalt_estimate_segments.asphalt_estimate_id is
 * likewise a plain indexed column (no FK) — mirrors assembly_items/
 * estimate_items, whose parent is deleted by the controller explicitly
 * deleting child rows first, not by a DB cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asphalt_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('name', 150);
            $table->string('city', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            // Non-segment cost inputs.
            $table->decimal('transport_cost_per_ton', 10, 2)->default(0);
            $table->decimal('paver_daily_rate', 10, 2)->default(0);
            $table->decimal('paver_days', 8, 2)->default(0);
            $table->decimal('roller_daily_rate', 10, 2)->default(0);
            $table->decimal('roller_days', 8, 2)->default(0);
            $table->unsignedInteger('labor_crew_size')->default(0);
            $table->decimal('labor_days', 8, 2)->default(0);
            $table->decimal('labor_daily_rate_per_worker', 10, 2)->default(0);
            $table->decimal('labor_food_daily_rate_per_worker', 10, 2)->default(0);
            $table->decimal('surveyor_fee', 10, 2)->default(0);
            $table->decimal('lab_testing_fee', 10, 2)->default(0);
            $table->decimal('mobilization_fee', 10, 2)->default(0);

            // Markup/tax — mirrors estimates.
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);

            // Cached computed totals — recomputed and saved every time the
            // estimate is saved (see AsphaltEstimateCalc).
            $table->decimal('segments_subtotal', 14, 2)->default(0);
            $table->decimal('other_costs_subtotal', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('markup_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('total_asphalt_tons', 12, 2)->default(0);
            $table->decimal('total_area_m2', 12, 2)->default(0);

            $table->timestamps();
        });

        Schema::create('asphalt_estimate_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asphalt_estimate_id')->index();
            $table->string('name', 150);
            $table->unsignedInteger('sort_order')->default(0);

            $table->decimal('length_m', 10, 2);
            $table->decimal('width_m', 8, 2);
            $table->decimal('median_width_m', 8, 2)->default(0);

            $table->decimal('subbase_thickness_mm', 8, 2)->default(0);
            $table->decimal('subbase_unit_cost_per_m3', 10, 2)->default(0);

            $table->decimal('base_course_thickness_mm', 8, 2)->default(0);
            $table->decimal('base_course_unit_cost_per_m3', 10, 2)->default(0);

            $table->decimal('asphalt_binder_thickness_mm', 8, 2)->default(0);
            $table->decimal('asphalt_wearing_thickness_mm', 8, 2)->default(0);
            $table->decimal('asphalt_density_kg_per_m3', 8, 2)->default(2400);
            $table->decimal('asphalt_unit_cost_per_ton', 10, 2)->default(0);

            // Cached computed values — see AsphaltEstimateCalc::computeSegment().
            $table->decimal('net_width_m', 8, 2)->default(0);
            $table->decimal('area_m2', 12, 2)->default(0);
            $table->decimal('subbase_volume_m3', 12, 3)->default(0);
            $table->decimal('base_course_volume_m3', 12, 3)->default(0);
            $table->decimal('asphalt_volume_m3', 12, 3)->default(0);
            $table->decimal('asphalt_tons', 12, 2)->default(0);
            $table->decimal('subbase_cost', 12, 2)->default(0);
            $table->decimal('base_course_cost', 12, 2)->default(0);
            $table->decimal('asphalt_cost', 12, 2)->default(0);
            $table->decimal('segment_total', 12, 2)->default(0);

            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asphalt_estimate_segments');
        Schema::dropIfExists('asphalt_estimates');
    }
};
