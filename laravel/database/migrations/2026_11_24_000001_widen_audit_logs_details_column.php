<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs.details was a VARCHAR(500) — fine when this table was created, but
 * CompanyController::updateFeatures() logs one line per Feature::ALL entry
 * ("key → on/off, key → on/off, ..."), and that list has grown well past the
 * point where a "set everything" message fits in 500 characters (confirmed in
 * production: a real MySQL insert failed with "Data too long for column
 * 'details'"). SQLite (this project's dev/test DB) never enforces a VARCHAR's
 * declared length at all, so this was invisible here the whole time.
 *
 * Uses a raw ALTER TABLE rather than Schema::table(...)->change() — this
 * project does not have doctrine/dbal installed, which Laravel's fluent
 * column-change() requires; a raw statement needs no such dependency.
 * SQLite has no real equivalent column-widening statement and, per the
 * above, no length to actually fix there anyway, so this is a no-op on
 * every connection except MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE audit_logs MODIFY details TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE audit_logs MODIFY details VARCHAR(500) NULL');
        }
    }
};
