<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'mfo',
        'kra',
        'kra_weight',
        'objective',
        'movs',
        'due_date',
        'cutoff_date',
        'assigned_to',
        'created_by',
        'status',
        'priority',
        'timeline_start',
        'timeline_end',
        'performance_criteria',
    ];

    protected function casts(): array
    {
        return [
            'movs' => 'array',
            'performance_criteria' => 'array',
            'due_date' => 'date',
            'cutoff_date' => 'date',
            'timeline_start' => 'date',
            'timeline_end' => 'date',
            'kra_weight' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
