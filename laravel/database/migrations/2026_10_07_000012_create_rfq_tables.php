<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            // Nullable: an RFQ can be tied to a project (most common) or kept standalone
            // (e.g. a framework/panel bid not yet attached to a specific job).
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('title', 200);
            $table->string('status', 20)->default('draft');
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['company_id', 'status']);
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id')->index();
            $table->string('description', 255);
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('unit', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('rfq_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_id')->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            // Only one quote per RFQ can carry this at a time — award() clears every other
            // quote on the same RFQ before setting the chosen one, inside a transaction.
            $table->boolean('is_awarded')->default(false);
            $table->date('submitted_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['rfq_id', 'is_awarded']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_quotes');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
