<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InvoiceResource;
use App\Models\Charge;
use App\Models\PlatformInvoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Charge::with(['user', 'client']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') $query->where('status', $status);
        }

        if ($eventType = $request->query('event_type')) {
            if ($eventType !== 'all') $query->where('event_type', $eventType);
        }

        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        $charges = $query->latest()->paginate($request->query('per_page', 10));

        return InvoiceResource::collection($charges)->response();
    }

    public function show(Charge $charge): JsonResponse
    {
        $charge->load(['user', 'client']);
        return response()->json(new InvoiceResource($charge));
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'total_received' => (float) Charge::where('status', 'paid')->sum('amount'),
            'total_pending' => (float) Charge::where('status', 'pending')->sum('amount'),
            'total_overdue' => (float) Charge::where('status', 'overdue')->sum('amount'),
        ]);
    }

    /**
     * Event type distribution counts for the invoices/transactions page.
     */
    public function eventCounts(): JsonResponse
    {
        $counts = PlatformInvoice::selectRaw('event_type, COUNT(*) as count')
            ->whereNotNull('event_type')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');

        return response()->json([
            'activation' => (int) ($counts['activation'] ?? 0),
            'reactivation' => (int) ($counts['reactivation'] ?? 0),
            'renewal' => (int) ($counts['renewal'] ?? 0),
            'cancellation' => (int) ($counts['cancellation'] ?? 0),
        ]);
    }

    public function markPaid(Charge $charge): JsonResponse
    {
        $charge->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        return response()->json(new InvoiceResource($charge->fresh(['user', 'client'])));
    }

    public function cancel(Charge $charge): JsonResponse
    {
        $charge->update(['status' => 'cancelled']);

        return response()->json(new InvoiceResource($charge->fresh(['user', 'client'])));
    }
}
