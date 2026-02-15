<?php
// app/Http/Resources/SessionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device' => $this->device_label,
            'device_type' => $this->device_type,
            'device_type_label' => $this->device_type_label,
            'browser' => $this->browser,
            'platform' => $this->platform,
            'ip_address' => $this->ip_address,
            'location' => $this->location ?? 'Desconhecido',
            'is_current' => $this->is_current,
            'last_active_at' => $this->last_active_at?->toISOString(),
            'last_active_ago' => $this->last_active_ago,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
