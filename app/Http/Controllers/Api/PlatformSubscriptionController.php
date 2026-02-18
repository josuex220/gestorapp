<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminIntegration;
use App\Models\PlatformInvoice;
use App\Models\PlatformPlan;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformSubscriptionController extends Controller
{
    /**
     * Get the current user's subscription details
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('platformPlan');

        return response()->json([
            'plan' => $user->platformPlan,
            'status' => $user->status,
            'stripe_subscription_id' => $user->stripe_subscription_id,
            'subscription_ends_at' => $user->subscription_ends_at,
            'trial_ends_at' => $user->trial_ends_at,
        ]);
    }

    /**
     * Sync the user's subscription status from Stripe.
     * Called every time the user loads the dashboard.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_subscription_id) {
            return response()->json([
                'synced' => false,
                'reason' => 'no_stripe_subscription',
                'plan' => $user->platformPlan,
                'status' => $user->status,
            ]);
        }

        $stripeIntegration = AdminIntegration::where('name', 'stripe')
            ->where('connected', true)
            ->first();

        if (!$stripeIntegration) {
            return response()->json([
                'synced' => false,
                'reason' => 'stripe_not_configured',
                'plan' => $user->platformPlan,
                'status' => $user->status,
            ]);
        }

        $stripeSecretKey = $this->getStripeKey($stripeIntegration);
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        try {
            $subscription = \Stripe\Subscription::retrieve($user->stripe_subscription_id);

            $updates = [];

            // Sync status
            if ($subscription->status === 'active' && !$subscription->cancel_at_period_end) {
                $updates['status'] = 'active';
                $updates['subscription_ends_at'] = null;
            } elseif ($subscription->cancel_at_period_end) {
                $updates['status'] = 'cancelling';
                $updates['subscription_ends_at'] = \Carbon\Carbon::createFromTimestamp(
                    $subscription->current_period_end
                );
            } elseif (in_array($subscription->status, ['past_due', 'unpaid'])) {
                $updates['status'] = 'overdue';
            } elseif (in_array($subscription->status, ['canceled', 'incomplete_expired'])) {
                $updates['status'] = 'cancelled';
                $updates['stripe_subscription_id'] = null;
                $updates['subscription_ends_at'] = now();
            }

            // Sync plan from metadata
            $planId = $subscription->metadata->plan_id ?? null;
            if ($planId && $planId != $user->platform_plan_id) {
                $plan = PlatformPlan::find($planId);
                if ($plan) {
                    $updates['platform_plan_id'] = $planId;
                }
            }

            // Sync trial
            if ($subscription->trial_end) {
                $updates['trial_ends_at'] = \Carbon\Carbon::createFromTimestamp($subscription->trial_end);
            }

            if (!empty($updates)) {
                $user->update($updates);
                $user->refresh();
            }

            $user->load('platformPlan');

            return response()->json([
                'synced' => true,
                'plan' => $user->platformPlan,
                'status' => $user->status,
                'stripe_subscription_id' => $user->stripe_subscription_id,
                'subscription_ends_at' => $user->subscription_ends_at,
                'trial_ends_at' => $user->trial_ends_at,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'synced' => false,
                'reason' => 'stripe_error',
                'message' => $e->getMessage(),
                'plan' => $user->platformPlan,
                'status' => $user->status,
            ]);
        }
    }

    /**
     * Create a Stripe Checkout session for subscribing to a plan
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:platform_plans,id',
            'interval' => 'required|in:monthly,yearly',
        ]);

        $user = $request->user();
        $plan = PlatformPlan::findOrFail($request->plan_id);

        // Check upgrade only (no downgrade)
        if ($user->platformPlan && $user->platformPlan->price >= $plan->price) {
            return response()->json([
                'message' => 'Não é permitido fazer downgrade de plano. Escolha um plano superior ao atual.',
            ], 422);
        }

        // Get Stripe keys from admin_integrations
        $stripeIntegration = AdminIntegration::where('name', 'stripe')
            ->where('connected', true)
            ->first();

        if (!$stripeIntegration) {
            return response()->json([
                'message' => 'Stripe não está configurado. Contate o administrador.',
            ], 503);
        }

        $stripeSecretKey = $this->getStripeKey($stripeIntegration);
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Calculate price based on interval
        $unitAmount = $request->interval === 'yearly'
            ? (int) round($plan->price * 10 * 100) // ~17% discount, in centavos
            : (int) round($plan->price * 100);

        $recurringInterval = $request->interval === 'yearly' ? 'year' : 'month';

        // Determine trial eligibility
        $trialDays = null;
        $privileges = $plan->privileges ?? null;

        if (
            $privileges &&
            !empty($privileges->has_trial) &&
            !empty($privileges->trial_days) &&
            (int) $privileges->trial_days > 0 &&
            !$user->has_used_trial
        ) {
            $trialDays = (int) $privileges->trial_days;
        }


        try {
            $sessionParams = [
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'customer_email' => $user->email,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'interval' => $request->interval,
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'brl',
                        'product_data' => [
                            'name' => "Plano {$plan->name}",
                            'description' => "Assinatura {$request->interval} do plano {$plan->name}",
                        ],
                        'unit_amount' => $unitAmount,
                        'recurring' => [
                            'interval' => $recurringInterval,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => config('app.frontend_url') . '/assinatura?session_id={CHECKOUT_SESSION_ID}&status=success',
                'cancel_url' => config('app.frontend_url') . '/assinatura?status=cancelled',
            ];

            if (!is_null($trialDays)) {
                $sessionParams['subscription_data'] = [
                    'trial_period_days' => $trialDays,
                ];
            }

            $session = \Stripe\Checkout\Session::create($sessionParams);
            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar sessão de checkout: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm a checkout session and activate the subscription
     */
    public function confirmCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $stripeIntegration = AdminIntegration::where('name', 'stripe')
            ->where('connected', true)
            ->first();

        if (!$stripeIntegration) {
            return response()->json(['message' => 'Stripe não configurado.'], 503);
        }

        $stripeSecretKey = $this->getStripeKey($stripeIntegration);
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        try {
            $session = \Stripe\Checkout\Session::retrieve($request->session_id);

            if ($session->payment_status !== 'paid' && $session->status !== 'complete') {
                return response()->json(['message' => 'Pagamento não confirmado.'], 400);
            }

            $user = $request->user();
            $planId = $session->metadata->plan_id;

            $updateData = [
                'platform_plan_id' => $planId,
                'stripe_subscription_id' => $session->subscription,
                'status' => 'active',
            ];

            // Mark trial as used if subscription has a trial
            if ($session->subscription) {
                try {
                    $stripeSubscription = \Stripe\Subscription::retrieve($session->subscription);
                    if ($stripeSubscription->trial_end) {
                        $updateData['has_used_trial'] = true;
                        $updateData['trial_ends_at'] = \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end);
                    }
                } catch (\Exception $e) {
                    // Continue without trial info
                }
            }

            $user->update($updateData);

            $user->load('platformPlan');

            // Register checkout activation in platform_invoices
            $isReactivation = $session->metadata->reactivation ?? false;
            PlatformInvoice::create([
                'user_id' => $user->id,
                'stripe_invoice_id' => null,
                'amount' => $user->platformPlan ? $user->platformPlan->price : 0,
                'status' => 'paid',
                'currency' => 'brl',
                'description' => ($isReactivation ? 'Reativação' : 'Ativação') . ' de assinatura via checkout - Plano ' . ($user->platformPlan->name ?? 'N/A'),
                'event_type' => $isReactivation ? 'reactivation' : 'activation',
                'paid_at' => now(),
            ]);

            // Enviar e-mail de ativação/reativação
            MailService::subscriptionActivated($user->email, [
                'name'       => $user->name,
                'plan_name'  => $user->platformPlan->name ?? 'N/A',
                'event_type' => $isReactivation ? 'reactivation' : 'activation',
                'subject'    => $isReactivation ? 'Assinatura reativada!' : 'Assinatura ativada!',
            ]);

            return response()->json([
                'message' => 'Assinatura ativada com sucesso!',
                'plan' => $user->platformPlan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao confirmar checkout: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reactivate a cancelled or cancelling subscription.
     * - If cancelling (cancel_at_period_end): reverts the cancellation on Stripe.
     * - If cancelled but Stripe subscription still exists: tries to resume it.
     * - Otherwise: redirects to a new checkout session.
     */
    public function reactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->status, ['cancelled', 'cancelling'])) {
            return response()->json([
                'message' => 'Sua assinatura já está ativa.',
            ], 422);
        }

        $stripeIntegration = AdminIntegration::where('name', 'stripe')
            ->where('connected', true)
            ->first();

        if (!$stripeIntegration) {
            return response()->json(['message' => 'Stripe não configurado.'], 503);
        }

        $stripeSecretKey = $this->getStripeKey($stripeIntegration);
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Case 1: User still has a Stripe subscription (cancelling or recently cancelled)
        if ($user->stripe_subscription_id) {
            try {
                $subscription = \Stripe\Subscription::retrieve($user->stripe_subscription_id);

                // If subscription is still active but set to cancel at period end, just revert
                if ($subscription->status === 'active' && $subscription->cancel_at_period_end) {
                    \Stripe\Subscription::update($user->stripe_subscription_id, [
                        'cancel_at_period_end' => false,
                    ]);

                    $user->update([
                        'status' => 'active',
                        'subscription_ends_at' => null,
                    ]);

                    $user->load('platformPlan');

                    // Register reactivation event in platform_invoices
                    PlatformInvoice::create([
                        'user_id' => $user->id,
                        'amount' => 0,
                        'status' => 'paid',
                        'currency' => 'brl',
                        'description' => 'Reativação de assinatura (reversão de cancelamento) - Plano ' . ($user->platformPlan->name ?? 'N/A'),
                        'event_type' => 'reactivation',
                        'paid_at' => now(),
                    ]);

                    // Enviar e-mail de reativação
                    MailService::subscriptionActivated($user->email, [
                        'name'       => $user->name,
                        'plan_name'  => $user->platformPlan->name ?? 'N/A',
                        'event_type' => 'reactivation',
                        'subject'    => 'Assinatura reativada!',
                    ]);

                    return response()->json([
                        'reactivated' => true,
                        'method' => 'revert_cancellation',
                        'message' => 'Assinatura reativada com sucesso!',
                        'plan' => $user->platformPlan,
                        'status' => 'active',
                    ]);
                }

                // If subscription is canceled on Stripe but not yet expired, can't resume
                // Fall through to new checkout
            } catch (\Exception $e) {
                // Subscription not found on Stripe, fall through to new checkout
            }
        }

        // Case 2: Need a new checkout — validate plan_id
        $request->validate([
            'plan_id' => 'required|exists:platform_plans,id',
            'interval' => 'required|in:monthly,yearly',
        ]);

        $plan = PlatformPlan::findOrFail($request->plan_id);

        $unitAmount = $request->interval === 'yearly'
            ? (int) round($plan->price * 10 * 100)
            : (int) round($plan->price * 100);

        $recurringInterval = $request->interval === 'yearly' ? 'year' : 'month';

        try {
            // No trial on reactivation — user already used it
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'customer_email' => $user->email,
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'interval' => $request->interval,
                    'reactivation' => true,
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'brl',
                        'product_data' => [
                            'name' => "Plano {$plan->name}",
                            'description' => "Reativação - Assinatura {$request->interval} do plano {$plan->name}",
                        ],
                        'unit_amount' => $unitAmount,
                        'recurring' => [
                            'interval' => $recurringInterval,
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => config('app.frontend_url') . '/assinatura?session_id={CHECKOUT_SESSION_ID}&status=success',
                'cancel_url' => config('app.frontend_url') . '/assinatura?status=cancelled',
            ]);

            return response()->json([
                'reactivated' => false,
                'method' => 'new_checkout',
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar sessão de reativação: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel the user's subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_subscription_id) {
            // If no Stripe subscription, just update status
            $user->update([
                'status' => 'cancelled',
                'subscription_ends_at' => now()->endOfMonth(),
            ]);

            return response()->json([
                'message' => 'Assinatura cancelada. Acesso até o fim do período pago.',
                'ends_at' => $user->subscription_ends_at,
            ]);
        }

        $stripeIntegration = AdminIntegration::where('name', 'stripe')
            ->where('connected', true)
            ->first();

        if (!$stripeIntegration) {
            return response()->json(['message' => 'Stripe não configurado.'], 503);
        }

        $stripeSecretKey = $this->getStripeKey($stripeIntegration);
        \Stripe\Stripe::setApiKey($stripeSecretKey);

        try {
            // Cancel at period end (graceful cancellation)
            $subscription = \Stripe\Subscription::update(
                $user->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            );

            $endsAt = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);

            $user->update([
                'status' => 'cancelling',
                'subscription_ends_at' => $endsAt,
            ]);

            return response()->json([
                'message' => 'Assinatura será cancelada ao final do período.',
                'ends_at' => $endsAt->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao cancelar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the user's invoice history
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        // Try Stripe invoices first
        if ($user->stripe_subscription_id) {
            $stripeIntegration = AdminIntegration::where('name', 'stripe')
                ->where('connected', true)
                ->first();

            if ($stripeIntegration) {
                $stripeSecretKey = $this->getStripeKey($stripeIntegration);
                \Stripe\Stripe::setApiKey($stripeSecretKey);

                try {
                    // Find the Stripe customer
                    $subscription = \Stripe\Subscription::retrieve($user->stripe_subscription_id);
                    $invoices = \Stripe\Invoice::all([
                        'customer' => $subscription->customer,
                        'limit' => 24,
                    ]);

                    $result = collect($invoices->data)->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'number' => $invoice->number,
                            'amount' => $invoice->amount_paid / 100,
                            'status' => $invoice->status, // paid, open, void, uncollectible
                            'currency' => $invoice->currency,
                            'created_at' => \Carbon\Carbon::createFromTimestamp($invoice->created)->toISOString(),
                            'paid_at' => $invoice->status_transitions->paid_at
                                ? \Carbon\Carbon::createFromTimestamp($invoice->status_transitions->paid_at)->toISOString()
                                : null,
                            'pdf_url' => $invoice->invoice_pdf,
                            'hosted_url' => $invoice->hosted_invoice_url,
                            'description' => $invoice->lines->data[0]->description ?? 'Assinatura',
                        ];
                    });

                    return response()->json(['data' => $result]);
                } catch (\Exception $e) {
                    // Fall through to internal invoices
                }
            }
        }

        // Fallback: return internal invoices from 'invoices' table
        $invoices = $user->invoices()
            ->orderBy('created_at', 'desc')
            ->limit(24)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'amount' => $invoice->amount,
                    'status' => $invoice->status,
                    'currency' => 'brl',
                    'created_at' => $invoice->created_at->toISOString(),
                    'paid_at' => $invoice->paid_at?->toISOString(),
                    'pdf_url' => null,
                    'hosted_url' => null,
                    'description' => $invoice->description ?? 'Assinatura',
                ];
            });

        return response()->json(['data' => $invoices]);
    }

    /**
     * Extract the Stripe secret key from integration fields
     */
    private function getStripeKey(AdminIntegration $integration): string
    {
        $fields = is_string($integration->fields)
            ? json_decode($integration->fields, true)
            : $integration->fields;

        return $fields['secret_key'] ?? $fields['key_value'] ?? $integration->key_value;
    }
}
