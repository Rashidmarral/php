<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zakat_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('period_end_date');
            // 'hijri' (2.5%) or 'gregorian' (2.5775% — the 365/354 day-count adjustment ZATCA
            // applies when a company's financial year runs on the Gregorian calendar).
            $table->string('rate_type', 20)->default('hijri');
            // The four figures the standard "net invested capital" Zakat base formula needs.
            // BuildXact has no general ledger to derive these from, so they're entered by hand
            // from the company's own accountant/audited financials — never auto-computed here.
            $table->decimal('equity_amount', 14, 2)->default(0);
            $table->decimal('long_term_liabilities', 14, 2)->default(0);
            $table->decimal('net_fixed_assets', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('zakat_base', 14, 2)->default(0);
            $table->decimal('zakat_due', 14, 2)->default(0);
            $table->string('notes', 2000)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_calculations');
    }
};
