<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purely additive: lets a supplier confirm receipt of an issued PO through the supplier
 * portal without touching the existing `status` enum/workflow at all (no new status value
 * — that would risk breaking existing code elsewhere that checks status === 'issued'/etc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
        });
    }
};
