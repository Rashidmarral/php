<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_member_documents', function (Blueprint $table) {
            $table->id();
            // company_id is denormalized alongside user_id so company-scoped queries (and the
            // daily expiry-reminder job) don't need to join through users — same convention as
            // support_tickets.company_id + opened_by_user_id.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
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
        Schema::dropIfExists('team_member_documents');
    }
};
