<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\MercadoPagoConfig;
use App\Models\MercadoPagoLog;
use Illuminate\Support\Facades\Http;

class MercadoPagoService
{
    private string $apiUrl = 'https://api.mercadopago.com';

    public function testConnection(MercadoPagoConfig $config): array
    {
        $token = $config->decrypted_access_token;

        $response = Http::withToken($token)
            ->get("{$this->apiUrl}/v1/payment_methods");

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Conexao estabelecida com sucesso',
                'payment_methods_count' => count($response->json()),
            ];
        }

        return [
            'success' => false,
            'message' => 'Falha na conexao: ' . $response->body(),
        ];
    }

    public function createPreference(
        MercadoPagoConfig $config,
        Charge $charge
    ): array {
        $token = $config->decrypted_access_token;

        // Montar metodos de pagamento excluidos
        $excludedMethods = [];
        $methods = $config->accepted_payment_methods;

        $methodMap = [
            'credit_card' => 'credit_card',
            'debit_card' => 'debit_card',
            'pix' => 'bank_transfer',
            'boleto' => 'ticket',
        ];

        foreach ($methodMap as $key => $mpType) {
            if (!($methods[$key] ?? true)) {
                $excludedMethods[] = ['id' => $mpType];
            }
        }

        // Bandeiras excluidas
        $allBrands = ['visa', 'mastercard', 'elo', 'amex', 'hipercard'];
        $acceptedBrands = $config->accepted_brands ?? $allBrands;
        $excludedBrands = array_diff($allBrands, $acceptedBrands);
        $excludedTypes = array_map(fn($b) => ['id' => $b], $excludedBrands);

        $preferenceData = [
            'items' => [
                [
                    'id' => $charge->id,
                    'title' => $charge->description ?: "Cobranca #{$charge->id}",
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => (float) $charge->amount,
                ],
            ],
            'payer' => [
                'name' => $charge->client->name ?? '',
                'email' => $charge->client->email ?? '',
            ],
            'payment_methods' => [
                'excluded_payment_methods' => $excludedTypes,
                'excluded_payment_types' => $excludedMethods,
                'installments' => $config->max_installments,
            ],
            'back_urls' => [
                'success' => config('app.frontend_url') . '/charges?mp_status=approved',
                'failure' => config('app.frontend_url') . '/charges?mp_status=rejected',
                'pending' => config('app.frontend_url') . '/charges?mp_status=pending',
            ],
            'auto_return' => 'approved',
            'external_reference' => $charge->id,
            'statement_descriptor' => $config->statement_descriptor,
            'notification_url' => config('app.url') . '/api/webhooks/mercadopago',
        ];

        $response = Http::withToken($token)
            ->post("{$this->apiUrl}/checkout/preferences", $preferenceData);

        if (!$response->successful()) {
            throw new \Exception(
                'Erro ao criar preferencia: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function getPayment(string $token, string $paymentId): array
    {
        $response = Http::withToken($token)
            ->get("{$this->apiUrl}/v1/payments/{$paymentId}");

        if (!$response->successful()) {
            throw new \Exception(
                'Erro ao buscar pagamento: ' . $response->body()
            );
        }

        return $response->json();
    }
}
