<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'platform_plan_id'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relacionamentos de Suporte
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function supportMessages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'sender_id');
    }

    // Relacionamentos Financeiros
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Relacionamentos Gerais
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function savedCards(): HasMany
    {
        return $this->hasMany(SavedCard::class);
    }
    public function platformPlan()
    {
        return $this->belongsTo(PlatformPlan::class, 'platform_plan_id');
    }
    /**
     * Platform invoices (internal SaaS billing records).
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(PlatformInvoice::class);
    }

    /**
     * The user's PIX configuration.
     */
    public function pixConfig(): HasOne
    {
        return $this->hasOne(UserPixConfig::class);
    }

    /**
     * Learning lesson progress records.
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    // ────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────

    /**
     * Check if the user has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return in_array($this->status, ['active', 'cancelling']);
    }

    /**
     * Check if the user is currently in a trial period.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }


}
