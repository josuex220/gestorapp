<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'ticket_number',
        'subject',
        'status',
        'priority',
        'category',
        'last_reply_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Boot method para gerar ticket_number automaticamente
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            $lastTicket = self::orderBy('created_at', 'desc')->first();
            $nextNumber = $lastTicket ? ((int) substr($lastTicket->ticket_number, 4)) + 1 : 1;
            $ticket->ticket_number = 'TKT-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }
}
