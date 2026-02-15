<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'categories',
        'auto_reminders',
        'reminders',
        'reminder_send_time',
        'notification_channels',
        'notification_preferences',
        'theme',
        'color_scheme',
    ];

    protected $casts = [
        'categories' => 'array',
        'auto_reminders' => 'boolean',
        'reminders' => 'array',
        'notification_channels' => 'array',
        'notification_preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
