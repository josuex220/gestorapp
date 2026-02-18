<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\MercadoPagoConfig;
use App\Models\MercadoPagoLog;
use App\Services\MailService;
use App\Services\MercadoPagoService;
use App\Services\ResellerChargeService;
use App\Services\SubscriptionChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private MercadoPagoService $mpService
    ) {}

    /**
     * POST /api/webhooks/mercadopago
     * Endpoint publico - sem autenticacao
     */
    public function handle(Request $request): JsonResponse
    {
        $topic = $request->input('topic') ?? $request->input('type');
        $resourceId = $request->input('data.id')
            ?? $request->input('resource');

        Log::info('Mercado Pago Webhook recebido', [
            'topic' => $topic,
            'resource_id' => $resourceId,
            'payload' => $request->all(),
        ]);

        // Processar apenas notificacoes de pagamento
        if (!in_array($topic, ['payment', 'payment.updated', 'payment.created'])) {
            return response()->json(['message' => 'Ignorado - tipo nao relevante']);
        }

        if (!$resourceId) {
            return response()->json(['message' => 'ID do recurso ausente'], 400);
        }

        // Extrair payment_id do resource URL se necessario
        $paymentId = $resourceId;
        if (str_contains((string) $resourceId, '/')) {
            $parts = explode('/', $resourceId);
            $paymentId = end($parts);
        }

        try {
            // Buscar todas as configs conectadas e tentar processar
            $configs = MercadoPagoConfig::where('is_connected', true)->get();

            foreach ($configs as $config) {
                try {
                    $paymentData = $this->mpService->getPayment(
                        $config->decrypted_access_token,
                        $paymentId
                    );

                    $externalReference = $paymentData['external_reference'] ?? null;

                    if (!$externalReference) {
                        continue;
                    }

                    $charge = Charge::where('id', $externalReference)
                        ->where('user_id', $config->user_id)
                        ->first();

                    if (!$charge) {
                        continue;
                    }

                    // Processar status do pagamento
                    $mpStatus = $paymentData['status'];
                    $logType = 'webhook_received';
                    $chargeStatus = null;

                    switch ($mpStatus) {
                        case 'approved':
                            $chargeStatus = 'paid';
                            $logType = 'payment_approved';
                            $charge->update([
                                'status' => 'paid',
                                'paid_at' => now(),
                                'mp_payment_id' => $paymentId,
                            ]);

                            // Processar serviços automáticos (revenda, assinatura, avulso)
                            ResellerChargeService::handleChargePaid($charge->id);
                            SubscriptionChargeService::handleSubscriptionChargePaid($charge->id);

                            // Enviar e-mail de confirmação
                            $client = DB::table('clients')->where('id', $charge->client_id)->first();
                            if ($client && $client->email) {
                                MailService::paymentConfirmed($client->email, [
                                    'name'           => $client->name,
                                    'amount'         => 'R$ ' . number_format((float) $charge->amount, 2, ',', '.'),
                                    'description'    => $charge->description ?? 'Cobrança',
                                    'paid_at'        => now()->format('d/m/Y H:i'),
                                    'payment_method' => $paymentData['payment_method_id'] ?? $charge->payment_method,
                                ]);
                            }
                            break;

                        case 'rejected':
                        case 'cancelled':
                            $logType = 'payment_rejected';
                            // Nao altera status da charge automaticamente
                            $charge->update([
                                'mp_payment_id' => $paymentId,
                            ]);
                            break;

                        case 'pending':
                        case 'in_process':
                            // Pagamento em processamento, manter pending
                            break;

                        case 'refunded':
                            $chargeStatus = 'cancelled';
                            $logType = 'payment_rejected';
                            $charge->update([
                                'status' => 'cancelled',
                                'cancelled_at' => now(),
                            ]);
                            break;
                    }

                    // Registrar log
                    MercadoPagoLog::record($config->user_id, $logType, 'success', [
                        'charge_id' => $charge->id,
                        'mp_payment_id' => $paymentId,
                        'request_payload' => $request->all(),
                        'response_payload' => [
                            'mp_status' => $mpStatus,
                            'charge_status' => $chargeStatus,
                            'amount' => $paymentData['transaction_amount'] ?? null,
                            'payment_method' => $paymentData['payment_method_id'] ?? null,
                            'payer_email' => $paymentData['payer']['email'] ?? null,
                        ],
                    ]);

                    return response()->json(['message' => 'Webhook processado com sucesso']);

                } catch (\Exception $e) {
                    // Este config nao conseguiu processar, tentar proximo
                    continue;
                }
            }

            // Nenhum config conseguiu processar
            Log::warning('Webhook MP: nenhum config processou o pagamento', [
                'payment_id' => $paymentId,
            ]);

            return response()->json(['message' => 'Processado']);

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook Mercado Pago', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Erro interno',
            ], 500);
        }
    }
}
