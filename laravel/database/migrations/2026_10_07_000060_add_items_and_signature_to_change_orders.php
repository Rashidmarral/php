<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enriches change_orders with (a) optional line items, (b) a lightweight
 * time-impact note, and (c) the same client-facing e-signature flow Estimate
 * and PaymentCertificate already have (share_token + signed_at/signed_by_name/
 * signature_data/signed_ip — see ShareController::signEstimate()/
 * signPaymentCertificate(), which this mirrors exactly for /co/{token}).
 *
 * change_order_items is additive and OPT-IN per change order: a change order
 * with zero rows here keeps behaving exactly as before (amount stays whatever
 * was typed in on creation) — see ChangeOrder::syncAmountFromItems()'s
 * docblock for the same "empty collection means nothing has opted in yet"
 * rule ApprovalChain::startIfChained() uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_orders', function (Blueprint $table) {
            $table->integer('time_impact_days')->nullable()->after('amount');
            $table->string('share_token', 64)->nullable()->unique()->after('approved_at');
            $table->timestamp('signed_at')->nullable()->after('share_token');
            $table->string('signed_by_name', 150)->nullable()->after('signed_at');
            $table->longText('signature_data')->nullable()->after('signed_by_name');
            $table->string('signed_ip', 45)->nullable()->after('signature_data');
        });

        Schema::create('change_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('change_order_id')->index();
            $table->string('description', 255);
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_order_items');
        Schema::table('change_orders', function (Blueprint $table) {
            $table->dropColumn(['time_impact_days', 'share_token', 'signed_at', 'signed_by_name', 'signature_data', 'signed_ip']);
        });
    }
};
