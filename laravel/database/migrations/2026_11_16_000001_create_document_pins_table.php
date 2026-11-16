<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_pins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            // x/y are stored as a PERCENTAGE (0-100) of the image's natural pixel
            // dimensions, not raw pixels — computed client-side as
            // canvasPoint(e).x / canvas.width * 100 (the exact canvasPoint() mechanic
            // takeoffs/show.blade.php already uses), so a pin still lands on the right
            // spot however the image ends up being displayed/rendered.
            $table->decimal('x', 6, 3);
            $table->decimal('y', 6, 3);
            // Standard Eloquent morphTo pair — the record this pin is attached to:
            // currently either a PunchListItem or an Rfi.
            $table->string('pinnable_type', 100);
            $table->unsignedBigInteger('pinnable_id');
            $table->unsignedBigInteger('created_by')->nullable();
            // Short caption shown on hover/click, auto-filled from the linked item's
            // title/number when the user leaves it blank.
            $table->string('label', 150)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['pinnable_type', 'pinnable_id']);
            $table->index(['document_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_pins');
    }
};
