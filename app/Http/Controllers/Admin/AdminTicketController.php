<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['user', 'messages.attachments']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            if ($priority !== 'all') $query->where('priority', $priority);
        }

        $tickets = $query->latest()->paginate($request->query('per_page', 10));

        return TicketResource::collection($tickets)->response();
    }

    public function show(SupportTicket $ticket): JsonResponse
    {
        $ticket->load(['user', 'messages.attachments']);
        return response()->json(new TicketResource($ticket));
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $request->validate(['message' => 'required|string']);

        $message = $ticket->messages()->create([
            'sender_type' => 'support',
            'sender_id' => $request->user()->id,
            'content' => $request->message,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        $ticket->update(['last_reply_at' => now()]);

        return response()->json(new TicketResource($ticket->fresh(['user', 'messages.attachments'])));
    }

    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $request->validate(['status' => 'required|in:open,in_progress,resolved,closed']);

        $ticket->update(['status' => $request->status]);

        return response()->json(new TicketResource($ticket->fresh(['user', 'messages.attachments'])));
    }

    public function close(SupportTicket $ticket): JsonResponse
    {
        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json(new TicketResource($ticket->fresh(['user', 'messages.attachments'])));
    }
}
