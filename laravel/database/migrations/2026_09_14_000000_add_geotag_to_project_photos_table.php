<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_photos', function (Blueprint $table) {
            // Captured client-side from navigator.geolocation when the browser grants it — nullable
            // since geolocation can be denied, unsupported, or simply unavailable indoors.
            $table->decimal('latitude', 10, 7)->nullable()->after('taken_on');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('project_photos', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
