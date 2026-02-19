<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:cleanup-sessions-logs')->daily();

// Gerar cobranças automáticas para assinaturas vencidas — executa diariamente às 06:00
Schedule::command('subscriptions:generate-charges')->dailyAt('06:00');

// Marcar cobranças vencidas como overdue (respeita notification_preferences) — executa diariamente às 07:00
Schedule::command('charges:mark-overdue')->dailyAt('07:00');

// Enviar lembretes de cobrança — executa a cada hora para atender o reminder_send_time de cada usuário
Schedule::command('charges:send-reminders')->hourly();

// Enviar alertas de expiração de sub-contas de revenda — executa diariamente às 08:30
Schedule::command('reseller:expiration-alerts')->dailyAt('08:30');
