<?php

namespace App\Services;

use App\Models\ResellerRenewalLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service to handle automatic sub-account activation/renewal
 * when a reseller charge is paid.
 *
 * Call ResellerChargeService::handleChargePaid($chargeId) from:
 * - ChargeController::updateStatus (when manually marking as paid)
 * - ChargeController::markAsPaid
 * - Mercado Pago webhook (when payment is approved)
 * - PublicPixPaymentController (if auto-approve is enabled)
 */
class ResellerChargeService
{
    /**
     * Check if a charge is linked to a reseller sub-account.
     * If so, activate the sub-account, renew its validity,
     * and create a Payment record.
     */
    public static function handleChargePaid(string $chargeId): void
    {
        $charge = DB::table('charges')->where('id', $chargeId)->first();

        if (!$charge) return;

        $accountId = $charge->reseller_charge_account_id ?? null;
        if (!$accountId) return;

        $account = User::find($accountId);
        if (!$account) return;

        $resellerId = $account->reseller_id;
        if (!$resellerId) return;

        // Determine renewal days (default 30)
        $renewalDays = 30;

        // Calculate new expiry
        $oldExpiry = $account->reseller_expires_at;
        $baseDate = $oldExpiry && $oldExpiry > now()
            ? new \DateTime($oldExpiry->format('Y-m-d H:i:s'))
            : now();

        $newExpiry = (clone $baseDate)->modify("+{$renewalDays} days");

        // Activate and renew
        $account->update([
            'reseller_expires_at' => $newExpiry,
            'status'              => 'active',
        ]);

        // Log the renewal
        ResellerRenewalLog::create([
            'account_id'     => $account->id,
            'renewed_by'     => $resellerId,
            'days'           => $renewalDays,
            'old_expires_at' => $oldExpiry,
            'new_expires_at' => $newExpiry,
        ]);

        // ==================== Criar registro de Payment ====================
        self::createPaymentRecord($charge);

        // ==================== Notificar sub-conta por e-mail ====================
        if ($account->email) {
            $reseller = User::find($resellerId);
            $methodLabels = [
                'pix'         => 'PIX',
                'boleto'      => 'Boleto',
                'credit_card' => 'Cartão de Crédito',
            ];

            MailService::sendTemplate($account->email, 'reseller_renewal_confirmed', [
                'account_name'   => $account->name,
                'reseller_name'  => $reseller->name ?? 'Revendedor',
                'charge_amount'  => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
                'payment_date'   => now()->format('d/m/Y H:i'),
                'payment_method' => $methodLabels[$charge->payment_method] ?? $charge->payment_method,
                'validity_days'  => (string) $renewalDays,
                'new_expiry'     => $newExpiry->format('d/m/Y'),
                'company_name'   => $reseller->company_name ?? $reseller->name ?? 'Sistema',
            ]);
        }

        Log::info("ResellerChargeService: Sub-account {$account->id} renewed +{$renewalDays} days after charge {$chargeId} paid.");
    }

    /**
     * Cria um registro de Payment para a cobrança de revenda paga.
     */
    private static function createPaymentRecord(object $charge): void
    {
        // Calcular taxa baseada no método de pagamento
        $feeRates = [
            'pix'         => 0.00,   // 0.99%
            'boleto'      => 0.00,   // 1.99%
            'credit_card' => 0.00,   // 3.99%
        ];

        $feeRate = $feeRates[$charge->payment_method] ?? 0.00;
        $fee = round((float) $charge->amount * $feeRate, 2);
        $netAmount = round((float) $charge->amount - $fee, 2);

        $paymentId = (string) Str::uuid();

        DB::table('payments')->insert([
            'id'              => $paymentId,
            'user_id'         => $charge->user_id,
            'client_id'       => $charge->client_id,
            'charge_id'       => $charge->id,
            'subscription_id' => null,
            'plan_id'         => null,
            'amount'          => $charge->amount,
            'fee'             => $fee,
            'net_amount'      => $netAmount,
            'payment_method'  => $charge->payment_method,
            'status'          => 'completed',
            'description'     => $charge->description ?? 'Pagamento de revenda',
            'transaction_id'  => 'TXN_' . strtoupper(substr(md5($charge->id . now()), 0, 12)),
            'completed_at'    => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        Log::info("ResellerChargeService: Payment {$paymentId} created for reseller charge {$charge->id}");
    }
}
