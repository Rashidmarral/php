<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('password')->nullable();
            $table->boolean('portal_enabled')->default(false);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('company_name', 150)->nullable();
            $table->string('company_name_ar', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('source', 50)->default('other');
            $table->string('status', 20)->default('new');
            $table->decimal('estimated_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('converted_client_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('clients');
    }
};
