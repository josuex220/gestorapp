<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Admin\PlanResource;
use App\Models\PlatformPlan;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class PlatformPlanController extends Controller
{
     /**
     * Lista planos ativos da plataforma (endpoint público)
     */
    public function index(): JsonResponse
    {
        $plans = PlatformPlan::where('active', true)
            ->orderBy('price', 'asc')
            ->get();

        return PlanResource::collection($plans)->response();
    }

    /**
     * Exibe detalhes de um plano específico
     */
    public function show(PlatformPlan $plan): JsonResponse
    {
        if (!$plan->active) {
            return response()->json(['message' => 'Plano não encontrado'], 404);
        }

        return response()->json(new PlanResource($plan));
    }
}
