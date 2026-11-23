<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            // Sequential per-project display number (RFI-001, RFI-002, ...) — same
            // convention as PaymentCertificate::certificate_number.
            $table->integer('rfi_number');
            $table->string('subject', 200);
            $table->text('question');
            $table->unsignedBigInteger('raised_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status', 15)->default('open');
            $table->date('due_date')->nullable();
            // An RFI often references a specific drawing/spec already on file.
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->index(['project_id', 'rfi_number']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('rfi_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfi_id')->index();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_name', 150);
            $table->text('message');
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 150)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfi_messages');
        Schema::dropIfExists('rfis');
    }
};
