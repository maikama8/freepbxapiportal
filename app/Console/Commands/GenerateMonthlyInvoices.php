<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InvoiceService;
use Carbon\Carbon;

class GenerateMonthlyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:generate-monthly {--month=} {--year=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly invoices for all postpaid customers';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceService $invoiceService)
    {
        $month = $this->option('month') ?? now()->subMonth()->month;
        $year = $this->option('year') ?? now()->subMonth()->year;
        
        $targetMonth = Carbon::create($year, $month, 1);
        
        $this->info("Generating monthly invoices for {$targetMonth->format('F Y')}...");
        
        $invoices = $invoiceService->generateMonthlyInvoices($targetMonth);
        
        $this->info("Generated " . count($invoices) . " invoices:");
        
        foreach ($invoices as $invoice) {
            $this->line("- {$invoice->invoice_number} for {$invoice->user->name} ({$invoice->getFormattedTotal()})");
        }
        
        $this->info("Monthly invoice generation completed!");
        
        return Command::SUCCESS;
    }
}
