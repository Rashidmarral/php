<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real equipment/fleet asset register — up to now "Equipment" only existed as a cost
     * category string (VendorBill::CATEGORIES['equipment'] / EstimateItem.item_type). This adds
     * an actual asset register (equipment), maintenance scheduling with the same
     * reminder_sent_at-guarded expiry-reminder convention as SupplierDocument/BankGuarantee
     * (equipment_maintenance_logs), and utilization/assignment tracking against a project
     * (equipment_assignments) — additive and optionally linkable from other modules, never a
     * replacement for the existing cost-classification system.
     */
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            // Asset number / serial — free text, no uniqueness constraint (a contractor may not
            // have one for older assets).
            $table->string('asset_number', 100)->nullable();
            // Free text, not a rigid enum — Saudi contractors use varied naming ("Excavator",
            // "Generator", "Scaffolding", "Concrete Mixer", ...), same convention as
            // Supplier::category.
            $table->string('category', 100)->nullable();
            $table->string('ownership_type', 20)->default('owned');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            // Relevant whether this asset is rented FROM a supplier (ownership_type = rented) or
            // rented OUT by this company — kept as a single day-rate field either way.
            $table->decimal('rental_cost_per_day', 10, 2)->nullable();
            $table->string('status', 20)->default('available');
            $table->string('notes', 500)->nullable();
            // Only set when rented from a known supplier — reuses the existing Supplier model
            // rather than inventing a parallel vendor concept.
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'category']);
        });

        Schema::create('equipment_maintenance_logs', function (Blueprint $table) {
            $table->id();
            // company_id denormalized alongside equipment_id so company-scoped queries (and the
            // daily maintenance-due-reminder job) don't need to join through equipment — same
            // convention as supplier_documents.company_id + supplier_id.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('equipment_id')->index();
            $table->date('maintenance_date');
            $table->string('description', 500);
            $table->decimal('cost', 10, 2)->nullable();
            // A person or vendor name — free text, same convention as category above.
            $table->string('performed_by', 150)->nullable();
            // Schedules the NEXT maintenance; the daily reminder job fires once per log entry
            // when this falls within the next 7 days, guarded by reminder_sent_at.
            $table->date('next_due_date')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['equipment_id', 'maintenance_date']);
        });

        Schema::create('equipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('equipment_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->date('assigned_date');
            // Null = currently assigned/in-use. A single asset can't be in two places at once —
            // EquipmentController enforces server-side that at most one assignment per equipment
            // has a null returned_date at any time.
            $table->date('returned_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['equipment_id', 'returned_date']);
            $table->index(['project_id', 'returned_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_assignments');
        Schema::dropIfExists('equipment_maintenance_logs');
        Schema::dropIfExists('equipment');
    }
};
