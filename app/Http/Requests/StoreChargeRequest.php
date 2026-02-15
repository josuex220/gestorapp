<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Charge;

class StoreChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|uuid|exists:clients,id',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date|after_or_equal:today',
            'payment_method' => 'required|in:' . implode(',', array_keys(Charge::PAYMENT_METHODS)),
            'description' => 'nullable|string|max:500',
            'notification_channels' => 'required|array|min:1',
            'notification_channels.*' => 'in:' . implode(',', array_keys(Charge::NOTIFICATION_CHANNELS)),
            'saved_card_id' => 'nullable|uuid|exists:saved_cards,id',
            'installments' => 'nullable|integer|min:1|max:12',
            'send_notification' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Selecione um cliente',
            'client_id.exists' => 'Cliente não encontrado',
            'amount.required' => 'O valor é obrigatório',
            'amount.numeric' => 'O valor deve ser um número válido',
            'amount.min' => 'O valor mínimo é R$ 0,01',
            'due_date.required' => 'A data de vencimento é obrigatória',
            'due_date.after_or_equal' => 'A data de vencimento deve ser hoje ou futura',
            'payment_method.required' => 'O método de pagamento é obrigatório',
            'payment_method.in' => 'Método de pagamento inválido',
            'notification_channels.required' => 'Selecione pelo menos um canal de notificação',
            'notification_channels.min' => 'Selecione pelo menos um canal de notificação',
        ];
    }
}
