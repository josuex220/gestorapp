<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'charge_id' => $this->charge_id,
            'subscription_id' => $this->subscription_id,
            'plan_id' => $this->plan_id,
            'plan_category' => $this->plan_category,
            'client_name' => $this->client?->name,
            'client_email' => $this->client?->email,
            'amount' => (float) $this->amount,
            'fee' => (float) $this->fee,
            'net_amount' => (float) $this->net_amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'description' => $this->description,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'refunded_at' => $this->refunded_at?->toISOString(),

            // Accessors computados
            'formatted_amount' => $this->formatted_amount,
            'formatted_net_amount' => $this->formatted_net_amount,
            'payment_method_label' => $this->payment_method_label,
            'status_label' => $this->status_label,
            'category_label' => $this->category_label,
        ];
    }
}
