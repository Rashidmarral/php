<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            // 'platform' = company staff -> BuildXact admin. 'company' = portal client -> the company itself.
            $table->string('channel', 10)->default('platform');
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('opened_by_user_id')->nullable();
            $table->string('subject', 200);
            $table->string('category', 30)->default('general');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 15)->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->index(['channel', 'status']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->string('sender_type', 10);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_name', 150);
            $table->text('message');
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 150)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 150);
            $table->string('title_ar', 150)->nullable();
            $table->string('issuer_en', 150)->nullable();
            $table->string('issuer_ar', 150)->nullable();
            $table->string('image_path', 255);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
