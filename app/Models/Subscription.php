<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'client_id',
        'plan_id',
        'plan_name',
        'plan_category',
        'amount',
        'cycle',
        'custom_days',
        'reminder_days',
        'status',
        'start_date',
        'next_billing_date',
        'last_payment_date',
        'suspended_at',
        'cancelled_at',
        'suspension_reason',
        'cancellation_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'next_billing_date' => 'date',
        'last_payment_date' => 'date',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'custom_days' => 'integer',
        'reminder_days' => 'integer',
    ];

    protected $attributes = [
        'status' => 'active',
        'reminder_days' => 3,
    ];

    const CYCLES = [
        'weekly' => 'Semanal',
        'biweekly' => 'Quinzenal',
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
        'custom' => 'Personalizado',
    ];

    const STATUSES = [
        'active' => 'Ativo',
        'suspended' => 'Suspenso',
        'cancelled' => 'Cancelado',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ==================== SCOPES ====================

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByCycle(Builder $query, string $cycle): Builder
    {
        return $query->where('cycle', $cycle);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('plan_name', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
              })
              ->orWhereHas('plan', function ($planQuery) use ($search) {
                  $planQuery->where('name', 'like', "%{$search}%");
              });
        });
    }

    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }
        return $query;
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query->active()
            ->whereDate('next_billing_date', '<=', now()->addDays($days))
            ->whereDate('next_billing_date', '>=', now());
    }

    // ==================== ACCESSORS ====================

    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    public function getCycleLabelAttribute(): string
    {
        return self::CYCLES[$this->cycle] ?? $this->cycle ?? 'Mensal';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status ?? 'Ativo';
    }

    public function getIsDueSoonAttribute(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        return $this->next_billing_date->between(now(), now()->addDays($this->reminder_days));
    }

    public function getDaysUntilBillingAttribute(): ?int
    {
        if ($this->status !== 'active') {
            return null;
        }
        return max(0, now()->diffInDays($this->next_billing_date, false));
    }

    /**
     * Calcula o valor mensal equivalente (para MRR)
     */
    public function getMonthlyEquivalentAttribute(): float
    {
        return match ($this->cycle) {
            'weekly' => $this->amount * 4.33,
            'biweekly' => $this->amount * 2.17,
            'monthly' => $this->amount,
            'quarterly' => $this->amount / 3,
            'semiannual' => $this->amount / 6,
            'annual' => $this->amount / 12,
            'custom' => $this->custom_days > 0
                ? ($this->amount * 30) / $this->custom_days
                : $this->amount,
            default => $this->amount,
        };
    }

    // ==================== MÉTODOS ====================

    public function suspend(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    public function reactivate(): bool
    {
        return $this->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    public function cancel(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function advanceBillingDate(): bool
    {
        $newDate = match ($this->cycle) {
            'weekly' => $this->next_billing_date->addWeek(),
            'biweekly' => $this->next_billing_date->addWeeks(2),
            'monthly' => $this->next_billing_date->addMonth(),
            'quarterly' => $this->next_billing_date->addMonths(3),
            'semiannual' => $this->next_billing_date->addMonths(6),
            'annual' => $this->next_billing_date->addYear(),
            'custom' => $this->next_billing_date->addDays($this->custom_days ?? 30),
            default => $this->next_billing_date->addMonth(),
        };

        return $this->update([
            'next_billing_date' => $newDate,
            'last_payment_date' => now(),
        ]);
    }
}
