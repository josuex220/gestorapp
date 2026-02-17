<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\MonthlyInvoiceGenerated;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate-monthly';
    protected $description = 'Gera faturas mensais para usuários com plano ativo';

    public function handle(): void
    {
        $users = User::whereNotNull('platform_plan_id')
            ->where('status', 'active')
            ->with('platformPlan')
            ->get();

        $period = now()->format('M/Y');
        $count = 0;

        foreach ($users as $user) {
            $exists = Invoice::where('user_id', $user->id)
                ->where('period', $period)
                ->exists();

            if ($exists) continue;

            $invoice = Invoice::create([
                'user_id'          => $user->id,
                'platform_plan_id' => $user->platform_plan_id,
                'amount'           => $user->platformPlan->price,
                'status'           => 'pending',
                'due_date'         => now()->addDays(5),
                'period'           => $period,
            ]);

            $user->notify(new MonthlyInvoiceGenerated($invoice));

            $count++;
        }

        $this->info("Geradas {$count} faturas para {$period}.");
    }
}
