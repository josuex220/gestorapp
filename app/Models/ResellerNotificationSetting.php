<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerNotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'alert_days',
        'channels',
    ];

    protected $casts = [
        'enabled'    => 'boolean',
        'alert_days' => 'array',
        'channels'   => 'array',
    ];

    /**
     * Default settings for new resellers.
     */
    public static function defaults(): array
    {
        return [
            'enabled'    => true,
            'alert_days' => [3, 7, 30],
            'channels'   => ['email' => true, 'whatsapp' => false],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
