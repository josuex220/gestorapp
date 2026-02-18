<?php

namespace App\Services;

use App\Models\ResellerRenewalLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * If so, activate the sub-account and renew its validity.
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

        Log::info("ResellerChargeService: Sub-account {$account->id} renewed +{$renewalDays} days after charge {$chargeId} paid.");
    }
}
