<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History of individual 1-5 performance ratings, not a single overwritable number —
        // Supplier::averageRating() always computes live from these rows. project_id is nullable
        // (a general supplier rating isn't always tied to one project) but is set whenever the
        // rating comes from a Subcontract, since a Subcontract IS a Supplier hired under a
        // specific project: a subcontract's "own" rating is just a SupplierRating row with its
        // project_id set to the subcontract's project_id — see Subcontract::ratings()/averageRating().
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('rated_by')->nullable();
            $table->unsignedTinyInteger('score');
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
    }
};
