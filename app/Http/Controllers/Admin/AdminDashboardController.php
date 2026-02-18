<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminDashboardController
 *
 * Dashboard administrativo da plataforma.
 * Consome dados de platform_invoices (faturas SaaS),
 * NÃO de charges (cobranças dos clientes).
 */
class AdminDashboardController extends Controller
{
    /**
     * Estatísticas gerais do dashboard.
     */
    public function index()
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Total de clientes (usuários da plataforma, excluindo sub-contas)
        $totalClients = User::whereNull('reseller_id')->count();
        $lastMonthClients = User::whereNull('reseller_id')
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $clientsChange = $totalClients - $lastMonthClients;

        // Planos ativos (assinaturas ativas na plataforma)
        $activePlans = User::whereNull('reseller_id')
            ->whereNotNull('platform_plan_id')
            ->where('status', 'active')
            ->count();
        $lastMonthActivePlans = User::whereNull('reseller_id')
            ->whereNotNull('platform_plan_id')
            ->where('status', 'active')
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $plansChange = $activePlans - $lastMonthActivePlans;

        // Tickets abertos
        $openTickets = DB::table('support_tickets')
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
        $lastMonthTickets = DB::table('support_tickets')
            ->whereIn('status', ['open', 'in_progress'])
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $ticketsChange = $openTickets - $lastMonthTickets;

        // Receita mensal (platform_invoices pagas no mês)
        $monthlyRevenue = PlatformInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $lastMonthRevenue = PlatformInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $revenueChangeNum = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;

        return response()->json([
            'total_clients'   => $totalClients,
            'active_plans'    => $activePlans,
            'open_tickets'    => $openTickets,
            'monthly_revenue' => round($monthlyRevenue, 2),
            'clients_change'  => ($clientsChange >= 0 ? '+' : '') . $clientsChange,
            'plans_change'    => ($plansChange >= 0 ? '+' : '') . $plansChange,
            'tickets_change'  => ($ticketsChange >= 0 ? '+' : '') . $ticketsChange,
            'revenue_change'  => ($revenueChangeNum >= 0 ? '+' : '') . $revenueChangeNum . '%',
        ]);
    }

    /**
     * Cronograma de Recebimentos — baseado em platform_invoices.
     *
     * Retorna faturas (pagas, pendentes, vencidas) do mês solicitado
     * para exibição no calendário do dashboard.
     */
    public function scheduledPayments(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $invoices = PlatformInvoice::with(['user.platformPlan'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->whereHas('user', function ($q) {
                $q->whereNull('reseller_id');
            })
            ->orderBy('due_date')
            ->get();

        $result = $invoices->map(function ($inv) {
            $status = 'expected';
            if ($inv->status === 'paid') {
                $status = 'confirmed';
            } elseif ($inv->status === 'overdue') {
                $status = 'overdue';
            }

            return [
                'id'          => (string) $inv->id,
                'date'        => $inv->due_date instanceof Carbon
                    ? $inv->due_date->toDateString()
                    : (string) $inv->due_date,
                'client_name' => $inv->user->name ?? 'N/A',
                'plan_name'   => $inv->user->platformPlan->name ?? 'N/A',
                'amount'      => (float) $inv->amount,
                'status'      => $status,
            ];
        });

        return response()->json($result);
    }

    /**
     * Atividade recente — últimos eventos de platform_invoices.
     */
    public function activity()
    {
        $invoices = PlatformInvoice::with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();

        $eventLabels = [
            'activation'   => 'Ativação de assinatura',
            'reactivation' => 'Reativação de assinatura',
            'renewal'      => 'Renovação de assinatura',
            'cancellation' => 'Cancelamento de assinatura',
        ];

        $statusLabels = [
            'paid'      => 'Pagamento recebido',
            'pending'   => 'Fatura pendente',
            'overdue'   => 'Fatura vencida',
            'cancelled' => 'Fatura cancelada',
        ];

        $result = $invoices->map(function ($inv) use ($eventLabels, $statusLabels) {
            $label = $eventLabels[$inv->event_type] ?? $statusLabels[$inv->status] ?? 'Fatura';
            $clientName = $inv->user->name ?? 'Cliente';

            return [
                'id'   => (string) $inv->id,
                'text' => "{$label} — {$clientName} (R$ " . number_format((float) $inv->amount, 2, ',', '.') . ")",
                'time' => $inv->created_at?->diffForHumans() ?? '',
                'type' => 'payment',
            ];
        });

        return response()->json($result);
    }

    /**
     * Tickets recentes.
     */
    public function tickets()
    {
        $tickets = DB::table('support_tickets')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'subject', 'status', 'priority']);

        return response()->json($tickets->map(fn ($t) => [
            'id'       => (string) $t->id,
            'subject'  => $t->subject,
            'status'   => $t->status,
            'priority' => $t->priority,
        ]));
    }

    /**
     * Distribuição por tipo de evento — baseado em platform_invoices.
     */
    public function eventDistribution()
    {
        $distribution = PlatformInvoice::select(
                'event_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->whereNotNull('event_type')
            ->groupBy('event_type')
            ->get();

        return response()->json($distribution->map(fn ($d) => [
            'event_type' => $d->event_type,
            'count'      => (int) $d->count,
            'total'      => (float) $d->total,
        ]));
    }
}
