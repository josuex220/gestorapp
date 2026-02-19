<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MailService;
use App\Services\ResellerChargeService;
use App\Services\SubscriptionChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChargeController extends Controller
{
    /**
     * GET /api/charges
     * List charges for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.user_id', $user->id)
            ->orderBy('charges.created_at', 'desc');

        // Filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('clients.name', 'like', "%{$search}%")
                  ->orWhere('clients.email', 'like', "%{$search}%")
                  ->orWhere('charges.description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($paymentMethod = $request->input('payment_method')) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($paymentProvider = $request->input('payment_provider')) {
            $query->where('payment_provider', $paymentProvider);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('due_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('due_date', '<=', $dateTo);
        }

        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        $perPage = $request->input('per_page', 20);
        $charges = $query->paginate($perPage);

        // Transform each charge with computed fields
        $charges->getCollection()->transform(function ($charge) {
            return $this->transformCharge($charge);
        });

        return response()->json($charges);
    }

    /**
     * GET /api/charges/{id}
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $charge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->where('charges.user_id', $user->id)
            ->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        return response()->json($this->transformCharge($charge));
    }

    /**
     * POST /api/charges
     * Create a new charge.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'client_id'              => 'required|uuid|exists:clients,id',
            'amount'                 => 'required|numeric|min:0.01',
            'due_date'               => 'required|date',
            'payment_method'         => 'required|in:pix,boleto,credit_card',
            'payment_provider'       => 'nullable|in:mercado_pago,pix_manual',
            'description'            => 'nullable|string|max:500',
            'notification_channels'  => 'nullable|array',
            'notification_channels.*'=> 'in:email,whatsapp,telegram',
            'saved_card_id'          => 'nullable|string',
            'installments'           => 'nullable|integer|min:1|max:12',
            'subscription_id'        => 'nullable|string|exists:subscriptions,id',
        ]);

        // Get client info
        $client = DB::table('clients')->where('id', $validated['client_id'])->first();

        if (!$client || $client->user_id != $user->id) {
            return response()->json(['message' => 'Cliente não encontrado'], 404);
        }

        $id = (string) Str::uuid();

        DB::table('charges')->insert([
            'id'                    => $id,
            'user_id'               => $user->id,
            'client_id'             => $validated['client_id'],
            'subscription_id'       => $validated['subscription_id'] ?? null,
            'amount'                => $validated['amount'],
            'due_date'              => $validated['due_date'],
            'payment_method'        => $validated['payment_method'],
            'payment_provider'      => $validated['payment_provider'] ?? null,
            'status'                => 'pending',
            'description'           => $validated['description'] ?? null,
            'notification_channels' => json_encode($validated['notification_channels'] ?? ['email']),
            'saved_card_id'         => $validated['saved_card_id'] ?? null,
            'installments'          => $validated['installments'] ?? null,
            'notification_count'    => 0,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $charge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->first();

        // Enviar e-mail de nova cobrança ao cliente (se habilitado nas preferências)
        if ($client->email && MailService::isNotificationEnabled($request->user(), 'new_charge')) {
            MailService::chargeCreated($client->email, [
                'client_name'         => $client->name,
                'charge_amount'       => 'R$ ' . number_format($validated['amount'], 2, ',', '.'),
                'due_date'            => \Carbon\Carbon::parse($validated['due_date'])->format('d/m/Y'),
                'charge_description'  => $validated['description'] ?? 'Cobrança',
                'payment_link'        => "https://cobgestmax.com/pix/{$id}",
                'company_name'        => $request->user()->company_name ?? 'CobGest Max',
            ]);
        }

        return response()->json($this->transformCharge($charge), 201);
    }

    /**
     * PUT /api/charges/{id}
     * Update a charge.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $charge = DB::table('charges')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        $validated = $request->validate([
            'amount'                 => 'sometimes|numeric|min:0.01',
            'due_date'               => 'sometimes|date',
            'payment_method'         => 'sometimes|in:pix,boleto,credit_card',
            'payment_provider'       => 'nullable|in:mercado_pago,pix_manual',
            'description'            => 'nullable|string|max:500',
            'notification_channels'  => 'nullable|array',
            'notification_channels.*'=> 'in:email,whatsapp,telegram',
            'status'                 => 'sometimes|in:pending,paid,overdue,cancelled',
            'saved_card_id'          => 'nullable|string',
            'installments'           => 'nullable|integer|min:1|max:12',
        ]);

        $updateData = array_filter([
            'amount'                => $validated['amount'] ?? null,
            'due_date'              => $validated['due_date'] ?? null,
            'payment_method'        => $validated['payment_method'] ?? null,
            'payment_provider'      => array_key_exists('payment_provider', $validated) ? $validated['payment_provider'] : null,
            'description'           => array_key_exists('description', $validated) ? $validated['description'] : null,
            'notification_channels' => isset($validated['notification_channels']) ? json_encode($validated['notification_channels']) : null,
            'saved_card_id'         => array_key_exists('saved_card_id', $validated) ? $validated['saved_card_id'] : null,
            'installments'          => array_key_exists('installments', $validated) ? $validated['installments'] : null,
            'updated_at'            => now(),
        ], fn ($v) => $v !== null);

        // Handle status transitions
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] === 'paid') {
                $updateData['paid_at'] = now();
                ResellerChargeService::handleChargePaid($id);
                SubscriptionChargeService::handleSubscriptionChargePaid($id);
                $this->handleStandaloneChargePaid($id);
                $this->sendPaidEmail($id);
            } elseif ($validated['status'] === 'cancelled') {
                $updateData['cancelled_at'] = now();
            }
        }

        DB::table('charges')->where('id', $id)->update($updateData);

        $updatedCharge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->first();

        return response()->json($this->transformCharge($updatedCharge));
    }

    /**
     * DELETE /api/charges/{id}
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $deleted = DB::table('charges')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        return response()->json(['message' => 'Cobrança excluída com sucesso']);
    }

    /**
     * PATCH /api/charges/{id}/status
     * Update charge status.
     */
    public function updateStatus(Request $request, string $id)
    {
        $user = Auth::user();
        $charge = DB::table('charges')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,paid,overdue,cancelled',
        ]);

        $updateData = [
            'status'     => $validated['status'],
            'updated_at' => now(),
        ];

        if ($validated['status'] === 'paid') {
            $updateData['paid_at'] = now();
            // Auto-renew sub-account if this is a reseller charge
            ResellerChargeService::handleChargePaid($id);
            // Gerar Payment para cobranças de assinatura
            SubscriptionChargeService::handleSubscriptionChargePaid($id);
            // Gerar Payment para cobranças avulsas
            $this->handleStandaloneChargePaid($id);
            // Enviar e-mail de confirmação
            $this->sendPaidEmail($id);
        } elseif ($validated['status'] === 'cancelled') {
            $updateData['cancelled_at'] = now();
        }

        DB::table('charges')->where('id', $id)->update($updateData);

        $updatedCharge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->first();

        return response()->json($this->transformCharge($updatedCharge));
    }

    /**
     * POST /api/charges/{id}/resend
     * Resend notification for a charge.
     */
    public function resend(string $id)
    {
        $user = Auth::user();
        $charge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email')
            ->where('charges.id', $id)
            ->where('charges.user_id', $user->id)
            ->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        // Enviar e-mail de cobrança usando template do banco
        $channels = json_decode($charge->notification_channels ?? '["email"]', true);

        if (in_array('email', $channels) && $charge->client_email) {
            $dueDate = \Carbon\Carbon::parse($charge->due_date);
            $now = now()->startOfDay();
            $isOverdue = $dueDate->lt($now);

            // Escolher template baseado no status
            if ($isOverdue || $charge->status === 'overdue') {
                $slug = 'charge_overdue';
                $vars = [
                    'client_name'        => $charge->client_name ?? 'Cliente',
                    'company_name'       => $user->company_name ?? $user->name ?? 'CobGest Max',
                    'charge_description' => $charge->description ?? 'Cobrança',
                    'charge_amount'      => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
                    'due_date'           => $dueDate->format('d/m/Y'),
                    'days_overdue'       => (string) $dueDate->diffInDays($now),
                    'payment_link'       => $charge->mp_init_point ?? '#',
                ];
            } else {
                $slug = 'charge_created';
                $vars = [
                    'client_name'        => $charge->client_name ?? 'Cliente',
                    'company_name'       => $user->company_name ?? $user->name ?? 'CobGest Max',
                    'charge_description' => $charge->description ?? 'Cobrança',
                    'charge_amount'      => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
                    'due_date'           => $dueDate->format('d/m/Y'),
                    'payment_link'       => $charge->mp_init_point ?? '#',
                ];
            }

            MailService::sendTemplate($charge->client_email, $slug, $vars);
        }

        DB::table('charges')->where('id', $id)->update([
            'last_notification_at' => now(),
            'notification_count'   => ($charge->notification_count ?? 0) + 1,
            'updated_at'           => now(),
        ]);

        $updatedCharge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->first();

        return response()->json($this->transformCharge($updatedCharge));
    }

    /**
     * PATCH /api/charges/{id}/mark-paid
     * Manually mark a charge as paid.
     */
    public function markAsPaid(Request $request, string $id)
    {
        $user = Auth::user();
        $charge = DB::table('charges')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        $paidAt = $request->input('paid_at') ? $request->input('paid_at') : now();

        DB::table('charges')->where('id', $id)->update([
            'status'     => 'paid',
            'paid_at'    => $paidAt,
            'updated_at' => now(),
        ]);

        // Auto-renew reseller sub-account if applicable
        ResellerChargeService::handleChargePaid($id);
        // Gerar Payment para cobranças de assinatura
        SubscriptionChargeService::handleSubscriptionChargePaid($id);
        // Gerar Payment para cobranças avulsas
        $this->handleStandaloneChargePaid($id);
        // Enviar e-mail de confirmação
        $this->sendPaidEmail($id);

        $updatedCharge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->first();

        return response()->json($this->transformCharge($updatedCharge));
    }

    /**
     * PATCH /api/charges/{id}/cancel
     * Cancel a charge.
     */
    public function cancel(Request $request, string $id)
    {
        $user = Auth::user();
        $charge = DB::table('charges')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$charge) {
            return response()->json(['message' => 'Cobrança não encontrada'], 404);
        }

        DB::table('charges')->where('id', $id)->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'updated_at'   => now(),
        ]);

        $updatedCharge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email', 'clients.phone as client_phone')
            ->where('charges.id', $id)
            ->first();

        return response()->json($this->transformCharge($updatedCharge));
    }

     /* GET /api/charges/summary
     * Get summary stats for the authenticated user's charges.
     */
    public function summary(Request $request)
    {
        $user = Auth::user();

        $query = DB::table('charges')->where('user_id', $user->id);

        if ($dateFrom = $request->input('date_from')) {
            $query->where('due_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('due_date', '<=', $dateTo);
        }

        $charges = $query->get();

        return response()->json([
            'pending_count'    => $charges->where('status', 'pending')->count(),
            'pending_amount'   => (float) $charges->where('status', 'pending')->sum('amount'),
            'paid_count'       => $charges->where('status', 'paid')->count(),
            'paid_amount'      => (float) $charges->where('status', 'paid')->sum('amount'),
            'overdue_count'    => $charges->where('status', 'overdue')->count(),
            'overdue_amount'   => (float) $charges->where('status', 'overdue')->sum('amount'),
            'cancelled_count'  => $charges->where('status', 'cancelled')->count(),
            'cancelled_amount' => (float) $charges->where('status', 'cancelled')->sum('amount'),
            'total_count'      => $charges->count(),
            'total_amount'     => (float) $charges->sum('amount'),
        ]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    /**
     * Gera um registro de Payment para cobranças avulsas (sem subscription_id nem reseller).
     * Cobranças de assinatura e revenda já são tratadas pelos seus respectivos Services.
     */
    private function handleStandaloneChargePaid(string $chargeId): void
    {
        $charge = DB::table('charges')->where('id', $chargeId)->first();

        if (!$charge) {
            return;
        }

        // Pular se já é tratada por outro service (assinatura ou revenda)
        if ($charge->subscription_id || ($charge->reseller_account_id ?? null)) {
            return;
        }

        // Verificar se já existe Payment para esta cobrança
        $existingPayment = DB::table('payments')
            ->where('charge_id', $chargeId)
            ->exists();

        if ($existingPayment) {
            return;
        }

        // Calcular taxa baseada no método de pagamento
        $feeRates = [
            'pix' => 0.0099,        // 0.99%
            'boleto' => 0.0199,      // 1.99%
            'credit_card' => 0.0399, // 3.99%
        ];

        $feeRate = $feeRates[$charge->payment_method] ?? 0.02;
        $fee = round((float) $charge->amount * $feeRate, 2);
        $netAmount = round((float) $charge->amount - $fee, 2);

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $charge->user_id,
            'client_id' => $charge->client_id,
            'charge_id' => $chargeId,
            'subscription_id' => null,
            'plan_id' => null,
            'amount' => $charge->amount,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'payment_method' => $charge->payment_method,
            'status' => 'completed',
            'description' => $charge->description ?? 'Pagamento de cobrança avulsa',
            'transaction_id' => 'TXN_' . strtoupper(substr(md5($chargeId . now()), 0, 12)),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Envia e-mail de confirmação de pagamento ao cliente da cobrança.
     */
    private function sendPaidEmail(string $chargeId): void
    {
        $charge = DB::table('charges')
            ->leftJoin('clients', 'charges.client_id', '=', 'clients.id')
            ->select('charges.*', 'clients.name as client_name', 'clients.email as client_email')
            ->where('charges.id', $chargeId)
            ->first();

        if (!$charge || !$charge->client_email) {
            return;
        }

        $user = DB::table('users')->where('id', $charge->user_id)->first();

        $methodLabels = [
            'pix'         => 'PIX',
            'boleto'      => 'Boleto',
            'credit_card' => 'Cartão de Crédito',
        ];

        MailService::sendTemplate($charge->client_email, 'payment_confirmed', [
            'client_name'        => $charge->client_name ?? 'Cliente',
            'company_name'       => $user->company_name ?? $user->name ?? 'CobGest Max',
            'charge_description' => $charge->description ?? 'Cobrança',
            'charge_amount'      => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
            'payment_date'       => now()->format('d/m/Y H:i'),
            'payment_method'     => $methodLabels[$charge->payment_method] ?? $charge->payment_method,
        ]);
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    /**
     * Transform a raw charge DB record into the API response format.
     */
    private function transformCharge(object $charge): array
    {
        $dueDate = \Carbon\Carbon::parse($charge->due_date);
        $now = now()->startOfDay();
        $isOverdue = $charge->status === 'pending' && $dueDate->lt($now);
        $daysUntilDue = $dueDate->gte($now) ? $now->diffInDays($dueDate) : 0;
        $daysOverdue = $dueDate->lt($now) ? $dueDate->diffInDays($now) * -1 : null;

        $methodLabels = [
            'pix'         => 'PIX',
            'boleto'      => 'Boleto',
            'credit_card' => 'Cartão de Crédito',
        ];

        $statusLabels = [
            'pending'   => 'Pendente',
            'paid'      => 'Pago',
            'overdue'   => 'Vencido',
            'cancelled' => 'Cancelado',
        ];

        return [
            'id'                    => $charge->id,
            'user_id'               => $charge->user_id,
            'client_id'             => $charge->client_id,
            'subscription_id'       => $charge->subscription_id ?? null,
            'client_name'           => $charge->client_name,
            'client_email'          => $charge->client_email,
            'client_phone'          => $charge->client_phone ?? null,
            'amount'                => (float) $charge->amount,
            'due_date'              => $charge->due_date,
            'payment_method'        => $charge->payment_method,
            'payment_provider'      => $charge->payment_provider ?? null,
            'status'                => $charge->status,
            'description'           => $charge->description ?? null,
            'notification_channels' => json_decode($charge->notification_channels ?? '["email"]', true),
            'created_at'            => $charge->created_at,
            'updated_at'            => $charge->updated_at,
            'paid_at'               => $charge->paid_at ?? null,
            'cancelled_at'          => $charge->cancelled_at ?? null,
            'last_notification_at'  => $charge->last_notification_at ?? null,
            'notification_count'    => (int) ($charge->notification_count ?? 0),
            'saved_card_id'         => $charge->saved_card_id ?? null,
            'installments'          => $charge->installments ?? null,
            // Mercado Pago fields
            'mp_preference_id'      => $charge->mp_preference_id ?? null,
            'mp_payment_id'         => $charge->mp_payment_id ?? null,
            'mp_init_point'         => $charge->mp_init_point ?? null,
            'mp_sandbox_init_point' => $charge->mp_sandbox_init_point ?? null,
            // Reseller origin
            'reseller_charge_account_id' => $charge->reseller_charge_account_id ?? null,
            // Proof fields
            'proof_path'            => $charge->proof_path ?? null,
            'proof_uploaded_at'     => $charge->proof_uploaded_at ?? null,
            'client_confirmed_at'   => $charge->client_confirmed_at ?? null,
            // Computed
            'formatted_amount'      => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
            'is_overdue'            => $isOverdue,
            'days_until_due'        => $daysUntilDue,
            'days_overdue'          => $daysOverdue,
            'payment_method_label'  => $methodLabels[$charge->payment_method] ?? $charge->payment_method,
            'status_label'          => $statusLabels[$charge->status] ?? $charge->status,
        ];
    }
}
