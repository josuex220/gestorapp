<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection as SupportCollection;

class SubAccountExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $reseller;
    public SupportCollection $accounts;
    public string $urgency;
    public int $daysRemaining;

    public function __construct(User $reseller, SupportCollection $accounts, string $urgency = 'warning', int $daysRemaining = 7)
    {
        $this->reseller = $reseller;
        $this->accounts = $accounts;
        $this->urgency = $urgency;
        $this->daysRemaining = $daysRemaining;
    }

    public function envelope(): Envelope
    {
        $count = $this->accounts->count();
        $plural = $count === 1 ? 'sub-conta' : 'sub-contas';

        $subject = match ($this->urgency) {
            'critical' => "⛔ {$count} {$plural} expira(m) HOJE!",
            'urgent'   => "⚠️ {$count} {$plural} expira(m) em 3 dias",
            default    => "📋 {$count} {$plural} expira(m) em 7 dias",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sub-account-expiring');
    }
}
