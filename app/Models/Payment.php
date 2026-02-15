<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'client_id',
        'charge_id',
        'subscription_id',
        'plan_id',
        'amount',
        'fee',
        'net_amount',
        'payment_method',
        'status',
        'description',
        'transaction_id',
        'completed_at',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'refunded_at' => 'datetime',
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

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', ['completed', 'paid']);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }


    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', 'refunded');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod(Builder $query, string $method): Builder
    {
        return $query->where('payment_method', $method);
    }

    public function scopeCreatedBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeCompletedBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('completed_at', [$startDate, $endDate]);
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    public function scopeLastMonth(Builder $query): Builder
    {
        $lastMonth = now()->subMonth();
        return $query->whereMonth('created_at', $lastMonth->month)
                     ->whereYear('created_at', $lastMonth->year);
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeFromSubscription(Builder $query): Builder
    {
        return $query->whereNotNull('subscription_id');
    }

    public function scopeFromCharge(Builder $query): Builder
    {
        return $query->whereNotNull('charge_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
              ->orWhere('transaction_id', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('name', 'like', "%{$search}%");
              });
        });
    }

    // ==================== ACCESSORS ====================

    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    public function getFormattedFeeAttribute(): string
    {
        return 'R$ ' . number_format($this->fee, 2, ',', '.');
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->net_amount, 2, ',', '.');
    }

    public function getFeePercentageAttribute(): float
    {
        if ($this->amount == 0) return 0;
        return round(($this->fee / $this->amount) * 100, 2);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        $labels = [
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'credit_card' => 'Cartão de Crédito',
            'debit_card' => 'Cartão de Débito',
            'transfer' => 'Transferência',
        ];

        return $labels[$this->payment_method] ?? $this->payment_method;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'completed' => 'Concluído',
            'pending' => 'Pendente',
            'failed' => 'Falhou',
            'refunded' => 'Reembolsado',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getPlanCategoryAttribute(): ?string
    {
        return $this->plan?->category;
    }

    // ==================== MÉTODOS ====================

    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): bool
    {
        return $this->update(['status' => 'failed']);
    }

    public function refund(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);
    }

    // ==================== MÉTODOS ESTÁTICOS PARA AGREGAÇÕES ====================

    public static function getSummaryForUser(int $userId): array
    {
        $payments = self::byUser($userId);

        $completed = (clone $payments)->completed();
        $pending = (clone $payments)->pending();
        $refunded = (clone $payments)->refunded();

        $totalReceived = $completed->sum('net_amount');
        $totalPending = $pending->sum('amount');
        $totalFees = $completed->sum('fee');
        $totalRefunded = $refunded->sum('amount');
        $transactionsCount = $completed->count();

        // Crescimento em relação ao mês anterior
        $thisMonthTotal = self::byUser($userId)->completed()->thisMonth()->sum('net_amount');
        $lastMonthTotal = self::byUser($userId)->completed()->lastMonth()->sum('net_amount');
        $growthPercentage = $lastMonthTotal > 0
            ? round((($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1)
            : 0;

        return [
            'total_received' => (float) $totalReceived,
            'total_pending' => (float) $totalPending,
            'total_fees' => (float) $totalFees,
            'total_refunded' => (float) $totalRefunded,
            'transactions_count' => $transactionsCount,
            'average_ticket' => $transactionsCount > 0 ? round($totalReceived / $transactionsCount, 2) : 0,
            'growth_percentage' => $growthPercentage,
        ];
    }

    public static function getMonthlyRevenueForUser(int $userId, int $months = 6): array
    {
        $result = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthName = $date->translatedFormat('M');

            $received = self::byUser($userId)
                ->completed()
                ->whereMonth('completed_at', $date->month)
                ->whereYear('completed_at', $date->year)
                ->sum('net_amount');

            $pending = self::byUser($userId)
                ->pending()
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('amount');

            $result[] = [
                'month' => $monthName,
                'received' => (float) $received,
                'pending' => (float) $pending,
            ];
        }

        return $result;
    }
}
