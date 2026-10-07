<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same shape as compliance_documents (company-level) and team_member_documents
        // (worker-level) — this is the supplier-level version of the same "entity has
        // compliance documents with expiry dates" pattern. doc_type is free-form (no
        // default/enum constraint) since suppliers' own paperwork (CR, insurance,
        // classification certificate, ISO certs, etc.) doesn't fit one fixed list the
        // way the company's own TYPES const does.
        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            // company_id denormalized alongside supplier_id so company-scoped queries (and the
            // daily expiry-reminder job) don't need to join through suppliers — same convention
            // as team_member_documents.company_id + user_id.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('doc_type', 50)->nullable();
            $table->string('name', 150);
            $table->string('document_number', 100)->nullable();
            $table->string('file_path', 255)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');
    }
};
