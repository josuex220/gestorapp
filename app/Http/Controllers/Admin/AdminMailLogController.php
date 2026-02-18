<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminMailLogController
 *
 * Lista os logs de e-mails enviados pela plataforma (tabela mail_logs).
 * A tabela é populada automaticamente pelo MailService após cada envio.
 *
 * GET /api/admin/mail-logs
 * Query params: search, status, event, page, per_page
 */
class AdminMailLogController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('mail_logs')->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('to', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        $perPage = min((int) $request->input('per_page', 10), 50);

        return response()->json($query->paginate($perPage));
    }
}
