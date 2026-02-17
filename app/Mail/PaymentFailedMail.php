<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public float $amount,
        public string $invoiceNumber,
        public ?string $hostedUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Falha no pagamento da sua assinatura',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-failed',
            with: [
                'userName' => $this->user->name,
                'amount' => number_format($this->amount, 2, ',', '.'),
                'invoiceNumber' => $this->invoiceNumber,
                'hostedUrl' => $this->hostedUrl,
                'appName' => config('app.name'),
            ],
        );
    }
}
