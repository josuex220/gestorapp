<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\MailService;

class MarkOverdueCharges extends Command
{
    protected $signature = 'charges:mark-overdue';
    protected $description = 'Marca cobranças pendentes com data de vencimento passada como vencidas (overdue) e notifica o cliente respeitando preferências';

    public function handle(): int
    {
        // Buscar cobranças que serão marcadas como overdue (com dados do cliente e usuário)
        $charges = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->leftJoin('users', 'charges.user_id', '=', 'users.id')
            ->select('charges.id', 'charges.amount', 'charges.due_date', 'charges.description',
                     'charges.payment_method', 'charges.mp_init_point', 'charges.user_id',
                     'clients.name as client_name', 'clients.email as client_email',
                     'users.name as user_name', 'users.company_name as user_company_name')
            ->where('charges.status', 'pending')
            ->whereDate('charges.due_date', '<', now()->startOfDay())
            ->get();

        $count = $charges->count();

        if ($count === 0) {
            $this->info("✅ Nenhuma cobrança pendente vencida.");
            return self::SUCCESS;
        }

        // Marcar todas como overdue
        DB::table('charges')
            ->where('status', 'pending')
            ->whereDate('due_date', '<', now()->startOfDay())
            ->update([
                'status' => 'overdue',
                'updated_at' => now(),
            ]);

        // Enviar e-mail de cobrança vencida para cada cliente (respeitando preferências)
        $emailsSent = 0;
        foreach ($charges as $charge) {
            if (!$charge->client_email) {
                continue;
            }

            // Verificar se a notificação payment_overdue está habilitada para o usuário
            $user = User::find($charge->user_id);
            if ($user && !MailService::isNotificationEnabled($user, 'payment_overdue')) {
                $this->line("⏭️  Notificação payment_overdue desabilitada para usuário {$user->name}");
                continue;
            }

            $dueDate = \Carbon\Carbon::parse($charge->due_date);
            $daysOverdue = $dueDate->diffInDays(now()->startOfDay());

            try {
                MailService::sendTemplate($charge->client_email, 'charge_overdue', [
                    'client_name'        => $charge->client_name ?? 'Cliente',
                    'company_name'       => $charge->user_company_name ?? $charge->user_name ?? 'Sistema',
                    'charge_description' => $charge->description ?? 'Cobrança',
                    'charge_amount'      => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
                    'due_date'           => $dueDate->format('d/m/Y'),
                    'days_overdue'       => (string) $daysOverdue,
                    'payment_link'       => $charge->mp_init_point ?? '#',
                ]);
                $emailsSent++;
            } catch (\Exception $e) {
                $this->error("  ❌ Falha ao enviar para {$charge->client_email}: {$e->getMessage()}");
                Log::error('Erro ao enviar e-mail de overdue', [
                    'charge_id' => $charge->id,
                    'email'     => $charge->client_email,
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("✅ {$count} cobrança(s) marcada(s) como vencida(s). {$emailsSent} e-mail(s) enviado(s).");

        if ($count > 0) {
            Log::info("Cobranças marcadas como overdue: {$count}, e-mails enviados: {$emailsSent}");
        }

        return self::SUCCESS;
    }
}
