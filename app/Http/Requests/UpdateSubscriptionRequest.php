<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Subscription;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => 'sometimes|nullable|uuid|exists:plans,id',
            'plan_name' => 'sometimes|string|max:255',
            'plan_category' => 'sometimes|nullable|in:' . implode(',', array_keys(Subscription::CATEGORIES)),
            'amount' => 'sometimes|numeric|min:0.01',
            'cycle' => 'sometimes|in:' . implode(',', array_keys(Subscription::CYCLES)),
            'custom_days' => 'nullable|integer|min:1|max:365',
            'reminder_days' => 'sometimes|integer|min:0|max:30',
        ];
    }
}
