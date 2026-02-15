<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;

class UpdateSessionActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->user()) {
            // Atualizar last_active_at da sessão atual
            $session = UserSession::where('user_id', $request->user()->id)
                ->where('is_current', true)
                ->first();

            if ($session) {
                $session->updateActivity();
            }
        }

        return $response;
    }

}
