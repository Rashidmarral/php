<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('contact_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('client_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->decimal('rate_percent', 5, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('client_types');
        Schema::dropIfExists('contact_types');
        Schema::dropIfExists('building_types');
    }
};
