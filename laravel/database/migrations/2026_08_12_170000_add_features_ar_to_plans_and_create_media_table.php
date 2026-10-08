<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->text('features_ar')->nullable()->after('features');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('team_size', 20)->nullable()->after('phone');
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('type', 10)->default('image');
            $table->string('file_path', 255)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('team_size');
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('features_ar');
        });
    }
};
