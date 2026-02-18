<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    use AuthorizesRequests;
    /**
     * Lista paginada de pagamentos com filtros
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::byUser(Auth::id())->with(['client', 'charge', 'subscription']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->byStatus($request->status);
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->byPaymentMethod($request->payment_method);
        }

        if ($request->filled('plan_category') && $request->plan_category !== 'all') {
            $query->byCategory($request->plan_category);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filtro por origem: charge (avulsa), subscription, reseller
        if ($request->filled('source') && $request->source !== 'all') {
            match ($request->source) {
                'charge' => $query->whereNotNull('charge_id')->whereNull('subscription_id'),
                'subscription' => $query->whereNotNull('subscription_id'),
                'reseller' => $query->where(function ($q) {
                    $q->whereHas('charge', function ($cq) {
                        $cq->whereNotNull('reseller_account_id');
                    })->orWhereHas('plan', function ($pq) {
                        $pq->where('category', 'reseller');
                    });
                }),
                default => null,
            };
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange(
                $request->input('date_from'),
                $request->input('date_to')
            );
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 20);

        return PaymentResource::collection($query->paginate($perPage));
    }

    /**
     * Exibe detalhes de um pagamento
     */
    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);
        $payment->load(['client', 'charge', 'subscription']);

        return new PaymentResource($payment);
    }

    /**
     * Retorna resumo financeiro dos pagamentos
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Payment::byUser(Auth::id());

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange(
                $request->input('date_from'),
                $request->input('date_to')
            );
        }

        $completed = (clone $query)->completed();
        $pending = (clone $query)->pending();
        $refunded = (clone $query)->refunded();
        $failed = (clone $query)->failed();

        $totalReceived = $completed->sum('net_amount');
        $totalPending = $pending->sum('amount');
        $totalFees = $completed->sum('fee');
        $totalRefunded = $refunded->sum('amount');
        $transactionsCount = $completed->count();

        // Cálculo de crescimento (comparar com período anterior)
        $growthPercentage = $this->calculateGrowth($request);

        return response()->json([
            'total_received' => (float) $totalReceived,
            'total_pending' => (float) $totalPending,
            'total_fees' => (float) $totalFees,
            'total_refunded' => (float) $totalRefunded,
            'transactions_count' => $transactionsCount,
            'average_ticket' => $transactionsCount > 0
                ? (float) ($totalReceived / $transactionsCount)
                : 0,
            'growth_percentage' => $growthPercentage,
            'failed_count' => $failed->count(),
        ]);
    }

    /**
     * Retorna receita mensal para gráficos
     */
    public function monthlyRevenue(Request $request): JsonResponse
    {
        $months = $request->input('months', 6);
        $userId = Auth::id();

        $revenue = Payment::byUser($userId)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw("SUM(CASE WHEN status IN ('completed', 'paid') THEN net_amount ELSE 0 END) as received"),
                DB::raw("SUM(CASE WHEN status IN ('pending','overdue') THEN amount ELSE 0 END) as pending")
            )
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => \Carbon\Carbon::createFromFormat('Y-m', $item->month)
                        ->locale('pt_BR')
                        ->isoFormat('MMM'),
                    'received' => (float) $item->received,
                    'pending' => (float) $item->pending,
                ];
            });

        return response()->json($revenue);
    }

    /**
     * Registra um pagamento manual
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        // Calcular taxa e valor líquido baseado no método de pagamento
        $feeRate = $this->getFeeRate($data['payment_method']);
        $data['fee'] = $data['amount'] * $feeRate;
        $data['net_amount'] = $data['amount'] - $data['fee'];

        // Gerar transaction_id único
        $data['transaction_id'] = 'TXN_' . strtoupper(bin2hex(random_bytes(4)));

        $payment = Payment::create($data);
        $payment->load(['client', 'charge', 'subscription']);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Reembolsa um pagamento
     */
    public function refund(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('update', $payment);

        if ($payment->status !== 'completed') {
            abort(422, 'Apenas pagamentos concluídos podem ser reembolsados');
        }

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_reason' => $request->input('reason'),
        ]);

        return new PaymentResource($payment->fresh()->load(['client', 'charge', 'subscription']));
    }

    /**
     * Calcula a taxa baseada no método de pagamento
     */
    private function getFeeRate(string $paymentMethod): float
    {
        return match ($paymentMethod) {
            'pix' => 0.00,           // 2%
            'boleto' => 0.00,        // 1%
            'credit_card' => 0.00,   // 3%
            'debit_card' => 0.00,   // 1.5%
            'transfer' => 0.00,     // 0.5%
            default => 0.00,
        };
    }

    /**
     * Calcula o crescimento percentual comparado ao período anterior
     */
    private function calculateGrowth(Request $request): float
    {
        $userId = Auth::id();

        // Se tem filtro de data, comparar com período anterior equivalente
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = \Carbon\Carbon::parse($request->date_from);
            $to = \Carbon\Carbon::parse($request->date_to);
            $days = $from->diffInDays($to);

            $currentTotal = Payment::byUser($userId)
                ->completed()
                ->whereBetween('created_at', [$from, $to])
                ->sum('net_amount');

            $previousFrom = $from->copy()->subDays($days + 1);
            $previousTo = $from->copy()->subDay();

            $previousTotal = Payment::byUser($userId)
                ->completed()
                ->whereBetween('created_at', [$previousFrom, $previousTo])
                ->sum('net_amount');
        } else {
            // Comparar mês atual com mês anterior
            $currentTotal = Payment::byUser($userId)
                ->completed()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('net_amount');

            $previousTotal = Payment::byUser($userId)
                ->completed()
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('net_amount');
        }

        if ($previousTotal == 0) {
            return $currentTotal > 0 ? 100 : 0;
        }

        return round((($currentTotal - $previousTotal) / $previousTotal) * 100, 2);
    }
}
