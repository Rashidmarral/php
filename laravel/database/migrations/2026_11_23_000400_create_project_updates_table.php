<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            // company_id denormalized alongside project_id, same convention as site_logs/change_orders.
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->string('title', 150)->nullable();
            $table->text('body');
            // 'manual' (typed from scratch or edited after an AI draft) or 'ai_draft' — purely
            // informational, never gates anything (same stance as every other AI feature here:
            // a human reviewing/editing before publish is what matters, not how it started).
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('created_by')->nullable();
            // Null = still a draft, staff-only. Set = published, visible in the client portal.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
    }
};
