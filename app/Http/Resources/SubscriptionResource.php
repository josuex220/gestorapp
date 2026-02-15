<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'plan_id' => $this->plan_id,
            'client_name' => $this->client?->name,
            'client_email' => $this->client?->email,
            'plan_name' => $this->plan_name ?? $this->plan?->name,
            'plan_category' => $this->plan_category ?? $this->plan?->category,
            'amount' => (float) $this->amount,
            'cycle' => $this->cycle,
            'custom_days' => $this->custom_days,
            'reminder_days' => $this->reminder_days,
            'status' => $this->status,
            'start_date' => $this->start_date->toDateString(),
            'next_billing_date' => $this->next_billing_date->toDateString(),
            'last_payment_date' => $this->last_payment_date?->toDateString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'suspended_at' => $this->suspended_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),

            // Accessors computados
            'formatted_amount' => $this->formatted_amount,
            'cycle_label' => $this->cycle_label,
            'status_label' => $this->status_label,
            'is_due_soon' => $this->is_due_soon,
            'days_until_billing' => $this->days_until_billing,
            'monthly_equivalent' => $this->monthly_equivalent,
        ];
    }
}
