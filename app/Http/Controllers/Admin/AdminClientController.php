<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ClientResource;
use App\Models\Invoice;
use App\Models\PlatformPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('platformPlan');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'all') $query->where('status', $status);
        }

        if ($plan = $request->query('plan')) {
            if ($plan !== 'all') {
                $query->whereHas('platformPlan', fn($q) => $q->where('name', $plan));
            }
        }

        if ($planId = $request->query('plan_id')) {
            $query->where('platform_plan_id', $planId);
        }

        $clients = $query->latest()->paginate($request->query('per_page', 10));

        return ClientResource::collection($clients)->response();
    }

    public function show(User $client): JsonResponse
    {
        $client->load('platformPlan');
        return response()->json(new ClientResource($client));
    }

    public function update(Request $request, User $client): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $client->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'status' => 'sometimes|in:active,inactive,suspended',
            'platform_plan_id' => 'sometimes|nullable|exists:platform_plans,id',
        ]);

        $client->update($request->only(['name', 'email', 'phone', 'status', 'platform_plan_id']));

        return response()->json(new ClientResource($client->fresh('platformPlan')));
    }

    public function destroy(User $client): JsonResponse
    {
        // Soft-deactivate instead of hard delete
        $client->update(['status' => 'inactive']);

        return response()->json(['message' => 'Cliente desativado com sucesso']);
    }

    public function toggleStatus(User $client): JsonResponse
    {
        $client->update([
            'status' => $client->status === 'active' ? 'inactive' : 'active',
        ]);

        return response()->json(new ClientResource($client->fresh('platformPlan')));
    }

    public function assignPlan(Request $request, User $client): JsonResponse
    {
        $request->validate(['plan_id' => 'required|exists:platform_plans,id']);

        $plan = PlatformPlan::findOrFail($request->plan_id);
        $client->update(['platform_plan_id' => $plan->id]);

        Invoice::create([
            'user_id'           => $client->id,
            'plan_id'           => $plan->id,
            'amount'            => $plan->price,
            'status'            => 'pending',
            'platform_plan_id'  => $plan->id,
            'period'            => $plan->interval,
            'due_date'          => now()->addDays(5),
            'description'       => "Assinatura - {$plan->name}",
        ]);

        return response()->json(new ClientResource($client->fresh('platformPlan')));
    }



    public function impersonate(User $client): JsonResponse
    {
        $token = $client->createToken('impersonation-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'redirect_url' => config('app.frontend_url', 'https://cobgestmax.com') . '/dashboard',
        ]);
    }
}
