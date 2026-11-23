<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rounds out the invoice header with the fields a real Saudi tax
     * invoice carries that this app never captured: how the client paid
     * (payment_method), ZATCA's "Date of Supply" — which can genuinely
     * differ from the invoice/issue date (created_at) for goods/services
     * delivered on another day — and a real header discount/other-charges
     * pair to replace InvoiceController::pdf()'s previous hardcoded
     * discountPercent/discountAmount = 0.
     *
     * All four are nullable/zero-defaulted additions — an invoice created
     * before this migration (or any row this request's form leaves blank)
     * keeps rendering exactly as it did before: see InvoiceController::pdf()
     * and the PDF templates' null-safe handling of each of these.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('status');
            $table->date('date_of_supply')->nullable()->after('due_date');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('vat_amount');
            $table->decimal('other_charges', 12, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'date_of_supply', 'discount_amount', 'other_charges']);
        });
    }
};
