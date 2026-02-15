<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->name,
            'client_email' => $this->client?->email,
            'client_phone' => $this->client?->phone,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date->toDateString(),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'description' => $this->description,
            'notification_channels' => $this->notification_channels,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'last_notification_at' => $this->last_notification_at?->toISOString(),
            'notification_count' => $this->notification_count,
            'saved_card_id' => $this->saved_card_id,
            'installments' => $this->installments,

            // Accessors computados
            'formatted_amount' => $this->formatted_amount,
            'is_overdue' => $this->is_overdue,
            'days_until_due' => $this->days_until_due,
            'days_overdue' => $this->days_overdue,
            'payment_method_label' => $this->payment_method_label,
            'status_label' => $this->status_label,
        ];
    }
}
