<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SubscriptionChargeService
{
    /**
     * Gera cobranças para assinaturas ativas cujo next_billing_date é hoje ou já passou.
     *
     * Fluxo:
     * 1. Busca assinaturas ativas com next_billing_date <= hoje
     * 2. Para cada assinatura, verifica se já existe cobrança pendente para o período
     * 3. Se não existir, cria uma nova cobrança com status "pending"
     * 4. Avança o next_billing_date para o próximo ciclo
     *
     * @param bool     $dryRun  Se true, apenas simula sem criar registros
     * @param int|null $userId  Se informado, processa apenas assinaturas deste usuário
     * @return array   Resultado com contadores de processed, charges_created, skipped, errors
     */
    public function generateCharges(bool $dryRun = false, ?int $userId = null): array
    {
        $result = [
            'processed' => 0,
            'charges_created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Buscar assinaturas ativas com next_billing_date vencido ou hoje
        $query = Subscription::query()
            ->where('status', 'active')
            ->whereDate('next_billing_date', '<=', now());

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $subscriptions = $query->with(['client', 'plan'])->get();

        foreach ($subscriptions as $subscription) {
            $result['processed']++;

            try {
                // Verificar se já existe cobrança pendente/paga para este período
                $existingCharge = DB::table('charges')
                    ->where('subscription_id', $subscription->id)
                    ->where('due_date', $subscription->next_billing_date->format('Y-m-d'))
                    ->whereIn('status', ['pending', 'paid'])
                    ->exists();

                if ($existingCharge) {
                    $result['skipped']++;
                    Log::debug("Cobrança já existe para assinatura {$subscription->id} no período {$subscription->next_billing_date->format('d/m/Y')}");
                    continue;
                }

                if ($dryRun) {
                    $result['charges_created']++;
                    Log::info("[DRY-RUN] Cobrança seria criada para assinatura {$subscription->id}", [
                        'client_id' => $subscription->client_id,
                        'amount' => $subscription->amount,
                        'due_date' => $subscription->next_billing_date->format('Y-m-d'),
                    ]);
                    continue;
                }

                // Criar a cobrança dentro de uma transação
                DB::transaction(function () use ($subscription, &$result) {
                    $chargeId = (string) Str::uuid();

                    // Determinar método de pagamento padrão
                    // Tenta usar o último método de pagamento do cliente, senão usa 'pix'
                    $lastPaymentMethod = DB::table('charges')
                        ->where('subscription_id', $subscription->id)
                        ->where('status', 'paid')
                        ->orderBy('paid_at', 'desc')
                        ->value('payment_method') ?? 'pix';

                    // Criar a cobrança
                    DB::table('charges')->insert([
                        'id' => $chargeId,
                        'user_id' => $subscription->user_id,
                        'client_id' => $subscription->client_id,
                        'subscription_id' => $subscription->id,
                        'amount' => $subscription->amount,
                        'due_date' => $subscription->next_billing_date->format('Y-m-d'),
                        'payment_method' => $lastPaymentMethod,
                        'status' => 'pending',
                        'description' => "Cobrança recorrente - {$subscription->plan_name} ({$subscription->cycle_label})",
                        'notification_channels' => json_encode(['email']),
                        'notification_count' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Avançar a data de próximo vencimento
                    $subscription->advanceBillingDate();

                    $result['charges_created']++;

                    // Notificar cliente por e-mail usando template charge_created
                    if ($subscription->client && $subscription->client->email) {
                        $user = User::find($subscription->user_id);

                        try {
                            MailService::sendTemplate($subscription->client->email, 'charge_created', [
                                'client_name'        => $subscription->client->name,
                                'company_name'       => $user->company_name ?? $user->name ?? 'Sistema',
                                'charge_description' => "Cobrança recorrente - {$subscription->plan_name} ({$subscription->cycle_label})",
                                'charge_amount'      => 'R$ ' . number_format($subscription->amount, 2, ',', '.'),
                                'due_date'           => $subscription->next_billing_date->format('d/m/Y'),
                                'payment_link'       => $charge->mp_init_point ?? '#',
                            ]);
                        } catch (\Exception $e) {
                            Log::warning("Falha ao enviar e-mail charge_created para assinatura {$subscription->id}", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Log::info("Cobrança gerada para assinatura {$subscription->id}", [
                        'charge_id' => $chargeId,
                        'client_id' => $subscription->client_id,
                        'amount' => $subscription->amount,
                        'due_date' => $subscription->next_billing_date->format('Y-m-d'),
                    ]);
                });
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'subscription_id' => $subscription->id,
                    'client_id' => $subscription->client_id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Chamado quando uma cobrança de assinatura é paga.
     * Gera o registro de Payment e atualiza a assinatura.
     *
     * @param string $chargeId  ID da cobrança que foi paga
     */
    public static function handleSubscriptionChargePaid(string $chargeId): void
    {
        $charge = DB::table('charges')->where('id', $chargeId)->first();

        if (!$charge || !$charge->subscription_id) {
            return;
        }

        $subscription = Subscription::find($charge->subscription_id);

        if (!$subscription) {
            return;
        }

        // Calcular taxa baseada no método de pagamento
        $feeRates = [
            'pix' => 0.00,        // 0.99%
            'boleto' => 0.00,      // 1.99%
            'credit_card' => 0.00, // 3.99%
        ];

        $feeRate = $feeRates[$charge->payment_method] ?? 0.02;
        $fee = round((float) $charge->amount * $feeRate, 2);
        $netAmount = round((float) $charge->amount - $fee, 2);

        // Criar registro de Payment
        $paymentId = (string) Str::uuid();

        DB::table('payments')->insert([
            'id' => $paymentId,
            'user_id' => $charge->user_id,
            'client_id' => $charge->client_id,
            'charge_id' => $chargeId,
            'subscription_id' => $charge->subscription_id,
            'plan_id' => $subscription->plan_id,
            'amount' => $charge->amount,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'payment_method' => $charge->payment_method,
            'status' => 'completed',
            'description' => $charge->description ?? "Pagamento de assinatura - {$subscription->plan_name}",
            'transaction_id' => 'TXN_' . strtoupper(substr(md5($chargeId . now()), 0, 12)),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Atualizar last_payment_date da assinatura
        $subscription->update([
            'last_payment_date' => now(),
        ]);

        Log::info("Payment criado para cobrança de assinatura", [
            'payment_id' => $paymentId,
            'charge_id' => $chargeId,
            'subscription_id' => $subscription->id,
            'amount' => $charge->amount,
        ]);

        // Notificar cliente por e-mail usando template subscription_payment_confirmed
        $client = $subscription->client;
        if ($client && $client->email) {
            $user = User::find($charge->user_id);

            $methodLabels = [
                'pix' => 'Pix',
                'boleto' => 'Boleto',
                'credit_card' => 'Cartão de Crédito',
            ];

            try {
                MailService::sendTemplate($client->email, 'subscription_payment_confirmed', [
                    'client_name'       => $client->name,
                    'company_name'      => $user->company_name ?? $user->name ?? 'Sistema',
                    'plan_name'         => $subscription->plan_name,
                    'charge_amount'     => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
                    'payment_date'      => now()->format('d/m/Y'),
                    'payment_method'    => $methodLabels[$charge->payment_method] ?? $charge->payment_method,
                    'next_billing_date' => $subscription->next_billing_date
                        ? $subscription->next_billing_date->format('d/m/Y')
                        : '-',
                    'fee'               => 'R$ ' . number_format($fee, 2, ',', '.'),
                    'net_amount'        => 'R$ ' . number_format($netAmount, 2, ',', '.'),
                ]);
            } catch (\Exception $e) {
                Log::warning("Falha ao enviar e-mail subscription_payment_confirmed", [
                    'charge_id' => $chargeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
