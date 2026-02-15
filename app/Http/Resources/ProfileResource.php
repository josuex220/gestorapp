<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'full_name' => $this->full_name,
            'email' => $this->user->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
