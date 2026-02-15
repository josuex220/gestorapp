<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMercadoPagoConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_sandbox' => 'sometimes|boolean',
            'accepted_payment_methods' => 'sometimes|array',
            'accepted_payment_methods.credit_card' => 'sometimes|boolean',
            'accepted_payment_methods.debit_card' => 'sometimes|boolean',
            'accepted_payment_methods.pix' => 'sometimes|boolean',
            'accepted_payment_methods.boleto' => 'sometimes|boolean',
            'accepted_brands' => 'sometimes|array',
            'accepted_brands.*' => 'string|in:visa,mastercard,elo,amex,hipercard',
            'max_installments' => 'sometimes|integer|min:1|max:12',
            'statement_descriptor' => 'sometimes|string|max:22',
        ];
    }
}
