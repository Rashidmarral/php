<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the company's chosen default PDF template — one of the 6 visual
 * layouts document.blade.php already knows how to render (see
 * App\Support\Pdf\PdfTemplateStyles). Every document-PDF action already lets
 * a user override the template for a single download via ?template=; this
 * column is only the fallback used when no override is given, replacing the
 * hardcoded 'modern' default (see Company::activeInvoiceTemplate()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('invoice_template', 20)->default('modern')->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('invoice_template');
        });
    }
};
