<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlyInvoiceGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Nova fatura gerada - {$this->invoice->period}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Sua fatura do período **{$this->invoice->period}** foi gerada.")
            ->line("Valor: **R$ " . number_format($this->invoice->amount, 2, ',', '.') . "**")
            ->line("Vencimento: **" . $this->invoice->due_date->format('d/m/Y') . "**")
            ->action('Ver Fatura', config('app.frontend_url') . '/subscription')
            ->line('Obrigado por utilizar nossos serviços!');
    }
}
