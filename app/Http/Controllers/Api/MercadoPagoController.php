<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnectMercadoPagoRequest;
use App\Http\Requests\CreateMercadoPagoPreferenceRequest;
use App\Http\Requests\UpdateMercadoPagoConfigRequest;
use App\Models\Charge;
use App\Models\MercadoPagoConfig;
use App\Models\MercadoPagoLog;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoController extends Controller
{
    public function __construct(
        private MercadoPagoService $mpService
    ) {}

    /**
     * GET /api/integrations/mercadopago/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        $config = MercadoPagoConfig::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'accepted_payment_methods' => [
                    'credit_card' => true,
                    'debit_card' => true,
                    'pix' => true,
                    'boleto' => true,
                ],
                'accepted_brands' => ['visa', 'mastercard', 'elo', 'amex', 'hipercard'],
            ]
        );

        return response()->json([
            'id' => $config->id,
            'is_connected' => $config->is_connected,
            'is_sandbox' => $config->is_sandbox,
            'public_key' => $config->public_key,
            'masked_access_token' => $config->masked_access_token,
            'accepted_payment_methods' => $config->accepted_payment_methods,
            'accepted_brands' => $config->accepted_brands,
            'max_installments' => $config->max_installments,
            'statement_descriptor' => $config->statement_descriptor,
            'updated_at' => $config->updated_at,
        ]);
    }

    /**
     * PUT /api/integrations/mercadopago/config
     */
    public function updateConfig(UpdateMercadoPagoConfigRequest $request): JsonResponse
    {
        $config = MercadoPagoConfig::where('user_id', $request->user()->id)->firstOrFail();

        $config->update($request->validated());

        MercadoPagoLog::record($request->user()->id, 'config_updated', 'success', [
            'request_payload' => $request->validated(),
        ]);

        return response()->json([
            'message' => 'Configuracoes atualizadas com sucesso',
            'config' => [
                'id' => $config->id,
                'is_connected' => $config->is_connected,
                'is_sandbox' => $config->is_sandbox,
                'public_key' => $config->public_key,
                'accepted_payment_methods' => $config->accepted_payment_methods,
                'accepted_brands' => $config->accepted_brands,
                'max_installments' => $config->max_installments,
                'statement_descriptor' => $config->statement_descriptor,
            ],
        ]);
    }

    /**
     * POST /api/integrations/mercadopago/connect
     */
    public function connect(ConnectMercadoPagoRequest $request): JsonResponse
    {
        $config = MercadoPagoConfig::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        $config->access_token = $request->access_token;
        $config->public_key = $request->public_key;
        $config->save();

        // Testar conexao
        $testResult = $this->mpService->testConnection($config);

        if ($testResult['success']) {
            $config->update(['is_connected' => true]);

            MercadoPagoLog::record($request->user()->id, 'connection_test', 'success', [
                'response_payload' => $testResult,
            ]);

            return response()->json([
                'message' => 'Mercado Pago conectado com sucesso',
                'is_connected' => true,
            ]);
        }

        // Falhou - limpar tokens
        $config->update([
            'access_token' => null,
            'public_key' => null,
            'is_connected' => false,
        ]);

        MercadoPagoLog::record($request->user()->id, 'connection_test', 'error', [
            'error_message' => $testResult['message'],
        ]);

        return response()->json([
            'message' => $testResult['message'],
            'is_connected' => false,
        ], 422);
    }

    /**
     * DELETE /api/integrations/mercadopago/disconnect
     */
    public function disconnect(Request $request): JsonResponse
    {
        $config = MercadoPagoConfig::where('user_id', $request->user()->id)->firstOrFail();

        $config->update([
            'access_token' => null,
            'public_key' => null,
            'is_connected' => false,
        ]);

        MercadoPagoLog::record($request->user()->id, 'config_updated', 'success', [
            'request_payload' => ['action' => 'disconnect'],
        ]);

        return response()->json([
            'message' => 'Mercado Pago desconectado com sucesso',
        ]);
    }

    /**
     * POST /api/integrations/mercadopago/test
     */
    public function testConnection(Request $request): JsonResponse
    {
        $config = MercadoPagoConfig::where('user_id', $request->user()->id)->firstOrFail();

        if (!$config->is_connected) {
            return response()->json([
                'message' => 'Mercado Pago nao esta conectado',
                'success' => false,
            ], 422);
        }

        $result = $this->mpService->testConnection($config);

        MercadoPagoLog::record(
            $request->user()->id,
            'connection_test',
            $result['success'] ? 'success' : 'error',
            [
                'response_payload' => $result,
                'error_message' => $result['success'] ? null : $result['message'],
            ]
        );

        if (!$result['success']) {
            $config->update(['is_connected' => false]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/integrations/mercadopago/preferences
     */
    public function createPreference(CreateMercadoPagoPreferenceRequest $request): JsonResponse
    {
        $config = MercadoPagoConfig::where('user_id', $request->user()->id)->firstOrFail();

        if (!$config->is_connected) {
            return response()->json([
                'message' => 'Mercado Pago nao esta conectado',
            ], 422);
        }

        $charge = Charge::where('user_id', $request->user()->id)
            ->findOrFail($request->charge_id);

        try {
            $preference = $this->mpService->createPreference($config, $charge);

            // Salvar referencia na charge
            $charge->update([
                'mp_preference_id' => $preference['id'],
                'mp_init_point' => $config->is_sandbox
                    ? $preference['sandbox_init_point']
                    : $preference['init_point'],
                'mp_sandbox_init_point' => $preference['sandbox_init_point'],
            ]);

            MercadoPagoLog::record($request->user()->id, 'preference_created', 'success', [
                'charge_id' => $charge->id,
                'request_payload' => ['charge_id' => $charge->id, 'amount' => $charge->amount],
                'response_payload' => [
                    'preference_id' => $preference['id'],
                    'init_point' => $preference['init_point'],
                ],
            ]);

            return response()->json([
                'preference_id' => $preference['id'],
                'init_point' => $preference['init_point'],
                'sandbox_init_point' => $preference['sandbox_init_point'],
            ]);
        } catch (\Exception $e) {
            MercadoPagoLog::record($request->user()->id, 'preference_created', 'error', [
                'charge_id' => $charge->id,
                'error_message' => $e->getMessage(),
                'request_payload' => ['charge_id' => $charge->id],
            ]);

            return response()->json([
                'message' => 'Erro ao gerar link de pagamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/integrations/mercadopago/logs
     */
    public function getLogs(Request $request): JsonResponse
    {
        $query = MercadoPagoLog::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('type') && $request->type !== 'all') {
            $query->ofType($request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->ofStatus($request->status);
        }

        if ($request->filled('charge_id')) {
            $query->forCharge($request->charge_id);
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json($logs);
    }
}
