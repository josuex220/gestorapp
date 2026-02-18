<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminIntegration;
use App\Models\PlatformInvoice;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook events.
     * This endpoint should NOT require authentication.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        $stripeIntegration = AdminIntegration::where('name', 'stripe')
            ->where('connected', true)
            ->first();

        if (!$stripeIntegration) {
            Log::warning('Stripe webhook received but Stripe is not configured.');
            return response()->json(['error' => 'Stripe not configured'], 503);
        }

        $fields = is_string($stripeIntegration->fields)
            ? json_decode($stripeIntegration->fields, true)
            : $stripeIntegration->fields;

        $webhookSecret = $fields['webhook_secret'] ?? null;

        // Verify signature if webhook secret is configured
        if ($webhookSecret && $sigHeader) {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                Log::error('Stripe webhook signature verification failed.', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Invalid signature'], 400);
            }
        } else {
            // Parse without verification (dev/testing only)
            $event = json_decode($payload);
            if (!$event || !isset($event->type)) {
                return response()->json(['error' => 'Invalid payload'], 400);
            }
        }

        Log::info("Stripe webhook received: {$event->type}", ['event_id' => $event->id ?? null]);

        match ($event->type) {
            'invoice.paid'              => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed'    => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.updated'  => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted'  => $this->handleSubscriptionDeleted($event->data->object),
            'checkout.session.completed'     => $this->handleCheckoutCompleted($event->data->object),
            default => Log::info("Unhandled Stripe event: {$event->type}"),
        };

        return response()->json(['received' => true]);
    }

    /**
     * invoice.paid — Confirms payment and keeps subscription active.
     */
    private function handleInvoicePaid(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        if (!$subscriptionId) return;

        $user = User::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$user) {
            Log::warning("invoice.paid: No user found for subscription {$subscriptionId}");
            return;
        }

        // Ensure user is active
        if ($user->status !== 'active') {
            $user->update(['status' => 'active']);
        }

        // Determine event_type: first invoice = activation, subsequent = renewal
        $billingReason = $invoice->billing_reason ?? null;
        $isFirstInvoice = in_array($billingReason, ['subscription_create', 'subscription_threshold']);
        $eventType = $isFirstInvoice ? 'activation' : 'renewal';

        // Check if this was a reactivation (user was cancelled/cancelling before)
        if ($isFirstInvoice) {
            $existingInvoices = PlatformInvoice::where('user_id', $user->id)
                ->whereIn('event_type', ['activation', 'reactivation', 'renewal'])
                ->exists();
            if ($existingInvoices) {
                $eventType = 'reactivation';
            }
        }

        // Store/update platform invoice record
        PlatformInvoice::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'invoice_number' => $invoice->number,
                'amount' => ($invoice->amount_paid ?? 0) / 100,
                'status' => 'paid',
                'event_type' => $eventType,
                'paid_at' => now(),
                'description' => $invoice->lines->data[0]->description ?? 'Assinatura',
            ]
        );

        // Send confirmation email via MailService
        try {
            $amount = ($invoice->amount_paid ?? 0) / 100;
            $invoiceNumber = $invoice->number ?? 'N/A';
            $description = $invoice->lines->data[0]->description ?? 'Assinatura';
            $pdfUrl = $invoice->invoice_pdf ?? null;

            MailService::paymentConfirmed($user->email, [
                'name'           => $user->name,
                'amount'         => 'R$ ' . number_format($amount, 2, ',', '.'),
                'invoice_number' => $invoiceNumber,
                'description'    => $description,
                'pdf_url'        => $pdfUrl,
                'event_type'     => $eventType,
            ]);

            Log::info("invoice.paid: confirmation email sent for user {$user->id}");
        } catch (\Exception $e) {
            Log::error("invoice.paid: failed to send email to user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("invoice.paid processed for user {$user->id}");
    }

    /**
     * invoice.payment_failed — Marks user as overdue.
     */
    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        if (!$subscriptionId) return;

        $user = User::where('stripe_subscription_id', $subscriptionId)->first();
        if (!$user) return;

        $user->update(['status' => 'overdue']);

        // Send email notification via MailService
        try {
            $amount = ($invoice->amount_due ?? 0) / 100;
            $invoiceNumber = $invoice->number ?? 'N/A';
            $hostedUrl = $invoice->hosted_invoice_url ?? null;

            MailService::paymentFailed($user->email, [
                'name'           => $user->name,
                'amount'         => 'R$ ' . number_format($amount, 2, ',', '.'),
                'invoice_number' => $invoiceNumber,
                'payment_url'    => $hostedUrl,
            ]);

            Log::info("invoice.payment_failed: email sent for user {$user->id}");
        } catch (\Exception $e) {
            Log::error("invoice.payment_failed: failed to send email to user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("invoice.payment_failed: user {$user->id} marked as overdue");
    }

    /**
     * customer.subscription.updated — Syncs plan and status changes.
     */
    private function handleSubscriptionUpdated(object $subscription): void
    {
        $user = User::where('stripe_subscription_id', $subscription->id)->first();
        if (!$user) return;

        $updates = [];

        // Sync cancellation state
        if ($subscription->cancel_at_period_end) {
            $updates['status'] = 'cancelling';
            $updates['subscription_ends_at'] = \Carbon\Carbon::createFromTimestamp(
                $subscription->current_period_end
            );
        } elseif ($subscription->status === 'active') {
            $updates['status'] = 'active';
            $updates['subscription_ends_at'] = null;
        }

        // Sync plan if metadata contains plan_id
        $planId = $subscription->metadata->plan_id ?? null;
        if ($planId && $planId !== $user->platform_plan_id) {
            $updates['platform_plan_id'] = $planId;
        }

        if (!empty($updates)) {
            $user->update($updates);
            Log::info("subscription.updated: user {$user->id} synced", $updates);
        }
    }

    /**
     * customer.subscription.deleted — Deactivates user subscription.
     */
    private function handleSubscriptionDeleted(object $subscription): void
    {
        $user = User::where('stripe_subscription_id', $subscription->id)->first();
        if (!$user) return;

        $planName = $user->platformPlan?->name;

        $user->update([
            'status' => 'cancelled',
            'stripe_subscription_id' => null,
            'subscription_ends_at' => now(),
        ]);

        // Register cancellation event in platform_invoices
        PlatformInvoice::create([
            'user_id' => $user->id,
            'stripe_invoice_id' => 'cancel_' . $subscription->id . '_' . now()->timestamp,
            'invoice_number' => null,
            'amount' => 0,
            'status' => 'void',
            'event_type' => 'cancellation',
            'paid_at' => null,
            'description' => 'Cancelamento - ' . ($planName ?? 'Assinatura'),
        ]);

        // Send cancellation email via MailService
        try {
            MailService::subscriptionCancelled($user->email, [
                'name'      => $user->name,
                'plan_name' => $planName ?? 'Assinatura',
            ]);
            Log::info("subscription.deleted: cancellation email sent for user {$user->id}");
        } catch (\Exception $e) {
            Log::error("subscription.deleted: failed to send email to user {$user->id}", [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("subscription.deleted: user {$user->id} cancelled");
    }

    /**
     * checkout.session.completed — Activates subscription from checkout.
     */
    private function handleCheckoutCompleted(object $session): void
    {
        if ($session->mode !== 'subscription') return;

        $userId = $session->metadata->user_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            Log::warning('checkout.session.completed: missing metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $user = User::find($userId);
        if (!$user) return;

        $user->update([
            'platform_plan_id' => $planId,
            'stripe_subscription_id' => $session->subscription,
            'status' => 'active',
        ]);

        Log::info("checkout.session.completed: user {$userId} activated plan {$planId}");
    }
}
