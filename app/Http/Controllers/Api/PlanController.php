<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{

    use AuthorizesRequests;
    /**
     * Lista paginada de planos com filtros
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Plan::byUser(Auth::id());

        // Filtro de busca
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filtro de status
        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->active(),
                'inactive' => $query->inactive(),
                default => null,
            };
        }

        // Filtro por categoria
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Filtro por ciclo
        if ($request->filled('cycle')) {
            $query->byCycle($request->cycle);
        }

        // Filtro por faixa de preco
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->priceRange(
                $request->input('min_price'),
                $request->input('max_price')
            );
        }

        // Ordenacao
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginacao
        $perPage = $request->input('per_page', 20);
        $plans = $query->paginate($perPage);

        return PlanResource::collection($plans);
    }

    /**
     * Exibe detalhes de um plano
     */
    public function show(Plan $plan): PlanResource
    {
        $this->authorize('view', $plan);

        return new PlanResource($plan);
    }

    /**
     * Cria um novo plano
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::create([
            'user_id' => Auth::id(),
            ...$request->validated(),
        ]);

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza um plano existente
     */
    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $this->authorize('update', $plan);

        $plan->update($request->validated());

        return new PlanResource($plan->fresh());
    }

    /**
     * Remove um plano
     */
    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        // Verifica se pode ser excluido
        if (!$plan->canBeDeleted()) {
            return response()->json([
                'message' => 'Este plano possui assinaturas ativas e nao pode ser excluido',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plano excluido com sucesso',
        ]);
    }

    /**
     * Alterna o status ativo/inativo do plano
     */
    public function toggleStatus(Plan $plan): PlanResource
    {
        $this->authorize('update', $plan);

        $plan->update([
            'is_active' => !$plan->is_active,
        ]);

        return new PlanResource($plan->fresh());
    }
}
