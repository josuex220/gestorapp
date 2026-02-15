<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Subscription;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|uuid|exists:clients,id',
            'plan_id' => 'nullable|uuid|exists:plans,id',
            'plan_name' => 'required_without:plan_id|string|max:255',
            'plan_category' => 'nullable|in:' . implode(',', array_keys(Subscription::CATEGORIES)),
            'amount' => 'required|numeric|min:0.01',
            'cycle' => 'required|in:' . implode(',', array_keys(Subscription::CYCLES)),
            'custom_days' => 'required_if:cycle,custom|nullable|integer|min:1|max:365',
            'reminder_days' => 'nullable|integer|min:0|max:30',
            'start_date' => 'required|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Selecione um cliente',
            'client_id.exists' => 'Cliente não encontrado',
            'plan_name.required_without' => 'Informe o nome do plano ou selecione um plano existente',
            'amount.required' => 'O valor é obrigatório',
            'amount.min' => 'O valor mínimo é R$ 0,01',
            'cycle.required' => 'O ciclo de cobrança é obrigatório',
            'cycle.in' => 'Ciclo de cobrança inválido',
            'custom_days.required_if' => 'Informe o número de dias para ciclo personalizado',
            'start_date.required' => 'A data de início é obrigatória',
            'start_date.after_or_equal' => 'A data de início deve ser hoje ou futura',
        ];
    }
}
