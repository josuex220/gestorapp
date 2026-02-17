<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $planName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sua assinatura foi cancelada',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription-cancelled',
            with: [
                'userName' => $this->user->name,
                'planName' => $this->planName ?? 'seu plano',
                'appName' => config('app.name'),
                'resubscribeUrl' => config('app.frontend_url') . '/assinatura',
            ],
        );
    }
}
