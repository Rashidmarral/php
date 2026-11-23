<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('takeoffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('name', 150);
            $table->string('plan_image_path', 255)->nullable();
            $table->decimal('scale_px_per_unit', 12, 6)->default(1);
            $table->string('scale_unit', 10)->default('m');
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('takeoff_measurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('takeoff_id')->index();
            $table->string('type', 10);
            $table->string('label', 150);
            $table->text('points_json')->nullable();
            $table->decimal('value', 12, 3)->default(0);
            $table->string('unit', 10)->default('m');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('takeoff_measurements');
        Schema::dropIfExists('takeoffs');
    }
};
