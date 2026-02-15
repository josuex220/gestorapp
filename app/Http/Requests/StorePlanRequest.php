<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Plan;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'base_price' => 'required|numeric|min:0',
            'cycle' => 'required|in:' . implode(',', array_keys(Plan::CYCLES)),
            'custom_days' => 'nullable|integer|min:1|required_if:cycle,custom',
            'category' => 'required|in:' . implode(',', array_keys(Plan::CATEGORIES)),
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome e obrigatorio',
            'name.max' => 'O nome deve ter no maximo 255 caracteres',
            'base_price.required' => 'O preco base e obrigatorio',
            'base_price.numeric' => 'O preco deve ser um numero valido',
            'base_price.min' => 'O preco nao pode ser negativo',
            'cycle.required' => 'O ciclo de cobranca e obrigatorio',
            'cycle.in' => 'Ciclo de cobranca invalido',
            'custom_days.required_if' => 'Informe a quantidade de dias para ciclo personalizado',
            'custom_days.min' => 'A quantidade de dias deve ser maior que zero',
            'category.required' => 'A categoria e obrigatoria',
            'category.in' => 'Categoria invalida',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->is_active ?? true,
        ]);
    }
}
