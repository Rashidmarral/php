<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Traces a generated invoice back to the Payment Certificate it was certified from, if any — null for every other invoice-creation path. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('source_payment_certificate_id')->nullable()->index()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('source_payment_certificate_id');
        });
    }
};
