<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Charge;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendChargeReminders extends Command
{
    protected $signature = 'charges:send-reminders
                            {--dry-run : Simula sem enviar e-mails}
                            {--user= : Processa apenas um usuário específico}';

    protected $description = 'Envia lembretes de cobrança por e-mail baseado nas configurações do usuário (user_settings)';

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

            // Buscar todos os usuários (ou um específico) com suas configurações
            $usersQuery = User::query()
                ->when($userId, fn($q) => $q->where('id', $userId));

            foreach ($usersQuery->cursor() as $user) {
                // Carregar configurações de lembretes do user_settings
                $settings = DB::table('user_settings')
                    ->where('user_id', $user->id)
                    ->first();

                // Se não tem configurações ou auto_reminders está desabilitado, pular
                if (!$settings || !$settings->auto_reminders) {
                    $this->line("⏭️  Usuário {$user->name} — Lembretes automáticos desabilitados");
                    $skipped++;
                    continue;
                }

                // Verificar horário de envio configurado
                $sendTime = $settings->reminder_send_time ?? '09:00';
                $currentHour = now()->format('H:i');

                // Permitir margem de 30 minutos para execução do cron
                $sendHour = (int) explode(':', $sendTime)[0];
                $currentHourInt = (int) now()->format('H');

                if ($currentHourInt !== $sendHour) {
                    $skipped++;
                    continue;
                }

                // Verificar se a notificação charge_reminder está habilitada
                if (!MailService::isNotificationEnabled($user, 'charge_reminder')) {
                    $this->line("⏭️  Usuário {$user->name} — Notificação charge_reminder desabilitada");
                    $skipped++;
                    continue;
                }

                // Parsear lembretes configurados
                $reminders = json_decode($settings->reminders ?? '[]', true) ?? [];
                $enabledReminders = array_filter($reminders, fn($r) => ($r['enabled'] ?? false) && ($r['channels']['email'] ?? false));

                if (empty($enabledReminders)) {
                    $skipped++;
                    continue;
                }

                $this->line("👤 Usuário: {$user->name} — Horário: {$sendTime} — " . count($enabledReminders) . " lembretes ativos");

                // Buscar cobranças pendentes e vencidas do usuário
                $charges = Charge::where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->whereNotNull('due_date')
                    ->with('client')
                    ->get();

                foreach ($charges as $charge) {
                    if (!$charge->client?->email) {
                        $skipped++;
                        continue;
                    }

                    // Evitar duplicata: se já notificou hoje
                    if ($charge->last_notification_at && Carbon::parse($charge->last_notification_at)->isToday()) {
                        $skipped++;
                        continue;
                    }

                    $dueDate = Carbon::parse($charge->due_date)->startOfDay();
                    $today = now()->startOfDay();
                    $diffDays = (int) $today->diffInDays($dueDate, false); // positivo = futuro, negativo = passado

                    // Verificar se algum lembrete configurado bate com a data atual
                    $shouldSend = false;
                    $matchedReminder = null;

                    foreach ($enabledReminders as $reminder) {
                        $type = $reminder['type'] ?? '';
                        $days = (int) ($reminder['days'] ?? 0);

                        $matches = match ($type) {
                            'before' => $diffDays === $days,      // X dias antes do vencimento
                            'on_due' => $diffDays === 0,          // No dia do vencimento
                            'after'  => $diffDays === -$days,     // X dias após vencimento
                            default  => false,
                        };

                        if ($matches) {
                            $shouldSend = true;
                            $matchedReminder = $reminder;
                            break;
                        }
                    }

                    if (!$shouldSend) {
                        $skipped++;
                        continue;
                    }

                    // Determinar template baseado no tipo
                    $type = $matchedReminder['type'] ?? 'before';
                    $template = match ($type) {
                        'after'  => 'charge_overdue',
                        default  => 'charge_reminder',
                    };

                    $typeLabel = match ($type) {
                        'before' => "{$matchedReminder['days']}d antes",
                        'on_due' => "no vencimento",
                        'after'  => "{$matchedReminder['days']}d após",
                        default  => $type,
                    };

                    $this->line("  📧 [{$typeLabel}] {$charge->client->name} — {$charge->description} — Vencimento: {$dueDate->format('d/m/Y')} — {$charge->formatted_amount}");

                    if (!$isDryRun) {
                        try {
                            $templateData = [
                                'client_name'        => $charge->client->name,
                                'company_name'       => $user->company_name ?? $user->name ?? 'Sistema',
                                'charge_description' => $charge->description ?? 'Cobrança',
                                'charge_amount'      => $charge->formatted_amount,
                                'due_date'           => $dueDate->format('d/m/Y'),
                                'payment_link'       => $charge->mp_init_point ?? '#',
                            ];

                            if ($type === 'before') {
                                $templateData['days_until_due'] = (string) $matchedReminder['days'];
                            } elseif ($type === 'after') {
                                $templateData['days_overdue'] = (string) $matchedReminder['days'];
                            }

                            MailService::sendTemplate(
                                $charge->client->email,
                                $template,
                                $templateData
                            );

                            $charge->update([
                                'last_notification_at' => now(),
                                'notification_count'   => ($charge->notification_count ?? 0) + 1,
                            ]);

                            $sent++;
                        } catch (\Exception $e) {
                            $errors[] = [
                                'type'    => $template,
                                'ref'     => $charge->id,
                                'client'  => $charge->client->name,
                                'message' => $e->getMessage(),
                            ];
                            $this->error("  ❌ Falha: {$e->getMessage()}");
                        }
                    } else {
                        $sent++;
                    }
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
