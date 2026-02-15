<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'document' => $this->document,
            'company' => $this->company,
            'address' => $this->address,
            'notes' => $this->notes,
            'tags' => $this->tags ?? [],
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Accessors computados
            'total_billed' => $this->total_billed,
            'total_paid' => $this->total_paid,
            'pending_charges_count' => $this->pending_charges_count,
            'pending_amount' => $this->pending_amount,
            'overdue_amount' => $this->overdue_amount,
            'active_subscriptions_count' => $this->active_subscriptions_count,
            'formatted_document' => $this->formatted_document,
            'total_charges_billed' => $this->total_charges_billed,
            'total_subscriptions_billed' => $this->total_subscriptions_billed,

        ];
    }
}
