<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('file_path', 255);
            $table->string('file_type', 100)->nullable();
            $table->integer('file_size')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('doc_type', 30)->default('other');
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('document_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_documents');
        Schema::dropIfExists('documents');
    }
};
