<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Charge;
use App\Models\Subscription;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

class SendChargeReminders extends Command
{
    protected $signature = 'charges:send-reminders
                            {--dry-run : Simula sem enviar e-mails}
                            {--user= : Processa apenas um usuário específico}';

    protected $description = 'Envia lembretes de cobrança por e-mail';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user');

        $this->info('=== Envio de Lembretes de Cobrança ===');
        $this->info('Data: ' . now()->format('d/m/Y H:i:s'));

        if ($isDryRun) {
            $this->warn('⚠️  Modo simulação (dry-run) — nenhum e-mail será enviado.');
        }

        try {
            $sent = 0;
            $skipped = 0;
            $errors = [];

            // ─── PARTE 1: Lembretes de cobrança de assinaturas ───
            $this->info('');
            $this->info('── Lembretes de Cobrança ──');

            $subscriptions = Subscription::active()
                ->whereNotNull('reminder_days')
                ->where('reminder_days', '>', 0)
                ->with(['client', 'user'])
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->get();

            foreach ($subscriptions as $subscription) {
                $reminderDate = $subscription->next_billing_date
                    ->copy()
                    ->subDays($subscription->reminder_days)
                    ->startOfDay();

                if (!now()->startOfDay()->equalTo($reminderDate)) {
                    $skipped++;
                    continue;
                }

                $charge = Charge::where('subscription_id', $subscription->id)
                    ->where('due_date', $subscription->next_billing_date)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->first();

                if (!$charge) {
                    $skipped++;
                    continue;
                }

                if (!$subscription->client?->email) {
                    $this->warn("⚠️  Cliente sem e-mail: {$subscription->client?->name} (Assinatura: {$subscription->id})");
                    $skipped++;
                    continue;
                }

                if ($charge->last_notification_at && $charge->last_notification_at->isToday()) {
                    $skipped++;
                    continue;
                }

                $this->line("📧 Lembrete: {$subscription->client->name} — {$subscription->plan_name} — Vencimento: {$subscription->next_billing_date->format('d/m/Y')} — {$charge->formatted_amount}");

                if (!$isDryRun) {
                    try {
                        MailService::sendTemplate($subscription->client->email, 'charge_reminder', [
                            'client_name'        => $subscription->client->name,
                            'company_name'       => $subscription->user->company_name ?? $subscription->user->name ?? 'Sistema',
                            'charge_description' => $charge->description ?? $subscription->plan_name,
                            'charge_amount'      => $charge->formatted_amount,
                            'due_date'           => $subscription->next_billing_date->format('d/m/Y'),
                            'days_until_due'     => (string) $subscription->reminder_days,
                            'payment_link'       => $charge->mp_init_point ?? '#',
                        ]);

                        $charge->update([
                            'last_notification_at' => now(),
                            'notification_count'   => ($charge->notification_count ?? 0) + 1,
                        ]);

                        $sent++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'type'    => 'charge_reminder',
                            'ref'     => $charge->id,
                            'client'  => $subscription->client->name,
                            'message' => $e->getMessage(),
                        ];
                        $this->error("  ❌ Falha: {$e->getMessage()}");
                    }
                } else {
                    $sent++;
                }
            }

            $this->newLine();
            $this->info("✅ Lembretes enviados: {$sent}");
            $this->info("⏭️  Ignorados: {$skipped}");

            if (!empty($errors)) {
                $this->error("❌ Erros: " . count($errors));
                foreach ($errors as $error) {
                    $this->error("  - {$error['client']} ({$error['ref']}): {$error['message']}");
                    Log::error('Erro ao enviar lembrete', $error);
                }
            }

            if ($sent > 0 && !$isDryRun) {
                Log::info('Lembretes de cobrança enviados', [
                    'sent'    => $sent,
                    'skipped' => $skipped,
                ]);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro fatal: {$e->getMessage()}");
            Log::error('Erro fatal ao enviar lembretes', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
