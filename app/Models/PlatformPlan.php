<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'price',
        'interval',
        'features',
        'privileges',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'privileges' => 'array',
        'active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'platform_plan_id');
    }

    public function getSubscribersCountAttribute(): int
    {
        return $this->users()->where('status', 'active')->count();
    }
}
