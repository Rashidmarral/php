<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrades takeoff_pages with the exact same immutable-history version tracking
     * documents already has (see 2026_10_07_000007_add_revision_control_to_documents_table.php
     * and Document::versionChain()) — uploading a revised plan sheet creates a NEW
     * takeoff_pages row with supersedes_id pointing at the row it replaces and flips the
     * OLD row's is_current to false; the old row itself is never modified otherwise, and
     * its measurements (takeoff_measurements.takeoff_page_id) stay attached to it.
     *
     * Every existing row predates this feature and is, by definition, its own first-and-
     * only version — the column defaults below (version=1, supersedes_id=null,
     * is_current=true) already backfill every existing row correctly, so no separate
     * data-migration step is needed (same as the documents migration this mirrors).
     */
    public function up(): void
    {
        Schema::table('takeoff_pages', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('scale_unit');
            // Self-referencing — no FK constraint, same loose-coupling convention as every
            // other cross-row reference in this app (e.g. documents.supersedes_id).
            $table->unsignedBigInteger('supersedes_id')->nullable()->index()->after('version');
            $table->boolean('is_current')->default(true)->after('supersedes_id');
        });
    }

    public function down(): void
    {
        Schema::table('takeoff_pages', function (Blueprint $table) {
            // SQLite's native DROP COLUMN refuses a column that still has an index on it —
            // the index must go first (same ordering as the documents migration's down()).
            $table->dropIndex(['supersedes_id']);
        });
        Schema::table('takeoff_pages', function (Blueprint $table) {
            $table->dropColumn(['version', 'supersedes_id', 'is_current']);
        });
    }
};
