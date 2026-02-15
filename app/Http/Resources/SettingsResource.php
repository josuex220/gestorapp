<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'categories' => $this->categories ?? [],
            'auto_reminders' => $this->auto_reminders ?? true,
            'reminders' => $this->reminders ?? [],
            'reminder_send_time' => $this->reminder_send_time ?? '09:00',
            'notification_channels' => $this->notification_channels ?? ['email' => true, 'push' => false, 'whatsapp' => false],
            'notification_preferences' => $this->notification_preferences ?? [],
            'theme' => $this->theme ?? 'system',
            'color_scheme' => $this->color_scheme ?? 'teal',
        ];
    }
}
