<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->with(['messages' => fn($q) => $q->latest()->limit(1)])
            ->when($request->status, fn($q, $status) => $q->byStatus($status))
            ->when($request->search, fn($q, $search) =>
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%")
            )
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        return response()->json($tickets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'       => 'required|string|max:255',
            'category'      => 'required|in:cobrancas,pagamentos,clientes,integracoes,sugestoes,outros',
            'priority'      => 'in:low,medium,high',
            'message'       => 'required|string',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        $message = $ticket->messages()->create([
            'sender_type' => 'user',
            'sender_id' => $request->user()->id,
            'content' => $validated['message'],
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store(
                    "tickets/{$ticket->id}/messages/{$message->id}",
                    'public'
                );

                $message->attachments()->create([
                    'path'              => $path,
                    'filename'          => $file->getClientOriginalName(),
                    'original_name'     => $file->getClientOriginalName(),
                    'mime_type'         => $file->getClientMimeType(),
                    'size'              => $file->getSize(),
                ]);
            }
        }

        return response()->json($ticket->load('messages'), 201);
    }

    public function show(SupportTicket $ticket)
    {

        return response()->json(
            $ticket->load(['messages' => fn($q) => $q->visibleToUser()->orderBy('created_at')->with('attachments')])
        );
    }

    public function reply(Request $request, SupportTicket $ticket)
    {

        $validated = $request->validate([
            'message' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        $message = $ticket->messages()->create([
            'sender_type' => 'user',
            'sender_id' => $request->user()->id,
            'content' => $validated['message'],

        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store(
                    "tickets/{$ticket->id}/messages/{$message->id}",
                    'public'
                );

                $message->attachments()->create([
                    'path'              => $path,
                    'filename'          => $file->getClientOriginalName(),
                    'original_name'     => $file->getClientOriginalName(),
                    'mime_type'         => $file->getClientMimeType(),
                    'size'              => $file->getSize(),
                ]);
            }
        }

        $ticket->update(['last_reply_at' => now()]);

        return response()->json($message, 201);
    }

    public function close(SupportTicket $ticket)
    {

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json($ticket);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        // Total de tickets do usuário
        $totalTickets = SupportTicket::where('user_id', $userId)->count();

        // Tickets resolvidos/fechados
        $resolvedTickets = SupportTicket::where('user_id', $userId)
            ->whereIn('status', ['resolved', 'closed'])
            ->count();

        // Taxa de resolução (percentual)
        $resolutionRate = $totalTickets > 0
            ? round(($resolvedTickets / $totalTickets) * 100)
            : 0;

        // Tempo médio de resposta (primeira resposta do suporte)
        $avgResponseTime = $this->calculateAverageResponseTime($userId);

        return response()->json([
            'open_tickets' => SupportTicket::where('user_id', $userId)->open()->count(),
            'total_tickets' => $totalTickets,
            'avg_response_time' => $avgResponseTime,
            'resolution_rate' => $resolutionRate,
        ]);
    }

    /**
     * Calcula o tempo médio de primeira resposta do suporte
     */
    private function calculateAverageResponseTime(int $userId): string
    {
        $tickets = SupportTicket::where('user_id', $userId)
            ->whereHas('messages', fn($q) => $q->where('sender_type', 'support'))
            ->with(['messages' => fn($q) => $q->orderBy('created_at')])
            ->get();

        if ($tickets->isEmpty()) {
            return '-';
        }

        $totalMinutes = 0;
        $count = 0;

        foreach ($tickets as $ticket) {
            // Primeira mensagem do usuário (criação do ticket)
            $firstUserMessage = $ticket->messages
                ->where('sender_type', 'user')
                ->first();

            // Primeira resposta do suporte
            $firstSupportMessage = $ticket->messages
                ->where('sender_type', 'support')
                ->first();

            if ($firstUserMessage && $firstSupportMessage) {
                $diff = $firstUserMessage->created_at->diffInMinutes($firstSupportMessage->created_at);
                $totalMinutes += $diff;
                $count++;
            }
        }

        if ($count === 0) {
            return '-';
        }

        $avgMinutes = $totalMinutes / $count;

        // Formatar o tempo
        if ($avgMinutes < 60) {
            return round($avgMinutes) . 'min';
        } elseif ($avgMinutes < 1440) { // menos de 24h
            return round($avgMinutes / 60, 1) . 'h';
        } else {
            return round($avgMinutes / 1440, 1) . 'd';
        }
    }

}
