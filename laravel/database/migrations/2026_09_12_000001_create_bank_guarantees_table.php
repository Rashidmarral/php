<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_guarantees', function (Blueprint $table) {
            $table->id();
            // company_id is denormalized alongside project_id so company-scoped queries (and the
            // daily expiry-reminder job) don't need to join through projects — same convention as
            // change_orders.company_id + project_id.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('type', 30)->default('other');
            $table->string('bank_name', 150)->nullable();
            $table->string('guarantee_number', 100)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('file_path', 255)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_guarantees');
    }
};
