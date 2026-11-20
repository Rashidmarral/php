<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #54: per-document progress through a company's configured
 * approval_chain_steps, snapshotted onto the document the moment it enters
 * approval_status='pending' (see App\Support\ApprovalChain::startIfChained()).
 * One row per configured step, all 'pending' initially.
 *
 * Deliberately separate from the estimates/invoices tables' own flat
 * approval_status/approved_by/approved_at columns, which must keep working
 * UNCHANGED for the common single-step case — a document with no chain
 * configured for its document_type never gets any rows here at all. Those
 * flat columns remain the single source of truth every other reader in the
 * app already relies on (isApprovalBlocked(), etc.): 'pending' while any
 * step here is still pending, 'approved' only once the last step approves,
 * 'rejected' immediately if any step rejects — see
 * App\Support\ApprovalChain::approve()/reject().
 *
 * document_id is a plain integer, not a foreign key, since it points at one
 * of two different tables (estimates/invoices) depending on document_type —
 * the same "polymorphic by a type column, not a real polymorphic relation"
 * approach already used by this app's Document model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_steps_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 20);
            $table->unsignedBigInteger('document_id');
            $table->unsignedTinyInteger('step_order');
            $table->string('role_required', 20);
            // 'skipped' is used for any step still pending at the moment an
            // earlier step rejects — the document is already dead at that
            // point, so the remaining steps are marked moot rather than left
            // looking like they're still awaiting action (see
            // App\Support\ApprovalChain::reject()'s own docblock).
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps_log');
    }
};
