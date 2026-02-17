<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public float $amount,
        public string $invoiceNumber,
        public string $description,
        public ?string $pdfUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pagamento confirmado — sua assinatura está ativa',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-confirmed',
            with: [
                'userName' => $this->user->name,
                'amount' => number_format($this->amount, 2, ',', '.'),
                'invoiceNumber' => $this->invoiceNumber,
                'description' => $this->description,
                'pdfUrl' => $this->pdfUrl,
                'appName' => config('app.name'),
            ],
        );
    }
}
