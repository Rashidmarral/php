<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // Company-wide, not per-user — same tenant-scoping shape as AuditLog's
            // own company_id column: any user in the company sees every row for it.
            $table->unsignedBigInteger('company_id')->index();
            // Short category key (invoice_paid, estimate_signed, ...) — drives the
            // bell/list icon, mirrors AuditLog::action's "short string key" shape.
            $table->string('type', 60);
            $table->string('title', 255);
            $table->text('message')->nullable();
            // Relative app URL straight to the record this notification is about,
            // e.g. "/app/invoices/12" — rendered as-is, never an absolute URL.
            $table->string('link', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['company_id', 'read_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
