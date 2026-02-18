<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubscriptionChargeService;
use Illuminate\Support\Facades\Log;

class GenerateSubscriptionCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:generate-charges 
                            {--dry-run : Simula sem criar cobranças}
                            {--user= : Processa apenas um usuário específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera cobranças automáticas para assinaturas no aniversário do vencimento';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionChargeService $service): int
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user');

        $this->info('=== Geração de Cobranças de Assinaturas ===');
        $this->info('Data: ' . now()->format('d/m/Y H:i:s'));

        if ($isDryRun) {
            $this->warn('⚠️  Modo simulação (dry-run) — nenhuma cobrança será criada.');
        }

        try {
            $result = $service->generateCharges(
                dryRun: $isDryRun,
                userId: $userId ? (int) $userId : null
            );

            $this->info("✅ Assinaturas processadas: {$result['processed']}");
            $this->info("✅ Cobranças geradas: {$result['charges_created']}");
            $this->info("⏭️  Ignoradas (não vencidas): {$result['skipped']}");

            if (!empty($result['errors'])) {
                $this->error("❌ Erros: " . count($result['errors']));
                foreach ($result['errors'] as $error) {
                    $this->error("  - Assinatura {$error['subscription_id']}: {$error['message']}");
                    Log::error('Erro ao gerar cobrança de assinatura', $error);
                }
            }

            if ($result['charges_created'] > 0 && !$isDryRun) {
                Log::info('Cobranças de assinatura geradas automaticamente', [
                    'charges_created' => $result['charges_created'],
                    'processed' => $result['processed'],
                ]);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro fatal: {$e->getMessage()}");
            Log::error('Erro fatal ao gerar cobranças de assinatura', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
