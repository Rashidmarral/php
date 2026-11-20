<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an authenticated quick estimate link to a real Project, the same way it already
 * links to a real Client — `project_name` stays as a free-text column (the public,
 * unauthenticated quick-estimate calculator on the marketing site has no Projects to pick
 * from and still needs it), but the company-side form now offers a project picker and
 * caches the chosen project's name into `project_name` for display, exactly as it already
 * caches the chosen client's name into `contact_name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_estimates', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('quick_estimates', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};
