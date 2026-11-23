<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extension of Time (EOT) requests: a contractor asks for the contract's effective
 * completion date to be pushed back by requested_days (weather, client-caused delay,
 * scope change, etc.). Only APPROVED requests count toward
 * Project::effectiveCompletionDate() — a pending or rejected request never shifts the
 * LD baseline. Approval is the same weight of contractual sign-off as the existing
 * estimate/invoice approval workflow, so it reuses that same 'approve_documents' Gate
 * rather than inventing a parallel permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_of_time_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->integer('requested_days');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_of_time_requests');
    }
};
