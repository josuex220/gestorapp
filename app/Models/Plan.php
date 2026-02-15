<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'base_price',
        'cycle',
        'custom_days',
        'category',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'custom_days' => 'integer',
        'is_active' => 'boolean',
    ];

    const CYCLES = [
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
        'custom' => 'Personalizado',
    ];

    const CYCLE_DAYS = [
        'monthly' => 30,
        'quarterly' => 90,
        'semiannual' => 180,
        'annual' => 365,
    ];

    const CATEGORIES = [
        'consultoria' => 'Consultoria',
        'design' => 'Design',
        'desenvolvimento' => 'Desenvolvimento',
        'marketing' => 'Marketing',
        'suporte' => 'Suporte',
        'treinamento' => 'Treinamento',
        'outros' => 'Outros',
    ];

    // ==================== RELACIONAMENTOS ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ==================== SCOPES ====================

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByCycle(Builder $query, string $cycle): Builder
    {
        return $query->where('cycle', $cycle);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopePriceRange(Builder $query, ?float $min, ?float $max): Builder
    {
        if ($min !== null) {
            $query->where('base_price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('base_price', '<=', $max);
        }
        return $query;
    }

    // ==================== ACCESSORS ====================

    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->base_price, 2, ',', '.');
    }

    public function getCycleLabelAttribute(): string
    {
        return self::CYCLES[$this->cycle] ?? $this->cycle;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getCycleDaysAttribute(): int
    {
        if ($this->cycle === 'custom') {
            return $this->custom_days ?? 30;
        }
        return self::CYCLE_DAYS[$this->cycle] ?? 30;
    }

    public function getMonthlyEquivalentAttribute(): float
    {
        $days = $this->cycle_days;
        return round(($this->base_price / $days) * 30, 2);
    }

    public function getActiveSubscriptionsCountAttribute(): int
    {
        return $this->subscriptions()->where('status', 'active')->count();
    }

    public function getMrrAttribute(): float
    {
        return (float) $this->subscriptions()
            ->where('status', 'active')
            ->sum('amount') * (30 / $this->cycle_days);
    }

    // ==================== MÉTODOS ====================

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function canBeDeleted(): bool
    {
        return $this->subscriptions()->where('status', 'active')->count() === 0;
    }

    public function calculateNextBillingDate(\DateTime $fromDate = null): \DateTime
    {
        $from = $fromDate ?? now();
        return $from->copy()->addDays($this->cycle_days);
    }

    // ==================== MÉTODOS ESTÁTICOS ====================

    public static function getTotalMrrForUser(int $userId): float
    {
        $plans = self::byUser($userId)->active()->with(['subscriptions' => function ($q) {
            $q->where('status', 'active');
        }])->get();

        $mrr = 0;
        foreach ($plans as $plan) {
            foreach ($plan->subscriptions as $subscription) {
                $mrr += ($subscription->amount / $plan->cycle_days) * 30;
            }
        }

        return round($mrr, 2);
    }
}
