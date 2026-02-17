<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonProgress extends Model
{
    use HasUuids;

    protected $table = 'user_lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'track_id',
        'watched_seconds',
        'total_seconds',
        'is_completed',
        'completed_at',
        'last_watched_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'watched_seconds' => 'integer',
        'total_seconds' => 'integer',
        'completed_at' => 'datetime',
        'last_watched_at' => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(LearningLesson::class, 'lesson_id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(LearningTrack::class, 'track_id');
    }
}
