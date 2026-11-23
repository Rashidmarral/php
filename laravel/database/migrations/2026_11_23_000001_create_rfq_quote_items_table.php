<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Line-item breakdown of one supplier's quote, for real bid leveling (compare
        // suppliers item-by-item, not just lump total_amount). Optional: a quote with
        // no rows here is a plain lump-sum quote, unchanged from before this table
        // existed. Same loose, no-FK-constraint convention as rfq_items.rfq_id /
        // rfq_quotes.rfq_id — and, like AsphaltEstimateSegment, these rows are always
        // deleted and recreated wholesale on resubmission, never edited in place, so
        // there's no updated_at.
        Schema::create('rfq_quote_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rfq_quote_id')->index();
            $table->unsignedBigInteger('rfq_item_id')->index();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 12, 2);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_quote_items');
    }
};
