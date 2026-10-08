<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 4 of the invoice-template project: a company stamp/seal image,
 * mirroring logo_path's exact shape (nullable string(255), same
 * uploads/ convention as SettingsController::update()'s logo handling).
 *
 * Stage 2's 'card' and 'bilingual' layout partials (itpl-card.blade.php,
 * itpl-bilingual.blade.php) already read $company->stamp_path directly —
 * that slot was deliberately built ahead of this column existing (see
 * those partials' own comments) — so populating it here is the only wiring
 * needed for the stamp to start appearing on those layouts' PDFs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('stamp_path', 255)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('stamp_path');
        });
    }
};
