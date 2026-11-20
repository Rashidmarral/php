<?php

namespace App\Models;

class Tender extends Model
{
    public $timestamps = true;

    /** Government procurement notices vs. giga-project and private-developer opportunities. */
    public const TYPES = [
        'government' => 'Government Procurement',
        'giga_project' => 'Giga-Project',
        'private_developer' => 'Private Developer',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'estimated_value_sar' => 'decimal:2',
            'submission_deadline' => 'date:Y-m-d',
        ];
    }

    public function isExpired(): bool
    {
        return $this->submission_deadline !== null && $this->submission_deadline->isPast();
    }
}
