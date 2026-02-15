<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Http\Resources\TransactionResource;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Métricas principais
        $totalReceived = Payment::where('user_id', $userId)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $lastMonthReceived = Payment::where('user_id', $userId)
            ->whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $totalPending = Charge::where('user_id', $userId)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        $pendingCount = Charge::where('user_id', $userId)
            ->whereIn('status', ['pending', 'overdue'])
            ->count();

        $paymentsCount = Payment::where('user_id', $userId)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->count();

        $averageTicket = $paymentsCount > 0 ? $totalReceived / $paymentsCount : 0;

        // Cálculo de crescimento
        $growthPercentage = $lastMonthReceived > 0
            ? (($totalReceived - $lastMonthReceived) / $lastMonthReceived) * 100
            : 0;

        return response()->json([
            'metrics' => [
                'total_received' => round($totalReceived, 2),
                'total_pending' => round($totalPending, 2),
                'pending_count' => $pendingCount,
                'average_ticket' => round($averageTicket, 2),
                'growth_percentage' => round($growthPercentage, 1),
            ],
            'period' => [
                'start' => $startOfMonth->toDateString(),
                'end' => $endOfMonth->toDateString(),
            ],
        ]);
    }

    public function recentActivity(Request $request)
    {
        $userId = $request->user()->id;
        $limit = $request->get('limit', 10);

        // Combina pagamentos e cobranças em uma lista de transações
        $payments = Payment::with('client')
            ->where('user_id', $userId)
            ->orderBy('paid_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'type' => 'income',
                'name' => $p->client?->name ?? 'Cliente',
                'action' => 'Pagamento recebido',
                'date' => $p->paid_at ? $p->paid_at->translatedFormat('d M Y') : null,
                'amount' => $p->amount,
                'status' => 'success',
                'method' => $p->payment_method,
                'category' => 'Pagamento',
            ]);

        $pendingCharges = Charge::with('client')
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'type' => 'income',
                'name' => $c->client?->name ?? 'Cliente',
                'action' => $c->status === 'overdue' ? 'Cobrança vencida' : 'Cobrança pendente',
                'date' => $c->due_date->translatedFormat('d M Y'),
                'amount' => $c->amount,
                'status' => $c->status === 'overdue' ? 'failed' : 'pending',
                'method' => $c->payment_method,
                'category' => 'Cobrança',
            ]);

        // Merge e ordena por data
        $transactions = $payments->concat($pendingCharges)
            ->sortByDesc('date')
            ->take($limit)
            ->values();

        return response()->json(['transactions' => $transactions]);
    }

    public function weeklyChart(Request $request)
    {
        $userId = $request->user()->id;
        $startOfWeek = Carbon::now()->startOfWeek();

        $data = [];
        $days = ['SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SAB', 'DOM'];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);

            $income = Payment::where('user_id', $userId)
                ->whereDate('paid_at', $date)
                ->sum('amount');

            $data[] = [
                'day' => $days[$i],
                'date' => $date->toDateString(),
                'income' => round($income, 2),
                'expense' => 0, // Pode ser expandido para incluir despesas
            ];
        }

        return response()->json([
            'weekly_data' => $data,
            'total_week' => array_sum(array_column($data, 'income')),
        ]);
    }

    public function monthlyChart(Request $request)
    {
        $userId = $request->user()->id;
        $year = $request->get('year', Carbon::now()->year);

        $data = Payment::where('user_id', $userId)
            ->whereYear('paid_at', $year)
            ->select(
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('SUM(amount) as income')
            )
            ->groupBy(DB::raw('MONTH(paid_at)'))
            ->get()
            ->keyBy('month');

        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $chartData = [];

        for ($i = 1; $i <= 12; $i++) {
            $chartData[] = [
                'month' => $months[$i - 1],
                'income' => round($data->get($i)?->income ?? 0, 2),
                'expense' => 0,
            ];
        }

        return response()->json([
            'monthly_data' => $chartData,
            'year' => $year,
            'total_year' => array_sum(array_column($chartData, 'income')),
        ]);
    }

    public function upcomingPayments(Request $request)
    {
        $userId = $request->user()->id;
        $startDate = Carbon::now();
        $endDate = Carbon::now()->addDays(30);

        $charges = Charge::with('client')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->orderBy('due_date')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'client_name' => $c->client?->name,
                'description' => $c->description,
                'amount' => $c->amount,
                'due_date' => $c->due_date->toDateString(),
                'days_until' => $c->due_date->diffInDays(Carbon::now()),
            ]);

        return response()->json(['upcoming_payments' => $charges]);
    }

    public function summary(Request $request)
    {
        $userId = $request->user()->id;

        return response()->json([
            'clients' => [
                'total' => Client::where('user_id', $userId)->count(),
                'active' => Client::where('user_id', $userId)->where('is_active', true)->count(),
            ],
            'subscriptions' => [
                'total' => Subscription::where('user_id', $userId)->count(),
                'active' => Subscription::where('user_id', $userId)->where('status', 'active')->count(),
                'mrr' => Subscription::where('user_id', $userId)
                    ->where('status', 'active')
                    ->sum('amount'),
            ],
            'charges' => [
                'pending' => Charge::where('user_id', $userId)->where('status', 'pending')->count(),
                'overdue' => Charge::where('user_id', $userId)->where('status', 'overdue')->count(),
            ],
        ]);
    }
}
