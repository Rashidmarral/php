<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('requested_by');
            $table->string('type', 20)->default('chat');
            $table->string('status', 20)->default('requested');
            $table->string('topic', 200)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('admin_notes', 1000)->nullable();
            $table->string('assigned_engineer', 150)->nullable();
            $table->date('preferred_date')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name', 150)->nullable();
            $table->string('action', 60);
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('details', 500)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('consultations');
    }
};
