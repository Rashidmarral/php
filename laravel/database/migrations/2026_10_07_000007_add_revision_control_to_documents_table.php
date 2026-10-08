<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrades the existing `documents` table with immutable-history version tracking —
     * same "never edit/delete the old row, append a new one" precedent as
     * SubcontractPayment's cumulative chain. Uploading a new version creates a NEW
     * documents row with supersedes_id pointing at the row it replaces and flips the
     * OLD row's is_current to false; the old row itself is never modified otherwise.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->integer('version')->default(1)->after('file_size');
            // Self-referencing — no FK constraint, same convention as every other
            // cross-table reference in this app (e.g. documents.project_id).
            $table->unsignedBigInteger('supersedes_id')->nullable()->index()->after('version');
            $table->boolean('is_current')->default(true)->after('supersedes_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // SQLite's native DROP COLUMN refuses a column that still has an index on it
            // ("error in index ... after drop column") — the index must go first.
            $table->dropIndex(['supersedes_id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['version', 'supersedes_id', 'is_current']);
        });
    }
};
