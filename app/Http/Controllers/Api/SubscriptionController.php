<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\Plan;
use App\Services\MailService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    use AuthorizesRequests;
    /**
     * Lista paginada de assinaturas com filtros
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Subscription::byUser(Auth::id())->with(['client', 'plan']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->byStatus($request->status);
        }

        if ($request->filled('cycle') && $request->cycle !== 'all') {
            $query->byCycle($request->cycle);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange(
                $request->input('date_from'),
                $request->input('date_to')
            );
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 20);

        return SubscriptionResource::collection($query->paginate($perPage));
    }

    /**
     * Exibe detalhes de uma assinatura
     */
    public function show(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);
        $subscription->load(['client', 'plan']);

        return new SubscriptionResource($subscription);
    }

    /**
     * Retorna resumo das assinaturas (MRR, contagens por status)
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Subscription::byUser(Auth::id());

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange(
                $request->input('date_from'),
                $request->input('date_to')
            );
        }

        $active = (clone $query)->active();
        $suspended = (clone $query)->suspended();
        $cancelled = (clone $query)->cancelled();

        // Calcular MRR (Monthly Recurring Revenue)
        $mrr = $active->get()->sum(function ($sub) {
            return $sub->monthly_equivalent;
        });

        return response()->json([
            'total_count' => $query->count(),
            'active_count' => $active->count(),
            'suspended_count' => $suspended->count(),
            'cancelled_count' => $cancelled->count(),
            'mrr' => (float) $mrr,
        ]);
    }

    /**
     * Cria uma nova assinatura
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        // Se plan_id foi fornecido, busca dados do plano
        if (!empty($data['plan_id'])) {
            $plan = Plan::findOrFail($data['plan_id']);
            $data['plan_name'] = $plan->name;
            $data['plan_category'] = $plan->category;
            // Se amount não foi fornecido, usa o preço base do plano
            if (empty($data['amount'])) {
                $data['amount'] = $plan->base_price;
            }
            // Se cycle não foi fornecido, usa o ciclo do plano
            if (empty($data['cycle'])) {
                $data['cycle'] = $plan->cycle;
            }
        }

        // Calcular próxima data de cobrança baseado no ciclo
        $startDate = $data['start_date'] ?? now();
        $data['next_billing_date'] = $this->calculateNextBillingDate(
            $startDate,
            $data['cycle'],
            $data['custom_days'] ?? null
        );

        $subscription = Subscription::create($data);

        // Gerar primeira cobrança automaticamente
        $chargeId = (string) Str::uuid();
        $dueDate = $subscription->start_date ?? now()->format('Y-m-d');
        DB::table('charges')->insert([
            'id' => $chargeId,
            'user_id' => $subscription->user_id,
            'client_id' => $subscription->client_id,
            'subscription_id' => $subscription->id,
            'amount' => $subscription->amount,
            'due_date' => $dueDate,
            'payment_method' => 'pix',
            'status' => 'pending',
            'description' => "Cobrança inicial - {$subscription->plan_name}",
            'notification_channels' => json_encode(['email']),
            'notification_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription->load(['client', 'plan']);

        // Enviar e-mail de nova cobrança ao cliente
        $client = DB::table('clients')->where('id', $subscription->client_id)->first();
        if ($client && $client->email) {
            MailService::chargeCreated($client->email, [
                'name'           => $client->name,
                'amount'         => 'R$ ' . number_format($subscription->amount, 2, ',', '.'),
                'due_date'       => \Carbon\Carbon::parse($dueDate)->format('d/m/Y'),
                'description'    => "Cobrança inicial - {$subscription->plan_name}",
                'payment_method' => 'pix',
            ]);
        }

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Atualiza uma assinatura existente
     */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        $subscription->update($request->validated());

        return new SubscriptionResource($subscription->fresh()->load(['client', 'plan']));
    }

    /**
     * Remove uma assinatura
     */
    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return response()->json([
            'message' => 'Assinatura excluída com sucesso',
        ]);
    }

    /**
     * Atualiza o status da assinatura
     */
    public function updateStatus(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        $request->validate([
            'status' => 'required|in:active,suspended,cancelled',
        ]);

        $subscription->update(['status' => $request->status]);

        return new SubscriptionResource($subscription->fresh()->load(['client', 'plan']));
    }

    /**
     * Suspende uma assinatura
     */
    public function suspend(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        if ($subscription->status !== 'active') {
            abort(422, 'Apenas assinaturas ativas podem ser suspensas');
        }

        $subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $request->input('reason'),
        ]);

        // Notificar cliente sobre suspensão
        $client = DB::table('clients')->where('id', $subscription->client_id)->first();
        if ($client && $client->email) {
            MailService::send($client->email, 'Assinatura suspensa', 'subscription_suspended', [
                'name'      => $client->name,
                'plan_name' => $subscription->plan_name,
                'reason'    => $request->input('reason') ?? 'Não informado',
            ]);
        }

        return new SubscriptionResource($subscription->fresh()->load(['client', 'plan']));
    }

    /**
     * Reativa uma assinatura suspensa
     */
    public function reactivate(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        if ($subscription->status !== 'suspended') {
            abort(422, 'Apenas assinaturas suspensas podem ser reativadas');
        }

        $subscription->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
            'next_billing_date' => $this->calculateNextBillingDate(
                now(),
                $subscription->cycle,
                $subscription->custom_days
            ),
        ]);

        // Notificar cliente sobre reativação
        $client = DB::table('clients')->where('id', $subscription->client_id)->first();
        if ($client && $client->email) {
            MailService::subscriptionActivated($client->email, [
                'name'      => $client->name,
                'plan_name' => $subscription->plan_name,
                'subject'   => 'Assinatura reativada',
            ]);
        }

        return new SubscriptionResource($subscription->fresh()->load(['client', 'plan']));
    }

    /**
     * Cancela uma assinatura
     */
    public function cancel(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        if ($subscription->status === 'cancelled') {
            abort(422, 'Assinatura já está cancelada');
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        // Notificar cliente sobre cancelamento
        $client = DB::table('clients')->where('id', $subscription->client_id)->first();
        if ($client && $client->email) {
            MailService::subscriptionCancelled($client->email, [
                'name'      => $client->name,
                'plan_name' => $subscription->plan_name,
            ]);
        }

        return new SubscriptionResource($subscription->fresh()->load(['client', 'plan']));
    }

    /**
     * Calcula a próxima data de cobrança baseada no ciclo
     */
    private function calculateNextBillingDate($startDate, string $cycle, ?int $customDays = null): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::parse($startDate);

        return match ($cycle) {
            'weekly' => $date->addWeek(),
            'biweekly' => $date->addWeeks(2),
            'monthly' => $date->addMonth(),
            'quarterly' => $date->addMonths(3),
            'semiannual' => $date->addMonths(6),
            'annual' => $date->addYear(),
            'custom' => $date->addDays($customDays ?? 30),
            default => $date->addMonth(),
        };
    }
}
