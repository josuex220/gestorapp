<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Payment;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|uuid|exists:clients,id',
            'charge_id' => 'nullable|uuid|exists:charges,id',
            'subscription_id' => 'nullable|uuid|exists:subscriptions,id',
            'plan_id' => 'nullable|uuid|exists:plans,id',
            'plan_category' => 'nullable|in:' . implode(',', array_keys(Payment::CATEGORIES)),
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:' . implode(',', array_keys(Payment::PAYMENT_METHODS)),
            'description' => 'nullable|string|max:500',
            'status' => 'sometimes|in:' . implode(',', array_keys(Payment::STATUSES)),
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Selecione um cliente',
            'client_id.exists' => 'Cliente não encontrado',
            'amount.required' => 'O valor é obrigatório',
            'amount.min' => 'O valor mínimo é R$ 0,01',
            'payment_method.required' => 'O método de pagamento é obrigatório',
            'payment_method.in' => 'Método de pagamento inválido',
        ];
    }
}
