<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps when a punch-list item was raised as a warranty claim (PunchListItem::STATUSES'
 * new 'warranty_claim' entry) — mirrors the existing resolved_at column's shape, just for
 * the defects-liability-period handoff instead of the ordinary resolve action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punch_list_items', function (Blueprint $table) {
            $table->timestamp('warranty_claim_raised_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('punch_list_items', function (Blueprint $table) {
            $table->dropColumn('warranty_claim_raised_at');
        });
    }
};
