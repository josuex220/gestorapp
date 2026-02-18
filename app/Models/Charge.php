<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Charge extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'client_id',
        'subscription_id',
        'amount',
        'due_date',
        'payment_method',
        'status',
        'description',
        'notification_channels',
        'paid_at',
        'cancelled_at',
        'last_notification_at',
        'notification_count',
        'saved_card_id',
        'installments',
        'cancellation_reason',
        'proof_path',
        'proof_uploaded_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'notification_channels' => 'array',
        'paid_at' => 'datetime',
        'proof_uploaded_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_notification_at' => 'datetime',
        'notification_count' => 'integer',
        'installments' => 'integer',
    ];

    const PAYMENT_METHODS = [
        'pix' => 'PIX',
        'boleto' => 'Boleto',
        'credit_card' => 'Cartão de Crédito',
    ];

    const STATUSES = [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'overdue' => 'Vencido',
        'cancelled' => 'Cancelado',
    ];

    const NOTIFICATION_CHANNELS = [
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'telegram' => 'Telegram',
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

    public function savedCard(): BelongsTo
    {
        return $this->belongsTo(SavedCard::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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

    public function scopeByPaymentMethod(Builder $query, string $method): Builder
    {
        return $query->where('payment_method', $method);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
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

    // ==================== ACCESSORS ====================

    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function getStatusLabelAttribute(): string
    {
        if (!$this->status) {
            return 'Pendente';
        }
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid' &&
               $this->status !== 'cancelled' &&
               $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if ($this->status === 'paid' || $this->status === 'cancelled') {
            return null;
        }
        return max(0, now()->diffInDays($this->due_date, false));
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->is_overdue) {
            return null;
        }
        return now()->diffInDays($this->due_date);
    }

    // ==================== MÉTODOS ====================

    public function sendNotification(?array $channels = null): void
    {
        $channels = $channels ?? $this->notification_channels;

        // Implementar lógica de envio de notificação
        // Aqui você integraria com serviços de WhatsApp, E-mail, Telegram

        $this->update([
            'last_notification_at' => now(),
            'notification_count' => $this->notification_count + 1,
        ]);
    }

    public function markAsPaid(): bool
    {
        return $this->update([
            'status' => 'paid',
            'paid_at' => now(),
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
}
