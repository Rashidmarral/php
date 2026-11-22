<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit trail for CSV imports into material_library_item_city_prices —
 * mirrors the simple "who did what when" shape of audit_logs, but isn't scoped to a
 * company since the catalog itself is platform-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_library_price_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('city', 100);
            $table->string('filename', 255);
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('matched_count');
            $table->unsignedInteger('skipped_count');
            $table->unsignedBigInteger('imported_by');
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_library_price_import_logs');
    }
};
