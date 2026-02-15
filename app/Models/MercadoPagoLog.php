<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class MercadoPagoLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'charge_id',
        'payment_id',
        'mp_payment_id',
        'request_payload',
        'response_payload',
        'error_message',
        'ip_address',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    // Scopes
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForCharge(Builder $query, string $chargeId): Builder
    {
        return $query->where('charge_id', $chargeId);
    }

    // Helper: criar log de forma pratica
    public static function record(
        int $userId,
        string $type,
        string $status,
        array $data = []
    ): self {
        return self::create(array_merge([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
            'ip_address' => request()->ip(),
        ], $data));
    }
}
