<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the per-line detail a real ZATCA-style tax invoice itemizes that
     * this table never carried: an item code/ID, a unit of measure, a
     * per-line discount, and the ZATCA tax category code the line is taxed
     * under (cbc:TaxCategory/cbc:ID in the UBL XML this app's ZatcaXmlGenerator
     * already emits — see that class's own docblock on why only a single
     * Standard-rated category exists today).
     *
     * tax_code defaults to, and is always written as, 'S' (standard-rated) —
     * InvoiceController::store() never reads it from the request, and no
     * form in this app exposes a picker for it. This app has deliberately
     * not built multi-rate/zero-rated/exempt VAT support; the column exists
     * purely so the PDF's items table and VAT-summary can render ZATCA's own
     * tax-code column the way a real invoice does, grouped from this one
     * real value instead of a hardcoded display string, so it stays correct
     * if multi-rate support is ever added later.
     *
     * item_code/unit/discount_amount are nullable/zero-defaulted — an
     * existing line (or one a form submission leaves blank) renders exactly
     * as before this migration.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('item_code', 50)->nullable()->after('description_ar');
            $table->string('unit', 20)->nullable()->after('qty');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('unit_price');
            $table->string('tax_code', 1)->default('S')->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['item_code', 'unit', 'discount_amount', 'tax_code']);
        });
    }
};
