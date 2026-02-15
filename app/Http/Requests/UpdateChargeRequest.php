<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Charge;

class UpdateChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'sometimes|uuid|exists:clients,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'due_date' => 'sometimes|date',
            'payment_method' => 'sometimes|in:' . implode(',', array_keys(Charge::PAYMENT_METHODS)),
            'description' => 'nullable|string|max:500',
            'notification_channels' => 'sometimes|array|min:1',
            'notification_channels.*' => 'in:' . implode(',', array_keys(Charge::NOTIFICATION_CHANNELS)),
            'status' => 'sometimes|in:' . implode(',', array_keys(Charge::STATUSES)),
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'Cliente não encontrado',
            'amount.numeric' => 'O valor deve ser um número válido',
            'amount.min' => 'O valor mínimo é R$ 0,01',
            'payment_method.in' => 'Método de pagamento inválido',
            'notification_channels.min' => 'Selecione pelo menos um canal de notificação',
            'status.in' => 'Status inválido',
        ];
    }
}
