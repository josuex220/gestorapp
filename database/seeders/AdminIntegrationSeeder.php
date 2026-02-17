<?php

namespace Database\Seeders;

use App\Models\AdminIntegration;
use Illuminate\Database\Seeder;

class AdminIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        AdminIntegration::updateOrCreate(['slug' => 'mercadopago'], [
            'name' => 'Mercado Pago',
            'description' => 'Gateway de pagamento principal',
            'fields' => [
                ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'value' => '', 'required' => true, 'placeholder' => 'APP_USR-xxxx'],
                ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'value' => '', 'required' => true, 'placeholder' => 'Cole o Client Secret'],
                ['key' => 'sandbox',       'label' => 'Modo Sandbox',  'type' => 'toggle',   'value' => 'true', 'required' => false],
            ],
        ]);

        AdminIntegration::updateOrCreate(['slug' => 'stripe'], [
            'name' => 'Stripe',
            'description' => 'Gateway de pagamento alternativo',
            'fields' => [
                ['key' => 'publishable_key', 'label' => 'Publishable Key', 'type' => 'text',     'value' => '', 'required' => true, 'placeholder' => 'pk_live_xxxx'],
                ['key' => 'secret_key',      'label' => 'Secret Key',      'type' => 'password', 'value' => '', 'required' => true, 'placeholder' => 'sk_live_xxxx'],
                ['key' => 'webhook_secret',  'label' => 'Webhook Secret',  'type' => 'password', 'value' => '', 'required' => false, 'placeholder' => 'whsec_xxxx'],
                ['key' => 'sandbox',         'label' => 'Modo Teste',      'type' => 'toggle',   'value' => 'true', 'required' => false],
            ],
        ]);

        AdminIntegration::updateOrCreate(['slug' => 'whatsapp'], [
            'name' => 'WhatsApp API',
            'description' => 'Notificações e cobranças via WhatsApp',
            'fields' => [
                ['key' => 'api_token',    'label' => 'API Token',    'type' => 'password', 'value' => '', 'required' => true],
                ['key' => 'phone_number', 'label' => 'Número',       'type' => 'text',     'value' => '', 'required' => true, 'placeholder' => '+5511999999999'],
                ['key' => 'provider',     'label' => 'Provedor',     'type' => 'select',   'value' => '', 'required' => true,
                    'options' => [
                        ['label' => 'Z-API',        'value' => 'zapi'],
                        ['label' => 'Evolution',    'value' => 'evolution'],
                        ['label' => 'Meta Official','value' => 'meta'],
                    ],
                ],
            ],
        ]);
    }
}
