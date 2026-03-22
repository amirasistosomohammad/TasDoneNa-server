<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'action',
        'description',
        'ip_address',
        'actor_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function log(string $action, ?string $description = null, ?int $actorId = null, ?string $ipAddress = null, array $properties = []): self
    {
        return static::create([
            'action' => $action,
            'description' => $description,
            'actor_id' => $actorId,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'properties' => $properties,
        ]);
    }
}
