<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #54: an optional, per-company, per-document-type sequential approval
 * chain layered on top of the existing single-step
 * require_estimate_approval/require_invoice_approval toggles (see the
 * companies table migration). A company with zero rows here for a
 * document_type has no chain configured at all, and every Estimate/Invoice
 * of that type keeps the original single `approve_documents` Gate check
 * unchanged — see App\Support\ApprovalChain's own docblock for how that
 * backward compatibility is preserved.
 *
 * role_required is deliberately restricted (at the application layer, see
 * App\Models\ApprovalChainStep::ROLES) to 'admin'/'accountant'/'owner' —
 * the only roles that ever had approval rights. 'estimator'/'viewer' never
 * did (see AppServiceProvider's 'approve_documents' Gate) and are not valid
 * chain steps either.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_chain_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 20);
            $table->unsignedTinyInteger('step_order');
            $table->string('role_required', 20);
            // Optional custom display name for this step (e.g. a company calls
            // its accountant step "Finance Review") — falls back to a
            // role-derived default label when blank, see
            // ApprovalChainStep::displayLabel().
            $table->string('label')->nullable();
            $table->string('label_ar')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'step_order'], 'approval_chain_steps_company_doc_order_unique');
            $table->index(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chain_steps');
    }
};
