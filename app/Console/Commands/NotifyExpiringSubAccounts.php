<?php

namespace App\Console\Commands;

use App\Models\ResellerNotificationSetting;
use App\Models\User;
use App\Mail\SubAccountExpiringMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyExpiringSubAccounts extends Command
{
    protected $signature = 'reseller:notify-expiring';
    protected $description = 'Envia notificações ao revendedor sobre sub-contas expirando, respeitando as preferências configuradas';

    public function handle(): int
    {
        $totalNotified = 0;

        // Buscar todos os revendedores que possuem sub-contas com expiração
        $resellerIds = User::whereNotNull('reseller_id')
            ->where('status', 'active')
            ->whereNotNull('reseller_expires_at')
            ->distinct()
            ->pluck('reseller_id');

        foreach ($resellerIds as $resellerId) {
            $reseller = User::find($resellerId);
            if (!$reseller || !$reseller->email) {
                continue;
            }

            // Buscar configurações do revendedor (ou usar padrão)
            $settings = $reseller->resellerNotificationSettings;
            $enabled = $settings ? $settings->enabled : true;
            $alertDays = $settings ? $settings->alert_days : [3, 7, 30];
            $channels = $settings ? $settings->channels : ['email' => true, 'whatsapp' => false];

            if (!$enabled) {
                $this->info("Revendedor #{$resellerId} ({$reseller->name}) desativou notificações. Pulando.");
                continue;
            }

            foreach ($alertDays as $daysAhead) {
                $urgency = $this->getUrgency($daysAhead);

                $from = now()->addDays($daysAhead)->startOfDay();
                $to   = now()->addDays($daysAhead + 1)->startOfDay();

                $accounts = User::where('reseller_id', $resellerId)
                    ->where('status', 'active')
                    ->whereNotNull('reseller_expires_at')
                    ->whereBetween('reseller_expires_at', [$from, $to])
                    ->get();

                if ($accounts->isEmpty()) {
                    continue;
                }

                // Canal: E-mail
                if ($channels['email'] ?? false) {
                    Mail::to($reseller->email)->send(
                        new SubAccountExpiringMail($reseller, $accounts, $urgency, $daysAhead)
                    );
                }

                // Canal: WhatsApp (placeholder para integração futura)
                if ($channels['whatsapp'] ?? false) {
                    // TODO: Integrar com API de WhatsApp
                    $this->info("  WhatsApp: notificação pendente de integração para {$reseller->phone}");
                }

                $totalNotified += $accounts->count();
                $this->info("[{$urgency}] {$accounts->count()} sub-conta(s) de {$reseller->name} expirando em {$daysAhead} dia(s).");
            }
        }

        $this->info("Total de notificações enviadas: {$totalNotified}");

        return self::SUCCESS;
    }

    /**
     * Determina o nível de urgência com base nos dias restantes.
     */
    private function getUrgency(int $days): string
    {
        if ($days <= 0) return 'critical';
        if ($days <= 3) return 'urgent';
        return 'warning';
    }
}
