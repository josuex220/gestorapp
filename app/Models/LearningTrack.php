<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningTrack extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'thumbnail_url',
        'category',
        'level',
        'is_published',
        'estimated_duration_minutes',
        'tags',
        'prerequisites',
        'order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'tags' => 'array',
        'prerequisites' => 'array',
        'order' => 'integer',
        'estimated_duration_minutes' => 'integer',
    ];

    protected $attributes = [
        'is_published' => false,
        'order' => 0,
        'level' => 'iniciante',
    ];

    public function lessons(): HasMany
    {
        return $this->hasMany(LearningLesson::class, 'track_id')->orderBy('order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
