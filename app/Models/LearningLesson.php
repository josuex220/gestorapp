<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningLesson extends Model
{
    use HasUuids;

    protected $fillable = [
        'track_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'duration_seconds',
        'is_published',
        'order',
        'attachments',
        'quiz',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'order' => 'integer',
        'duration_seconds' => 'integer',
        'attachments' => 'array',
        'quiz' => 'array',
    ];

    protected $attributes = [
        'is_published' => true,
        'order' => 0,
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(LearningTrack::class, 'track_id');
    }
}
