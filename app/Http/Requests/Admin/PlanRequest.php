<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|string|in:mensal,trimestral,semestral,anual',
            'features' => 'nullable|string',
            'active' => 'sometimes|boolean',
            'privileges' => 'required|array',
            'privileges.max_clients' => 'nullable|integer|min:1',
            'privileges.max_charges_per_month' => 'nullable|integer|min:1',
            'privileges.notification_channels' => 'required|array',
            'privileges.notification_channels.email' => 'required|boolean',
            'privileges.notification_channels.whatsapp' => 'required|boolean',
            'privileges.notification_channels.telegram' => 'required|boolean',
            'privileges.reports_access' => 'required|string|in:basic,advanced',
            'privileges.api_access' => 'required|boolean',
            'privileges.dedicated_support' => 'required|boolean',
            'privileges.custom_branding' => 'required|boolean',
            'privileges.has_trial' => 'required|boolean',
            'privileges.trial_days' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do plano é obrigatório.',
            'price.required' => 'O preço é obrigatório.',
            'price.min' => 'O preço deve ser maior ou igual a zero.',
            'interval.required' => 'O intervalo de cobrança é obrigatório.',
            'interval.in' => 'O intervalo deve ser mensal, trimestral, semestral ou anual.',
        ];
    }
}
