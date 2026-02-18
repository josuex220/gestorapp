<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendResellerExpirationAlerts extends Command
{
    protected $signature = 'reseller:expiration-alerts 
                            {--dry-run : Simula sem enviar e-mails}
                            {--user= : Processa apenas um revendedor específico}';

    protected $description = 'Envia alertas automáticos de expiração de sub-contas de revenda via e-mail';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user');

        $this->info('=== Alertas de Expiração de Sub-contas ===');
        $this->info('Data: ' . now()->format('d/m/Y H:i:s'));

        if ($isDryRun) {
            $this->warn('⚠️  Modo simulação (dry-run) — nenhum e-mail será enviado.');
        }

        try {
            $sent = 0;
            $skipped = 0;
            $errors = [];

            // Buscar revendedores com notificações de expiração habilitadas
            $resellerSettings = DB::table('reseller_notification_settings')
                ->where('enabled', true)
                ->where(function ($q) {
                    $q->whereJsonContains('channels->email', true)
                      ->orWhereRaw("JSON_EXTRACT(channels, '$.email') = 'true'");
                })
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->get();

            if ($resellerSettings->isEmpty()) {
                $this->info('Nenhum revendedor com notificações habilitadas.');
                return self::SUCCESS;
            }

            foreach ($resellerSettings as $settings) {
                $reseller = User::find($settings->user_id);
                if (!$reseller) {
                    $skipped++;
                    continue;
                }

                $alertDays = json_decode($settings->alert_days ?? '[7, 3, 1]', true) ?? [7, 3, 1];

                $this->line("👤 Revendedor: {$reseller->name} (ID: {$reseller->id}) — Alertas: " . implode(', ', $alertDays) . ' dias');

                // Buscar sub-contas ativas com validade definida
                $subAccounts = User::where('reseller_id', $reseller->id)
                    ->where('status', 'active')
                    ->whereNotNull('reseller_expires_at')
                    ->get();

                if ($subAccounts->isEmpty()) {
                    $this->line('  Nenhuma sub-conta ativa com validade definida.');
                    continue;
                }

                foreach ($subAccounts as $account) {
                    if (!$account->email) {
                        $this->warn("  ⚠️  Sub-conta sem e-mail: {$account->name}");
                        $skipped++;
                        continue;
                    }

                    $expiresAt = $account->reseller_expires_at;
                    $daysRemaining = (int) now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);

                    // Verificar se hoje corresponde a algum dos dias de alerta configurados
                    if (!in_array($daysRemaining, $alertDays)) {
                        $skipped++;
                        continue;
                    }

                    // Evitar duplicatas: verificar se já enviou alerta hoje para esta conta
                    $alreadySent = DB::table('mail_logs')
                        ->where('to_email', $account->email)
                        ->where('event', 'reseller_account_expiring')
                        ->whereDate('created_at', now()->toDateString())
                        ->exists();

                    if ($alreadySent) {
                        $skipped++;
                        continue;
                    }

                    $urgencyLabel = match (true) {
                        $daysRemaining <= 1 => '🔴 URGENTE',
                        $daysRemaining <= 3 => '🟠 ATENÇÃO',
                        default              => '🟡 AVISO',
                    };

                    $this->line("  {$urgencyLabel} {$account->name} ({$account->email}) — Expira em {$daysRemaining} dia(s) ({$expiresAt->format('d/m/Y')})");

                    if (!$isDryRun) {
                        try {
                            MailService::sendTemplate($account->email, 'reseller_account_expiring', [
                                'account_name'   => $account->name,
                                'account_email'  => $account->email,
                                'reseller_name'  => $reseller->company_name ?? $reseller->name,
                                'days_remaining' => (string) $daysRemaining,
                                'expiry_date'    => $expiresAt->format('d/m/Y'),
                                'renewal_price'  => $account->reseller_price
                                    ? 'R$ ' . number_format((float) $account->reseller_price, 2, ',', '.')
                                    : 'Consulte seu revendedor',
                                'company_name'   => $reseller->company_name ?? $reseller->name ?? 'Sistema',
                            ]);

                            $sent++;
                        } catch (\Exception $e) {
                            $errors[] = [
                                'reseller'  => $reseller->name,
                                'account'   => $account->name,
                                'email'     => $account->email,
                                'message'   => $e->getMessage(),
                            ];
                            $this->error("  ❌ Falha: {$e->getMessage()}");
                        }
                    } else {
                        $sent++;
                    }
                }
            }

            $this->newLine();
            $this->info("✅ Alertas enviados: {$sent}");
            $this->info("⏭️  Ignorados: {$skipped}");

            if (!empty($errors)) {
                $this->error("❌ Erros: " . count($errors));
                foreach ($errors as $error) {
                    $this->error("  - {$error['account']} ({$error['email']}): {$error['message']}");
                    Log::error('Erro ao enviar alerta de expiração de sub-conta', $error);
                }
            }

            if ($sent > 0 && !$isDryRun) {
                Log::info('Alertas de expiração de sub-contas enviados', [
                    'sent'    => $sent,
                    'skipped' => $skipped,
                    'errors'  => count($errors),
                ]);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro fatal: {$e->getMessage()}");
            Log::error('Erro fatal ao enviar alertas de expiração', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
