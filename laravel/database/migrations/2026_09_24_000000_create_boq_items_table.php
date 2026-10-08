<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project's Bill of Quantities: the fixed list of contract line items
 * (qty x contract unit rate = that line's contract value) that Payment
 * Certificates later claim cumulative progress against. See
 * PaymentCertificateController for the certificate cycle built on top of
 * this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('section_title', 255)->nullable();
            $table->string('section_title_ar', 255)->nullable();
            $table->string('item_number', 30)->nullable();
            $table->string('description', 500);
            $table->string('description_ar', 500)->nullable();
            $table->string('uom', 30);
            $table->decimal('qty', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boq_items');
    }
};
