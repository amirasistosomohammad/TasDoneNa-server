<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccomplishmentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'status',
        'noted_by',
        'noted_at',
        'tasks_summary',
        'remarks',
        'admin_remarks',
    ];

    protected function casts(): array
    {
        return [
            'tasks_summary' => 'array',
            'noted_at' => 'datetime',
            'year' => 'integer',
            'month' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'noted_by');
    }
}
