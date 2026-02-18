<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_invoice_id',
        'invoice_number',
        'amount',
        'status',
        'currency',
        'description',
        'event_type',
        'paid_at',
        'due_date',
        'period',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'due_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
