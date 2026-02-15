<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargeRequest;
use App\Http\Requests\UpdateChargeRequest;
use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ChargeController extends Controller
{
    use AuthorizesRequests;
    /**
     * Lista paginada de cobranças com filtros
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Charge::byUser(Auth::id())->with('client');

        // Filtro de busca
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filtro de status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Filtro por método de pagamento
        if ($request->filled('payment_method')) {
            $query->byPaymentMethod($request->payment_method);
        }

        // Filtro por cliente
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // Filtro por período
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange(
                $request->input('date_from'),
                $request->input('date_to')
            );
        }

        // Ordenação
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = $request->input('per_page', 20);
        $charges = $query->paginate($perPage);

        return ChargeResource::collection($charges);
    }

    /**
     * Exibe detalhes de uma cobrança
     */
    public function show(Charge $charge): ChargeResource
    {
        $this->authorize('view', $charge);
        $charge->load('client');

        return new ChargeResource($charge);
    }

    /**
     * Retorna resumo das cobranças
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Charge::byUser(Auth::id());

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange(
                $request->input('date_from'),
                $request->input('date_to')
            );
        }

        $pending = (clone $query)->pending();
        $paid = (clone $query)->paid();
        $overdue = (clone $query)->overdue();

        return response()->json([
            'pending_count' => $pending->count(),
            'pending_amount' => (float) $pending->sum('amount'),
            'paid_count' => $paid->count(),
            'paid_amount' => (float) $paid->sum('amount'),
            'overdue_count' => $overdue->count(),
            'overdue_amount' => (float) $overdue->sum('amount'),
            'total_count' => $query->count(),
            'total_amount' => (float) $query->sum('amount'),
        ]);
    }

    /**
     * Cria uma nova cobrança
     */
    public function store(StoreChargeRequest $request): JsonResponse
    {
        $charge = Charge::create([
            'user_id' => Auth::id(),
            ...$request->validated(),
        ]);

        $payment = Payment::create([
            'user_id'       => Auth::id(),
            'client_id'     => $charge->client_id,
            'charge_id'     => $charge->id,
            'amount'        => $charge->amount,
            'net_amount'    => $charge->amount,
            'payment_method'=> $charge->payment_method,
            'status'        => 'pending',
            'description'   => $charge->description,
            'created_at'    => now(),
            'updated_at'    => now()
        ]);

        $charge->load('client');

        // Enviar notificação inicial se configurado
        if ($request->has('send_notification') && $request->send_notification) {
            $charge->sendNotification();
        }

        return (new ChargeResource($charge))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza uma cobrança existente
     */
    public function update(UpdateChargeRequest $request, Charge $charge): ChargeResource
    {
        $this->authorize('update', $charge);

        $charge->update($request->validated());
        $charge->load('client');

        return new ChargeResource($charge->fresh());
    }

    /**
     * Remove uma cobrança
     */
    public function destroy(Charge $charge): JsonResponse
    {
        $this->authorize('delete', $charge);

        // Não permite excluir cobranças pagas
        if ($charge->status === 'paid') {
            return response()->json([
                'message' => 'Cobranças pagas não podem ser excluídas',
            ], 422);
        }

        $charge->delete();

        return response()->json([
            'message' => 'Cobrança excluída com sucesso',
        ]);
    }

    /**
     * Atualiza o status da cobrança
     */
    public function updateStatus(Request $request, Charge $charge): ChargeResource
    {
        $this->authorize('update', $charge);

        $request->validate([
            'status' => 'required|in:pending,paid,overdue,cancelled',
        ]);



        $charge->update([
            'status' => $request->status,
            'paid_at' => $request->status === 'paid' ? now() : $charge->paid_at,
            'cancelled_at' => $request->status === 'cancelled' ? now() : $charge->cancelled_at,
        ]);

        Payment::where('charge_id', $charge->id)->update([
            'status' => $request->status,
            'paid_at' => $request->status === 'paid' ? now() : $charge->paid_at,
            'cancelled_at' => $request->status === 'cancelled' ? now() : $charge->cancelled_at,
        ]);
        return new ChargeResource($charge->fresh()->load('client'));
    }

    /**
     * Reenvia notificação da cobrança
     */
    public function resendNotification(Request $request, Charge $charge): ChargeResource
    {
        $this->authorize('update', $charge);

        if ($charge->status === 'paid') {
            return response()->json([
                'message' => 'Não é possível enviar notificação para cobranças pagas',
            ], 422);
        }

        $channels = $request->input('channels', $charge->notification_channels);
        $charge->sendNotification($channels);

        return new ChargeResource($charge->fresh()->load('client'));
    }

    /**
     * Marca a cobrança como paga
     */
    public function markAsPaid(Request $request, Charge $charge): ChargeResource
    {
        $this->authorize('update', $charge);

        Payment::where('charge_id', $charge->id)->update([
            'status' => 'paid',
            'paid_at' => $request->input('paid_at', now()),
        ]);

        $charge->update([
            'status' => 'paid',
            'paid_at' => $request->input('paid_at', now()),
        ]);

        return new ChargeResource($charge->fresh()->load('client'));
    }

    /**
     * Cancela a cobrança
     */
    public function cancel(Request $request, Charge $charge): ChargeResource
    {
        $this->authorize('update', $charge);

        if ($charge->status === 'paid') {
            return response()->json([
                'message' => 'Cobranças pagas não podem ser canceladas',
            ], 422);
        }

        $charge->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        $charge->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return new ChargeResource($charge->fresh()->load('client'));
    }
}
