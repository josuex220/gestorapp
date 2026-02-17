<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Http\Resources\Admin\PlanResource;
use App\Models\PlatformPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlatformPlan::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            if ($status === 'active') $query->where('active', true);
            elseif ($status === 'inactive') $query->where('active', false);
        }

        $plans = $query->latest()->paginate($request->query('per_page', 10));

        return PlanResource::collection($plans)->response();
    }

    public function show(PlatformPlan $plan): JsonResponse
    {
        return response()->json(new PlanResource($plan));
    }

    public function store(PlanRequest $request): JsonResponse
    {
        $plan = PlatformPlan::create($request->validated());

        return response()->json(new PlanResource($plan), 201);
    }

    public function update(PlanRequest $request, PlatformPlan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return response()->json(new PlanResource($plan));
    }

    public function destroy(PlatformPlan $plan): JsonResponse
    {
        if ($plan->users()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir um plano com assinantes ativos.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Plano removido com sucesso']);
    }
}
