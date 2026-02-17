<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🚫 Conta suspensa por inadimplência')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Sua conta foi suspensa devido a faturas em atraso.')
            ->line('Para reativar seu acesso, regularize suas pendências.')
            ->action('Regularizar Agora', config('app.frontend_url') . '/subscription')
            ->line('Em caso de dúvidas, entre em contato com nosso suporte.');
    }
}
