<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SavedCard extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'brand',
        'last_four_digits',
        'holder_name',
        'expiry_month',
        'expiry_year',
        'is_default',
        'token', // Token do gateway de pagamento
    ];

    protected $casts = [
        'expiry_month' => 'integer',
        'expiry_year' => 'integer',
        'is_default' => 'boolean',
    ];

    protected $hidden = [
        'token',
    ];

    const BRANDS = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'elo' => 'Elo',
        'amex' => 'American Express',
        'hipercard' => 'Hipercard',
    ];

    // ==================== RELACIONAMENTOS ====================

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    // ==================== SCOPES ====================

    public function scopeByClient(Builder $query, string $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        return $query->where(function ($q) use ($currentYear, $currentMonth) {
            $q->where('expiry_year', '>', $currentYear)
              ->orWhere(function ($q2) use ($currentYear, $currentMonth) {
                  $q2->where('expiry_year', $currentYear)
                     ->where('expiry_month', '>=', $currentMonth);
              });
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        return $query->where(function ($q) use ($currentYear, $currentMonth) {
            $q->where('expiry_year', '<', $currentYear)
              ->orWhere(function ($q2) use ($currentYear, $currentMonth) {
                  $q2->where('expiry_year', $currentYear)
                     ->where('expiry_month', '<', $currentMonth);
              });
        });
    }

    public function scopeByBrand(Builder $query, string $brand): Builder
    {
        return $query->where('brand', $brand);
    }

    // ==================== ACCESSORS ====================

    public function getMaskedNumberAttribute(): string
    {
        return "**** **** **** {$this->last_four_digits}";
    }

    public function getExpiryDateAttribute(): string
    {
        return sprintf('%02d/%d', $this->expiry_month, $this->expiry_year);
    }

    public function getBrandLabelAttribute(): string
    {
        return self::BRANDS[$this->brand] ?? $this->brand;
    }

    public function getIsExpiredAttribute(): bool
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        if ($this->expiry_year < $currentYear) {
            return true;
        }

        if ($this->expiry_year === $currentYear && $this->expiry_month < $currentMonth) {
            return true;
        }

        return false;
    }

    public function getExpiresInMonthsAttribute(): int
    {
        $now = now();
        $expiry = now()->setYear($this->expiry_year)->setMonth($this->expiry_month)->endOfMonth();

        return max(0, $now->diffInMonths($expiry, false));
    }

    // ==================== MÉTODOS ====================

    public function setAsDefault(): bool
    {
        // Remove default de outros cartões do mesmo cliente
        self::where('client_id', $this->client_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        return $this->update(['is_default' => true]);
    }

    public function isValidForCharge(): bool
    {
        return !$this->is_expired && $this->token !== null;
    }

    // ==================== BOOT ====================

    protected static function boot()
    {
        parent::boot();

        // Se é o primeiro cartão do cliente, torna-o default
        static::creating(function ($card) {
            $existingCards = self::where('client_id', $card->client_id)->count();
            if ($existingCards === 0) {
                $card->is_default = true;
            }
        });

        // Se remover o cartão default, promove outro
        static::deleted(function ($card) {
            if ($card->is_default) {
                $nextCard = self::where('client_id', $card->client_id)->first();
                $nextCard?->update(['is_default' => true]);
            }
        });
    }
}
