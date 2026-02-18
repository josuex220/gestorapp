<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckClientLimit
{
    /**
     * Verifica se o usuário atingiu o limite de clientes do seu plano.
     * Deve ser aplicado nas rotas de criação de clientes (POST /api/clients).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Sub-conta de revendedor: usar reseller_credits como limite
        if ($user && $user->reseller_id) {
            $maxClients = $user->reseller_credits;

            // null ou -1 = ilimitado
            if ($maxClients !== null && $maxClients !== -1) {
                $currentCount = $user->clients()->count();

                if ($currentCount >= $maxClients) {
                    return response()->json([
                        'message' => 'Você atingiu o limite de clientes da sua conta. Entre em contato com seu revendedor para aumentar o limite.',
                        'error' => 'client_limit_reached',
                        'current' => $currentCount,
                        'limit' => $maxClients,
                    ], 403);
                }
            }

            return $next($request);
        }

        // Usuário direto: usar limite do plano
        $plan = $user?->platformPlan;

        if ($plan) {
            $maxClients = $plan->privileges['max_clients'] ?? null;

            // null = ilimitado
            if ($maxClients !== null && $maxClients !== -1) {
                // Se o usuário é revendedor, descontar créditos alocados às sub-contas
                $allocatedToSubAccounts = \App\Models\User::where('reseller_id', $user->id)
                    ->sum('reseller_credits') ?? 0;

                $availableForOwnClients = max(0, $maxClients - $allocatedToSubAccounts);
                $currentCount = $user->clients()->count();

                if ($currentCount >= $availableForOwnClients) {
                    $message = $allocatedToSubAccounts > 0
                        ? "Você atingiu o limite de clientes. Seus créditos ({$maxClients}) estão distribuídos: {$allocatedToSubAccounts} para sub-contas e {$availableForOwnClients} para uso próprio."
                        : 'Você atingiu o limite de clientes do seu plano. Faça upgrade para cadastrar mais clientes.';

                    return response()->json([
                        'message' => $message,
                        'error' => 'client_limit_reached',
                        'current' => $currentCount,
                        'limit' => $availableForOwnClients,
                        'allocated_to_sub_accounts' => $allocatedToSubAccounts,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
