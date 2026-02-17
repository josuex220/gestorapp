<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminIntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        $integrations = AdminIntegration::all()->map(fn($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'description' => $i->description,
            'key_label' => $i->key_label,
            'key_value' => $i->masked_key_value,
            'connected' => $i->connected,
            'fields' => $i->masked_fields,
            'created_at' => $i->created_at?->toISOString(),
            'updated_at' => $i->updated_at?->toISOString(),
        ]);

        return response()->json($integrations);
    }

    public function update(Request $request, AdminIntegration $integration): JsonResponse
    {
        // Support both legacy (key_value) and new (fields) format
        if ($request->has('fields')) {
            $request->validate(['fields' => 'required|array']);

            $incomingFields = $request->input('fields');
            $currentFields = is_string($integration->fields)
                ? json_decode($integration->fields, true)
                : ($integration->fields ?? []);

            // Merge: skip masked values (containing asterisks)
            foreach ($incomingFields as $key => $value) {
                if (is_string($value) && str_contains($value, '***')) {
                    continue; // Don't overwrite with masked value
                }
                $currentFields[$key] = $value;
            }

            $integration->update([
                'fields' => $currentFields,
                'connected' => true,
            ]);
        } else {
            // Legacy single key_value format
            $request->validate(['key_value' => 'required|string']);

            $integration->update([
                'key_value' => $request->key_value,
                'connected' => true,
            ]);
        }

        return response()->json([
            'id' => $integration->id,
            'name' => $integration->name,
            'description' => $integration->description,
            'key_label' => $integration->key_label,
            'key_value' => $integration->masked_key_value,
            'connected' => $integration->connected,
            'fields' => $integration->masked_fields,
        ]);
    }

    public function disconnect(AdminIntegration $integration): JsonResponse
    {
        $integration->update([
            'key_value' => null,
            'fields' => null,
            'connected' => false,
        ]);

        return response()->json(['message' => 'Integração desconectada com sucesso']);
    }

    public function test(AdminIntegration $integration): JsonResponse
    {
        // Check both legacy key_value and new fields format
        $hasLegacyKey = !empty($integration->key_value);
        $hasFields = false;

        $fields = is_string($integration->fields)
            ? json_decode($integration->fields, true)
            : ($integration->fields ?? []);

        if (!empty($fields)) {
            // Check if at least one required field has a value
            $hasFields = collect($fields)->filter(fn($v) => !empty($v))->isNotEmpty();
        }

        if (!$hasLegacyKey && !$hasFields) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma chave configurada.',
            ]);
        }

        $slug = $integration->slug ?? strtolower(str_replace(' ', '', $integration->name));

        // Real connectivity tests per integration
        try {
            return match ($slug) {
                'stripe' => $this->testStripe($fields),
                'mercadopago' => $this->testMercadoPago($fields),
                default => response()->json([
                    'success' => true,
                    'message' => 'Chaves salvas com sucesso.',
                ]),
            };
        } catch (\Exception $e) {
            Log::error("Integration test failed for {$slug}", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
            ]);
        }
    }

    private function testStripe(array $fields): JsonResponse
    {
        $secretKey = $fields['secret_key'] ?? null;

        if (empty($secretKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Secret Key não configurada.',
            ]);
        }

        \Stripe\Stripe::setApiKey($secretKey);
        $account = \Stripe\Account::retrieve();

        $status = $account->settings->dashboard->display_name ?? $account->id;

        return response()->json([
            'success' => true,
            'message' => "Conectado à conta: {$status}",
        ]);
    }

    private function testMercadoPago(array $fields): JsonResponse
    {
        $clientId = $fields['client_id'] ?? null;
        $clientSecret = $fields['client_secret'] ?? null;

        if (empty($clientId) || empty($clientSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID e Client Secret são obrigatórios.',
            ]);
        }

        $response = \Illuminate\Support\Facades\Http::post('https://api.mercadopago.com/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->successful() && $response->json('access_token')) {
            return response()->json([
                'success' => true,
                'message' => 'Conexão com Mercado Pago verificada com sucesso.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Credenciais do Mercado Pago inválidas.',
        ]);
    }
}
