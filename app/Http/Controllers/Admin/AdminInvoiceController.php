<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminInvoiceController
 *
 * Gerencia faturas da plataforma (tabela platform_invoices).
 * Estas são as faturas de assinatura dos clientes do SaaS,
 * NÃO as cobranças que os clientes criam para seus próprios clientes.
 *
 * Filtra automaticamente sub-contas (reseller_id) quando solicitado.
 */
class AdminInvoiceController extends Controller
{
    /**
     * Lista faturas da plataforma com filtros.
     */
    public function index(Request $request)
    {
        $query = PlatformInvoice::with(['user.platformPlan'])
            ->latest('created_at');

        // Excluir sub-contas de revendedores
        if ($request->boolean('exclude_reseller', false)) {
            $query->whereHas('user', function ($q) {
                $q->whereNull('reseller_id');
            });
        }

        // Filtro por busca (nome ou e-mail do cliente)
        if ($search = $request->input('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtro por tipo de evento
        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        // Filtro por período
        if ($period = $request->input('period')) {
            switch ($period) {
                case '7d':
                    $query->where('created_at', '>=', now()->subDays(7));
                    break;
                case '30d':
                    $query->where('created_at', '>=', now()->subDays(30));
                    break;
                case '90d':
                    $query->where('created_at', '>=', now()->subDays(90));
                    break;
            }
        }

        $perPage = $request->input('per_page', 10);
        $invoices = $query->paginate($perPage);

        // Transformar dados para o formato esperado pelo frontend
        $invoices->getCollection()->transform(function ($invoice) {
            return [
                'id'            => $invoice->id,
                'client_id'     => $invoice->user_id,
                'client_name'   => $invoice->user->name ?? 'N/A',
                'client_email'  => $invoice->user->email ?? '',
                'plan'          => $invoice->user->platformPlan->name ?? 'N/A',
                'amount'        => (float) $invoice->amount,
                'status'        => $invoice->status,
                'event_type'    => $invoice->event_type,
                'due_date'      => $invoice->due_date,
                'paid_at'       => $invoice->paid_at,
                'period'        => $invoice->period ?? '',
                'stripe_invoice_id' => $invoice->stripe_invoice_id,
                'created_at'    => $invoice->created_at,
                'updated_at'    => $invoice->updated_at,
            ];
        });

        return response()->json($invoices);
    }

    /**
     * Resumo financeiro das faturas.
     */
    public function summary(Request $request)
    {
        $baseQuery = PlatformInvoice::query();

        if ($request->boolean('exclude_reseller', false)) {
            $baseQuery->whereHas('user', function ($q) {
                $q->whereNull('reseller_id');
            });
        }

        $totalReceived = (clone $baseQuery)->where('status', 'paid')->sum('amount');
        $totalPending  = (clone $baseQuery)->where('status', 'pending')->sum('amount');
        $totalOverdue  = (clone $baseQuery)->where('status', 'overdue')->sum('amount');

        return response()->json([
            'total_received' => (float) $totalReceived,
            'total_pending'  => (float) $totalPending,
            'total_overdue'  => (float) $totalOverdue,
        ]);
    }

    /**
     * Contagem por tipo de evento.
     */
    public function eventCounts(Request $request)
    {
        $baseQuery = PlatformInvoice::query();

        if ($request->boolean('exclude_reseller', false)) {
            $baseQuery->whereHas('user', function ($q) {
                $q->whereNull('reseller_id');
            });
        }

        $counts = (clone $baseQuery)
            ->select('event_type', DB::raw('count(*) as total'))
            ->whereNotNull('event_type')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        return response()->json([
            'activation'    => $counts['activation'] ?? 0,
            'reactivation'  => $counts['reactivation'] ?? 0,
            'renewal'       => $counts['renewal'] ?? 0,
            'cancellation'  => $counts['cancellation'] ?? 0,
        ]);
    }

    /**
     * Detalhe de uma fatura específica.
     */
    public function show(string $id)
    {
        $invoice = PlatformInvoice::with(['user.platformPlan'])->findOrFail($id);

        return response()->json([
            'id'            => $invoice->id,
            'client_id'     => $invoice->user_id,
            'client_name'   => $invoice->user->name ?? 'N/A',
            'client_email'  => $invoice->user->email ?? '',
            'plan'          => $invoice->user->platformPlan->name ?? 'N/A',
            'amount'        => (float) $invoice->amount,
            'status'        => $invoice->status,
            'event_type'    => $invoice->event_type,
            'due_date'      => $invoice->due_date,
            'paid_at'       => $invoice->paid_at,
            'period'        => $invoice->period ?? '',
            'stripe_invoice_id' => $invoice->stripe_invoice_id,
            'created_at'    => $invoice->created_at,
            'updated_at'    => $invoice->updated_at,
        ]);
    }

    /**
     * Marcar fatura como paga.
     */
    public function markPaid(string $id)
    {
        $invoice = PlatformInvoice::findOrFail($id);
        $invoice->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        $invoice->load(['user.platformPlan']);

        return response()->json([
            'id'            => $invoice->id,
            'client_id'     => $invoice->user_id,
            'client_name'   => $invoice->user->name ?? 'N/A',
            'client_email'  => $invoice->user->email ?? '',
            'plan'          => $invoice->user->platformPlan->name ?? 'N/A',
            'amount'        => (float) $invoice->amount,
            'status'        => $invoice->status,
            'event_type'    => $invoice->event_type,
            'due_date'      => $invoice->due_date,
            'paid_at'       => $invoice->paid_at,
        ]);
    }

    /**
     * Cancelar fatura.
     */
    public function cancel(string $id)
    {
        $invoice = PlatformInvoice::findOrFail($id);
        $invoice->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Fatura cancelada com sucesso.']);
    }
}
