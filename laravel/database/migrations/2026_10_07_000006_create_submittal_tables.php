<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submittals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            // Sequential per-project display number (SUB-001, SUB-002, ...) — same
            // convention as PaymentCertificate::certificate_number / rfis.rfi_number.
            $table->integer('submittal_number');
            $table->string('title', 200);
            $table->string('description', 500)->nullable();
            // CSI MasterFormat section (e.g. "09 30 00") — free text, not enforced against a list.
            $table->string('spec_section', 30)->nullable();
            $table->string('status', 20)->default('submitted');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->index(['project_id', 'submittal_number']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('submittal_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submittal_id')->index();
            $table->integer('revision_number');
            $table->string('file_path', 255);
            $table->string('file_name', 150);
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submittal_revisions');
        Schema::dropIfExists('submittals');
    }
};
