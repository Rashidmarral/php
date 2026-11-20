<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTaskDependency extends Model
{
    protected $table = 'schedule_task_dependencies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'lag_days' => 'integer',
        ];
    }

    /** The dependent/successor task — can't start until `dependsOn` finishes (+ lag_days). */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ScheduleTask::class, 'task_id');
    }

    /** The predecessor task that must finish first. */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(ScheduleTask::class, 'depends_on_task_id');
    }
}
