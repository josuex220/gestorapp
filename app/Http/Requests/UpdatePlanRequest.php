<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Plan;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_price' => 'sometimes|numeric|min:0',
            'cycle' => 'sometimes|in:' . implode(',', array_keys(Plan::CYCLES)),
            'custom_days' => [
                Rule::requiredIf(fn () => $this->input('cycle') === 'custom'),
                Rule::when(
                    $this->input('cycle') === 'custom',
                    ['integer', 'min:1'],
                    ['nullable']
                ),
            ],
            'category' => 'sometimes|in:' . implode(',', array_keys(Plan::CATEGORIES)),
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'O nome deve ter no maximo 255 caracteres',
            'base_price.numeric' => 'O preco deve ser um numero valido',
            'base_price.min' => 'O preco nao pode ser negativo',
            'cycle.in' => 'Ciclo de cobranca invalido',
            'custom_days.required_if' => 'Informe a quantidade de dias para ciclo personalizado',
            'custom_days.min' => 'A quantidade de dias deve ser maior que zero',
            'category.in' => 'Categoria invalida',
        ];
    }
}
