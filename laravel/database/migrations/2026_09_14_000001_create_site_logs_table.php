<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_logs', function (Blueprint $table) {
            $table->id();
            // company_id denormalized alongside project_id, same convention as change_orders/bank_guarantees.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('logged_by')->nullable();
            $table->date('log_date');
            $table->string('weather', 100)->nullable();
            $table->unsignedInteger('workers_on_site')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_logs');
    }
};
