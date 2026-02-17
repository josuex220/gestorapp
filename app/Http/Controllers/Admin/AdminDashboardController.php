<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\PlatformInvoice;
use App\Models\PlatformPlan;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();

        $totalClients = User::count();
        $lastMonthClients = User::where('created_at', '<', $now->copy()->startOfMonth())->count();
        $clientsChange = $lastMonthClients > 0
            ? '+' . round((($totalClients - $lastMonthClients) / $lastMonthClients) * 100) . '%'
            : '+0%';

        $activePlans = PlatformPlan::where('active', true)->count();
        $openTickets = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();

        $monthlyRevenue = Charge::where('status', 'paid')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');

        $lastMonthRevenue = Charge::where('status', 'paid')
            ->whereMonth('paid_at', $lastMonth->month)
            ->whereYear('paid_at', $lastMonth->year)
            ->sum('amount');

        $revenueChange = $lastMonthRevenue > 0
            ? '+' . round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100) . '%'
            : '+0%';

        return response()->json([
            'total_clients' => $totalClients,
            'active_plans' => $activePlans,
            'open_tickets' => $openTickets,
            'monthly_revenue' => (float) $monthlyRevenue,
            'clients_change' => $clientsChange,
            'plans_change' => '+' . $activePlans,
            'tickets_change' => '-' . $openTickets,
            'revenue_change' => $revenueChange,
        ]);
    }

    public function scheduledPayments(Request $request): JsonResponse
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);

        $charges = Charge::with(['user', 'client'])
            ->whereMonth('due_date', $date->month)
            ->whereYear('due_date', $date->year)
            ->orderBy('due_date')
            ->get();

        $payments = $charges->map(function ($charge) {
            $status = 'expected';
            if ($charge->status === 'paid') $status = 'confirmed';
            elseif ($charge->due_date < Carbon::now() && $charge->status !== 'paid') $status = 'overdue';

            return [
                'id' => $charge->id,
                'date' => $charge->due_date->format('Y-m-d'),
                'client_name' => $charge->client?->name ?? $charge->user?->name ?? 'N/A',
                'plan_name' => $charge->description ?? 'Cobrança',
                'amount' => (float) $charge->amount,
                'status' => $status,
            ];
        });

        return response()->json($payments);
    }

    public function activity(): JsonResponse
    {
        $activities = collect();

        User::latest()->take(5)->get()->each(function ($user) use (&$activities) {
            $activities->push([
                'id' => 'act_client_' . $user->id,
                'text' => 'Novo cliente cadastrado: ' . $user->name,
                'time' => $user->created_at->diffForHumans(),
                'type' => 'client',
            ]);
        });

        SupportTicket::latest()->take(5)->get()->each(function ($ticket) use (&$activities) {
            $activities->push([
                'id' => 'act_ticket_' . $ticket->id,
                'text' => 'Ticket: ' . $ticket->subject,
                'time' => $ticket->created_at->diffForHumans(),
                'type' => 'ticket',
            ]);
        });

        Charge::where('status', 'paid')->latest('paid_at')->take(5)->get()->each(function ($charge) use (&$activities) {
            $activities->push([
                'id' => 'act_payment_' . $charge->id,
                'text' => 'Pagamento recebido: R$ ' . number_format($charge->amount, 2, ',', '.'),
                'time' => $charge->paid_at?->diffForHumans() ?? '',
                'type' => 'payment',
            ]);
        });

        return response()->json(
            $activities->sortByDesc('time')->take(10)->values()
        );
    }

    public function tickets(): JsonResponse
    {
        $tickets = SupportTicket::whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => ucfirst($t->status),
                'priority' => ucfirst($t->priority),
            ]);

        return response()->json($tickets);
    }

    /**
     * Distribution of platform invoice events by type.
     */
    public function eventDistribution(): JsonResponse
    {
        $distribution = PlatformInvoice::selectRaw('event_type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('event_type')
            ->get()
            ->map(fn($row) => [
                'event_type' => $row->event_type ?? 'activation',
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ]);

        return response()->json($distribution);
    }
}
