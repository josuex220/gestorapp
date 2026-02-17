<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Console\Command;

class SuspendOverdueUsers extends Command
{
    protected $signature = 'users:suspend-overdue {--days=15 : Dias de atraso para suspender}';
    protected $description = 'Suspende usuários com faturas vencidas há X dias';

    public function handle(): void
    {
        $days = 5;
        $cutoff = now()->subDays($days);

        $userIds = Invoice::where('status', 'overdue')
            ->where('due_date', '<=', $cutoff)
            ->pluck('user_id')
            ->unique();

        $count = User::whereIn('id', $userIds)
            ->where('status', 'active')
            ->update(['status' => 'suspended']);

        $this->info("Suspensos {$count} usuários com faturas vencidas há mais de {$days} dias.");
    }
}
