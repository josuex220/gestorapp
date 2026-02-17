<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPixConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'key_type',
        'key_value',
        'holder_name',
        'require_proof',
        'proof_required',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'require_proof' => 'boolean',
        'proof_required' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mask the PIX key for display (show first/last chars only).
     */
    public function getMaskedKeyValueAttribute(): string
    {
        $val = $this->key_value;
        if (empty($val) || strlen($val) <= 6) return $val;

        return substr($val, 0, 3) . str_repeat('*', strlen($val) - 6) . substr($val, -3);
    }
}
