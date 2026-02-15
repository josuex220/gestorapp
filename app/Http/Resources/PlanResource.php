<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'description' => $this->description,
            'base_price' => (float) $this->base_price,
            'cycle' => $this->cycle,
            'custom_days' => $this->custom_days,
            'category' => $this->category,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'formatted_price' => $this->formatted_price,
            'cycle_label' => $this->cycle_label,
            'category_label' => $this->category_label,
            'cycle_days' => $this->cycle_days,
            'monthly_equivalent' => $this->monthly_equivalent,
            'active_subscriptions_count' => $this->active_subscriptions_count,
            'mrr' => $this->mrr,
            'can_be_deleted' => $this->canBeDeleted(),
        ];
    }
}
