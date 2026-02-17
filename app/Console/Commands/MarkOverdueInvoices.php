<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';
    protected $description = 'Marca faturas pendentes como vencidas após a data de vencimento';

    public function handle(): void
    {
        $invoices = Invoice::where('status', 'pending')
            ->where('due_date', '<', now())
            ->with('user')
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'overdue']);
            $invoice->user->notify(new InvoiceOverdue($invoice));
        }

        $this->info("Marcadas {$invoices->count()} faturas como vencidas.");
    }
}
