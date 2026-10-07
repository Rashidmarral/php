<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Free-text trade/classification, not a rigid enum — Saudi contractors use varied
            // classification language ("Electrical", "Steel Fabrication", "General Contracting"...).
            $table->string('trade_category', 100)->nullable()->after('category');
            $table->string('cr_number', 50)->nullable()->after('trade_category');
            $table->string('vat_number', 50)->nullable()->after('cr_number');
            // Mirrors Company::contractor_classification's Grade 1-5 convention (SettingsController's
            // legal tab) so a supplier's own classification reads the same way as the company's.
            $table->string('classification_grade', 10)->nullable()->after('vat_number');
            $table->boolean('is_approved_vendor')->default(false)->after('classification_grade');
            $table->text('approved_vendor_notes')->nullable()->after('is_approved_vendor');
            // Denormalized for cheap list-view display only — never trusted as the source of truth;
            // Supplier::averageRating() always recomputes live from supplier_ratings (see that app's
            // "never trust a stale denormalized value when you can compute it" precedent, e.g.
            // Subcontract::cumulativePaid()). Kept here only so the index view can show/sort a rating
            // without joining/aggregating supplier_ratings on every page load.
            $table->decimal('rating', 3, 2)->nullable()->after('approved_vendor_notes');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'trade_category',
                'cr_number',
                'vat_number',
                'classification_grade',
                'is_approved_vendor',
                'approved_vendor_notes',
                'rating',
            ]);
        });
    }
};
