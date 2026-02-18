<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BlockExpiredSubAccounts extends Command
{
    protected $signature = 'reseller:block-expired';
    protected $description = 'Bloqueia sub-contas de revenda com validade expirada e cascata para sub-contas de revendedores bloqueados';

    public function handle(): int
    {
        // 1. Bloquear sub-contas com validade expirada
        $expiredCount = User::whereNotNull('reseller_id')
            ->where('status', 'active')
            ->whereNotNull('reseller_expires_at')
            ->where('reseller_expires_at', '<', now())
            ->update(['status' => 'inactive']);

        $this->info("Sub-contas expiradas bloqueadas: {$expiredCount}");

        // 2. Cascata: bloquear sub-contas cujo revendedor está inativo
        $inactiveResellerIds = User::where('status', 'inactive')
            ->whereHas('subAccounts', function ($q) {
                $q->where('status', 'active');
            })
            ->pluck('id');

        $cascadeCount = 0;
        if ($inactiveResellerIds->isNotEmpty()) {
            $cascadeCount = User::whereIn('reseller_id', $inactiveResellerIds)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);
        }

        $this->info("Sub-contas bloqueadas por cascata (revendedor inativo): {$cascadeCount}");

        return self::SUCCESS;
    }
}
