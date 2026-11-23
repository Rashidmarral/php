<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            // Sequential per-project display number (INC-001, INC-002, ...) — same
            // convention as Rfi::rfi_number / Submittal::submittal_number.
            $table->integer('incident_number');
            $table->date('incident_date');
            // Free text with a suggested-options dropdown in the UI (Near Miss, First
            // Aid, Lost Time Injury, Property Damage, Other) — not a rigid enum, since
            // real-world incident classifications vary by company/site.
            $table->string('incident_type', 100);
            $table->string('severity', 10)->default('low');
            $table->text('description');
            $table->string('location', 150)->nullable();
            $table->string('injured_person_name', 150)->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->text('corrective_action')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('photo_path', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();
            $table->index(['project_id', 'incident_number']);
            $table->index(['project_id', 'status']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('toolbox_talks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id')->index();
            $table->date('talk_date');
            $table->string('topic', 200);
            $table->unsignedBigInteger('conducted_by')->nullable();
            $table->unsignedInteger('attendee_count')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['project_id', 'talk_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toolbox_talks');
        Schema::dropIfExists('safety_incidents');
    }
};
