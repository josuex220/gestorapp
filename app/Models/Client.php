<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Client extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'document',
        'company',
        'address',
        'notes',
        'tags',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
    ];

    // ==================== RELACIONAMENTOS ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function savedCards(): HasMany
    {
        return $this->hasMany(SavedCard::class);
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

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('document', 'like', "%{$search}%")
              ->orWhere('company', 'like', "%{$search}%");
        });
    }

    public function scopeByTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopeWithPendingCharges(Builder $query): Builder
    {
        return $query->whereHas('charges', function ($q) {
            $q->whereIn('status', ['pending', 'overdue']);
        });
    }

    public function scopeWithActiveSubscriptions(Builder $query): Builder
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->where('status', 'active');
        });
    }

    // ==================== ACCESSORS ====================

    public function getTotalBilledAttribute(): float
    {
        return (float) $this->charges()->sum('amount');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'completed')->sum('net_amount');
    }

    public function getPendingChargesCountAttribute(): int
    {
        return $this->charges()->whereIn('status', ['pending', 'overdue'])->count();
    }

    public function getPendingAmountAttribute(): float
    {
        return (float) $this->charges()->whereIn('status', ['pending', 'overdue'])->sum('amount');
    }

    public function getOverdueAmountAttribute(): float
    {
        return (float) $this->charges()->where('status', 'overdue')->sum('amount');
    }

    public function getActiveSubscriptionsCountAttribute(): int
    {
        return $this->subscriptions()->where('status', 'active')->count();
    }

    public function getFormattedDocumentAttribute(): ?string
    {
        if (!$this->document) return null;

        $doc = preg_replace('/\D/', '', $this->document);

        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }

        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }

        return $this->document;
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

    public function addTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update(['tags' => $tags]);
        }
        return false;
    }

    public function removeTag(string $tag): bool
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        return $this->update(['tags' => array_values($tags)]);
    }

    public function getDefaultCard(): ?SavedCard
    {
        return $this->savedCards()->where('is_default', true)->first();
    }

    public function getTotalChargesBilledAttribute(): float
    {
        return $this->charges()
            ->whereIn('status', ['paid'])
            ->sum('amount');
    }

    public function getTotalSubscriptionsBilledAttribute(): float
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->get()
            ->sum(function ($sub) {
                return $sub->payments()->sum('amount');
            });
    }

}
