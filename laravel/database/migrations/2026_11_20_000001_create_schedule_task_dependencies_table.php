<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finish-to-Start task dependencies for the Schedule/Gantt tool (task_id can't
 * start until depends_on_task_id finishes, plus an optional lag_days). Same
 * loose-coupling style as the rest of this app (assemblies, cost_codes, etc.) —
 * no FK constraints; ScheduleController enforces company/project ownership and
 * cycle-freedom in PHP before a row is ever written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('depends_on_task_id')->index();
            $table->integer('lag_days')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_task_dependencies');
    }
};
