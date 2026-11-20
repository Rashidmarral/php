<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-worker fields Saudi Arabia's Wage Protection System (WPS,
 * commonly filed through Mudad or a bank's own portal) needs to build a
 * salary file each pay period: the worker's national ID/iqama number and
 * nationality, their bank IBAN/name for wage disbursement, and the salary
 * breakdown (basic wage, housing allowance, other earnings) WPS files
 * report as separate columns. All nullable — these are being added to
 * team members that already exist, and payroll data is filled in
 * retroactively rather than required up front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id', 20)->nullable()->after('role');
            $table->string('nationality', 100)->nullable()->after('national_id');
            $table->string('bank_iban', 34)->nullable()->after('nationality');
            $table->string('bank_name', 150)->nullable()->after('bank_iban');
            $table->decimal('basic_salary', 12, 2)->nullable()->after('bank_name');
            $table->decimal('housing_allowance', 12, 2)->nullable()->after('basic_salary');
            $table->decimal('other_earnings', 12, 2)->nullable()->after('housing_allowance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'national_id', 'nationality', 'bank_iban', 'bank_name',
                'basic_salary', 'housing_allowance', 'other_earnings',
            ]);
        });
    }
};
