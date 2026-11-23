<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('status', 20)->default('planning');
            $table->decimal('budget', 12, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('project_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('caption', 255)->nullable();
            $table->string('file_path', 255);
            $table->date('taken_on')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('change_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('title', 150);
            $table->string('title_ar', 150)->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('schedule_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('title', 150);
            $table->string('title_ar', 150)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('category', 20)->default('material');
            $table->string('description', 255);
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('bill_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('unpaid');
            $table->string('file_path', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('schedule_tasks');
        Schema::dropIfExists('change_orders');
        Schema::dropIfExists('project_photos');
        Schema::dropIfExists('projects');
    }
};
