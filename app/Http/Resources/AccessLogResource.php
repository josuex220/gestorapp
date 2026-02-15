<?php
// app/Http/Resources/AccessLogResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'action_label' => $this->action_label,
            'action_icon' => $this->action_icon,
            'description' => $this->description,
            'device' => $this->device_label,
            'device_type' => $this->device_type,
            'browser' => $this->browser,
            'platform' => $this->platform,
            'ip_address' => $this->ip_address,
            'location' => $this->location ?? 'Desconhecido',
            'status' => $this->status,
            'status_label' => $this->status_label,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toISOString(),
            'created_at_formatted' => $this->created_at_formatted,
            'created_at_ago' => $this->created_at_ago,
        ];
    }
}
