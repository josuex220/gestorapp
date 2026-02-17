<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'plan' => $this->platformPlan?->name ?? 'Sem plano',
            'plan_id' => $this->platform_plan_id,
            'status' => $this->status ?? 'active',
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'total_charges' => $this->charges()->count(),
            'total_revenue' => (float) ($this->charges()->where('status', 'paid')->sum('amount') ?? 0),
        ];
    }
}
