<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResellerNotificationSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResellerController extends Controller
{
    /**
     * Mapeia um User (sub-conta) para o formato esperado pelo frontend (ResellerAccount).
     */
    private function mapAccountResponse(User $account): array
    {
        $credits = $account->reseller_credits ?? 0;
        $creditsUsed = $account->clients()->count() ?? 0;

        return [
            'id'           => $account->id,
            'name'         => $account->name,
            'email'        => $account->email,
            'phone'        => $account->phone,
            'credits'      => $credits,
            'credits_used' => $creditsUsed,
            'price'        => $account->reseller_price !== null ? (float) $account->reseller_price : null,
            'expires_at'   => $account->reseller_expires_at?->toISOString(),
            'status'       => $account->status,
            'created_at'   => $account->created_at->toISOString(),
        ];
    }

    /**
     * GET /api/reseller/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $plan = $user->platformPlan;

        $resellerCredits = $plan?->privileges['max_clients'] ?? 0;
        $isUnlimited     = $resellerCredits === -1;
        $subAccounts     = User::where('reseller_id', $user->id)->get();
        $totalAccounts   = $subAccounts->count();
        $activeAccounts  = $subAccounts->where('status', 'active')->count();

        // Créditos usados = soma dos reseller_credits atribuídos a cada sub-conta
        $creditsUsed = 0;
        foreach ($subAccounts as $sub) {
            $subCredits = $sub->reseller_credits ?? 0;
            if ($subCredits > 0) {
                $creditsUsed += $subCredits;
            }
        }

        return response()->json([
            'total_accounts'   => $totalAccounts,
            'active_accounts'  => $activeAccounts,
            'credits_used'     => $creditsUsed,
            'credits_total'    => $isUnlimited ? -1 : $resellerCredits,
            'credits_remaining'=> $isUnlimited ? -1 : max(0, $resellerCredits - $creditsUsed),
        ]);
    }

    /**
     * GET /api/reseller/report
     */
    public function report(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = User::where('reseller_id', $user->id)->get();

        $totalAccounts   = $accounts->count();
        $activeAccounts  = $accounts->where('status', 'active')->count();
        $inactiveAccounts = $totalAccounts - $activeAccounts;
        $totalRevenue    = $accounts->sum('reseller_price') ?? 0;
        $avgPrice        = $totalAccounts > 0 ? $totalRevenue / $totalAccounts : 0;

        $expiringSoon = $accounts
            ->filter(fn ($a) => $a->reseller_expires_at && $a->reseller_expires_at > now() && $a->reseller_expires_at <= now()->addDays(30))
            ->sortBy('reseller_expires_at')
            ->map(fn ($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'email'          => $a->email,
                'price'          => (float) ($a->reseller_price ?? 0),
                'expires_at'     => $a->reseller_expires_at->toISOString(),
                'days_remaining' => (int) now()->diffInDays($a->reseller_expires_at, false),
                'status'         => $a->status,
            ])
            ->values();

        $expired = $accounts
            ->filter(fn ($a) => $a->reseller_expires_at && $a->reseller_expires_at <= now())
            ->sortByDesc('reseller_expires_at')
            ->take(10)
            ->map(fn ($a) => [
                'id'             => $a->id,
                'name'           => $a->name,
                'email'          => $a->email,
                'price'          => (float) ($a->reseller_price ?? 0),
                'expires_at'     => $a->reseller_expires_at->toISOString(),
                'days_remaining' => (int) now()->diffInDays($a->reseller_expires_at, false),
                'status'         => $a->status,
            ])
            ->values();

        return response()->json([
            'total_accounts'   => $totalAccounts,
            'active_accounts'  => $activeAccounts,
            'inactive_accounts' => $inactiveAccounts,
            'total_revenue'    => round($totalRevenue, 2),
            'average_price'    => round($avgPrice, 2),
            'expiring_soon'    => $expiringSoon,
            'expired'          => $expired,
        ]);
    }

    /**
     * GET /api/reseller/accounts
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('reseller_id', $request->user()->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Bloqueio automático: desativar sub-contas com validade expirada
        User::where('reseller_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNotNull('reseller_expires_at')
            ->where('reseller_expires_at', '<', now())
            ->update(['status' => 'inactive']);

        $paginated = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        // Mapear cada conta para o formato esperado pelo frontend
        $mapped = collect($paginated->items())->map(fn ($account) => $this->mapAccountResponse($account));

        return response()->json([
            'data'         => $mapped,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
        ]);
    }

    /**
     * POST /api/reseller/accounts
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $plan = $user->platformPlan;
        $resellerCredits = $plan?->privileges['max_clients'] ?? 0;
        $isUnlimited = $resellerCredits === -1;
        // Calcular créditos já atribuídos (soma dos reseller_credits de cada sub-conta)
        $usedCredits = 0;
        $subAccounts = User::where('reseller_id', $user->id)->get();
        foreach ($subAccounts as $sub) {
            $subCredits = $sub->reseller_credits ?? 0;
            if ($subCredits > 0) {
                $usedCredits += $subCredits;
            }
        }

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable|string|max:20',
            'password'   => 'nullable|string|min:6|max:100',
            'credits'    => 'required|integer|min:-1',
            'price'      => 'nullable|numeric|min:0|max:999999.99',
            'expires_at' => 'nullable|date|after:today|before_or_equal:' . now()->addYears(5)->toDateString(),
        ], [
            'price.numeric'          => 'O preço deve ser um valor numérico.',
            'price.min'              => 'O preço não pode ser negativo.',
            'price.max'              => 'O preço máximo permitido é R$ 999.999,99.',
            'expires_at.date'        => 'A data de validade deve ser uma data válida.',
            'expires_at.after'       => 'A data de validade deve ser posterior a hoje.',
            'expires_at.before_or_equal' => 'A data de validade não pode ultrapassar 5 anos.',
        ]);

        $requestedCredits = $validated['credits'];

        // Créditos ilimitados (-1) só podem ser atribuídos por revendedores com plano ilimitado
        if ($requestedCredits === -1 && !$isUnlimited) {
            throw ValidationException::withMessages([
                'credits' => ['Apenas revendedores com plano ilimitado podem atribuir créditos ilimitados.'],
            ]);
        }

        // Verificar limite de créditos apenas se o revendedor NÃO tem plano ilimitado
        if (!$isUnlimited && $requestedCredits > 0) {
            $remaining = max(0, $resellerCredits - $usedCredits);
            if ($requestedCredits > $remaining) {
                throw ValidationException::withMessages([
                    'credits' => ["Créditos insuficientes. Disponível: {$remaining}, solicitado: {$requestedCredits}."],
                ]);
            }
        }

        $account = User::create([
            'name'                => $validated['name'],
            'email'               => $validated['email'],
            'phone'               => $validated['phone'] ?? null,
            'password'            => Hash::make($validated['password'] ?? Str::random(16)),
            'reseller_id'         => $user->id,
            'reseller_price'      => $validated['price'] ?? null,
            'reseller_credits'    => $requestedCredits,
            'reseller_expires_at' => $validated['expires_at'] ?? null,
            'platform_plan_id'    => $user->platform_plan_id,
            'status'              => 'active',
        ]);

        return response()->json($this->mapAccountResponse($account), 201);
    }

    /**
     * PUT /api/reseller/accounts/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = User::where('id', $id)
            ->where('reseller_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => "sometimes|email|unique:users,email,{$account->id}",
            'phone'      => 'nullable|string|max:20',
            'password'   => 'nullable|string|min:6|max:100',
            'credits'    => 'sometimes|integer|min:-1',
            'price'      => 'nullable|numeric|min:0|max:999999.99',
            'expires_at' => 'nullable|date|after_or_equal:today|before_or_equal:' . now()->addYears(5)->toDateString(),
        ], [
            'price.numeric'          => 'O preço deve ser um valor numérico.',
            'price.min'              => 'O preço não pode ser negativo.',
            'price.max'              => 'O preço máximo permitido é R$ 999.999,99.',
            'expires_at.date'        => 'A data de validade deve ser uma data válida.',
            'expires_at.after_or_equal' => 'A data de validade deve ser a partir de hoje.',
            'expires_at.before_or_equal' => 'A data de validade não pode ultrapassar 5 anos.',
        ]);

        // Se password foi enviado, hashear
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Se credits foi enviado, salvar diretamente no campo reseller_credits
        if (isset($validated['credits'])) {
            $validated['reseller_credits'] = $validated['credits'];
            unset($validated['credits']);
        }

        // Mapear campos do frontend para campos do banco
        if (array_key_exists('price', $validated)) {
            $validated['reseller_price'] = $validated['price'];
            unset($validated['price']);
        }
        if (array_key_exists('expires_at', $validated)) {
            $validated['reseller_expires_at'] = $validated['expires_at'];
            unset($validated['expires_at']);

            // Se a validade foi estendida e a conta está inativa, reativar
            if ($validated['reseller_expires_at'] && $account->status === 'inactive') {
                $expiresAt = new \DateTime($validated['reseller_expires_at']);
                if ($expiresAt > now()) {
                    $validated['status'] = 'active';
                }
            }
        }

        $account->update($validated);
        $account->refresh();

        return response()->json($this->mapAccountResponse($account));
    }

    /**
     * DELETE /api/reseller/accounts/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $account = User::where('id', $id)
            ->where('reseller_id', $request->user()->id)
            ->firstOrFail();

        // Verificar se a sub-conta tem clientes cadastrados
        $clientsCount = $account->clients()->count();
        if ($clientsCount > 0) {
            return response()->json([
                'message' => "Esta sub-conta possui {$clientsCount} cliente(s) cadastrado(s). Remova os clientes antes de excluir a conta.",
                'error' => 'has_clients',
                'clients_count' => $clientsCount,
            ], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Sub-conta excluída com sucesso.']);
    }

    /**
     * PATCH /api/reseller/accounts/{id}/toggle-status
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $account = User::where('id', $id)
            ->where('reseller_id', $request->user()->id)
            ->firstOrFail();

        $newStatus = $account->status === 'active' ? 'inactive' : 'active';
        $account->update(['status' => $newStatus]);

        // Cascata: se o revendedor foi desativado, desativar todas as sub-contas dele
        if ($newStatus === 'inactive') {
            User::where('reseller_id', $account->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
        }

        return response()->json($this->mapAccountResponse($account));
    }

    /**
     * PATCH /api/reseller/accounts/{id}/renew
     */
    public function renew(Request $request, string $id): JsonResponse
    {
        $account = User::where('id', $id)
            ->where('reseller_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:1825',
        ], [
            'days.min' => 'O período mínimo de renovação é 1 dia.',
            'days.max' => 'O período máximo de renovação é 5 anos (1825 dias).',
        ]);

        $days = $validated['days'] ?? 30;
        $oldExpiry = $account->reseller_expires_at;
        $baseDate = $oldExpiry && $oldExpiry > now()
            ? new \DateTime($oldExpiry)
            : now();

        $newExpiry = $baseDate->modify("+{$days} days");

        $account->update([
            'reseller_expires_at' => $newExpiry,
            'status'              => 'active',
        ]);

        // Registrar log de renovação
        \App\Models\ResellerRenewalLog::create([
            'account_id'     => $account->id,
            'renewed_by'     => $request->user()->id,
            'days'           => $days,
            'old_expires_at' => $oldExpiry,
            'new_expires_at' => $newExpiry,
        ]);

        return response()->json($this->mapAccountResponse($account));
    }

    /**
     * GET /api/reseller/accounts/{id}/renewal-history
     */
    public function renewalHistory(Request $request, string $id): JsonResponse
    {
        $account = User::where('id', $id)
            ->where('reseller_id', $request->user()->id)
            ->firstOrFail();

        $logs = \App\Models\ResellerRenewalLog::where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($logs);
    }

    /**
     * GET /api/reseller/notification-settings
     */
    public function getNotificationSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->resellerNotificationSettings;

        if (!$settings) {
            return response()->json(ResellerNotificationSetting::defaults());
        }

        return response()->json([
            'enabled'    => $settings->enabled,
            'alert_days' => $settings->alert_days,
            'channels'   => $settings->channels,
        ]);
    }

    /**
     * PUT /api/reseller/notification-settings
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled'             => 'required|boolean',
            'alert_days'          => 'required|array|min:0',
            'alert_days.*'        => 'integer|min:1|max:365',
            'channels'            => 'required|array',
            'channels.email'      => 'required|boolean',
        ], [
            'alert_days.*.min'  => 'O prazo mínimo de alerta é 1 dia.',
            'alert_days.*.max'  => 'O prazo máximo de alerta é 365 dias.',
        ]);

        $settings = ResellerNotificationSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'enabled'    => $validated['enabled'],
                'alert_days' => $validated['alert_days'],
                'channels'   => $validated['channels'],
            ]
        );

        return response()->json([
            'enabled'    => $settings->enabled,
            'alert_days' => $settings->alert_days,
            'channels'   => $settings->channels,
        ]);
    }
}
