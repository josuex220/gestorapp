<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminIntegration;
use App\Models\PlatformPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create default super admin
        Admin::firstOrCreate(
            ['email' => 'admin@cobgest.com'],
            [
                'id' => Str::uuid(),
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
            ]
        );

        // Seed default platform plans
        $plans = [
            [
                'name' => 'Starter',
                'price' => 29.90,
                'interval' => 'mensal',
                'features' => ['Até 50 clientes', '100 cobranças/mês', 'E-mail'],
                'privileges' => [
                    'max_clients' => 50,
                    'max_charges_per_month' => 100,
                    'notification_channels' => ['email' => true, 'whatsapp' => false, 'telegram' => false],
                    'reports_access' => 'basic',
                    'api_access' => false,
                    'dedicated_support' => false,
                    'custom_branding' => false,
                    'has_trial' => true,
                    'trial_days' => 7,
                ],
            ],
            [
                'name' => 'Pro',
                'price' => 99.90,
                'interval' => 'mensal',
                'features' => ['Até 200 clientes', '500 cobranças/mês', 'E-mail e WhatsApp', 'Relatórios avançados'],
                'privileges' => [
                    'max_clients' => 200,
                    'max_charges_per_month' => 500,
                    'notification_channels' => ['email' => true, 'whatsapp' => true, 'telegram' => false],
                    'reports_access' => 'advanced',
                    'api_access' => true,
                    'dedicated_support' => false,
                    'custom_branding' => false,
                    'has_trial' => true,
                    'trial_days' => 14,
                ],
            ],
            [
                'name' => 'Enterprise',
                'price' => 249.90,
                'interval' => 'mensal',
                'features' => ['Clientes ilimitados', 'Cobranças ilimitadas', 'Todos os canais', 'Suporte dedicado', 'Marca personalizada'],
                'privileges' => [
                    'max_clients' => null,
                    'max_charges_per_month' => null,
                    'notification_channels' => ['email' => true, 'whatsapp' => true, 'telegram' => true],
                    'reports_access' => 'advanced',
                    'api_access' => true,
                    'dedicated_support' => true,
                    'custom_branding' => true,
                    'has_trial' => true,
                    'trial_days' => 14,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            PlatformPlan::firstOrCreate(
                ['name' => $plan['name']],
                array_merge($plan, ['id' => Str::uuid()])
            );
        }

        // Seed default integrations
        $integrations = [
            ['name' => 'mercadopago', 'description' => 'Gateway de pagamento principal', 'key_label' => 'Access Token'],
            ['name' => 'stripe', 'description' => 'Gateway de pagamento internacional', 'key_label' => 'Secret Key'],
            ['name' => 'whatsapp', 'description' => 'Notificações via WhatsApp', 'key_label' => 'API Token'],
        ];

        foreach ($integrations as $integration) {
            AdminIntegration::firstOrCreate(
                ['name' => $integration['name']],
                array_merge($integration, ['id' => Str::uuid()])
            );
        }
    }
}
