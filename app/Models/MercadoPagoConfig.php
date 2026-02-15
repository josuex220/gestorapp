<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MercadoPagoConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'access_token',
        'public_key',
        'is_connected',
        'is_sandbox',
        'accepted_payment_methods',
        'accepted_brands',
        'max_installments',
        'statement_descriptor',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'is_sandbox' => 'boolean',
        'accepted_payment_methods' => 'array',
        'accepted_brands' => 'array',
        'max_installments' => 'integer',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $attributes = [
        'is_connected' => false,
        'is_sandbox' => true,
        'max_installments' => 12,
        'statement_descriptor' => 'COBGEST MAX',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessor: retorna token descriptografado
    public function getDecryptedAccessTokenAttribute(): ?string
    {
        return $this->access_token ? decrypt($this->access_token) : null;
    }

    // Mutator: armazena token criptografado
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    // Accessor: mascara o token para exibicao
    public function getMaskedAccessTokenAttribute(): ?string
    {
        $token = $this->decrypted_access_token;
        if (!$token) return null;
        return substr($token, 0, 12) . '...' . substr($token, -4);
    }
}
