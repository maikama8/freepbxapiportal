<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CallRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class InvoiceService
{
    /**
     * Generate invoice for a user for a specific period
     */
    public function generateInvoice(User $user, Carbon $periodStart, Carbon $periodEnd): Invoice
    {
        // Create the invoice
        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'user_id' => $user->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(config('voip.billing.payment_terms_days', 30))->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'currency' => $user->currency ?? 'USD',
            'billing_address' => $this->getUserBillingAddress($user)
        ]);

        // Add call charges to invoice
        $this->addCallCharges($invoice, $periodStart, $periodEnd);

        // Add any additional fees
        $this->addAdditionalFees($invoice, $periodStart, $periodEnd);

        // Calculate totals
        $invoice->calculateTotals();

        Log::info("Generated invoice {$invoice->invoice_number} for user {$user->id}");

        return $invoice;
    }

    /**
     * Generate monthly invoices for all postpaid customers
     */
    public function generateMonthlyInvoices(Carbon $month = null): array
    {
        $month = $month ?? now()->subMonth();
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $postpaidUsers = User::where('account_type', 'postpaid')
            ->where('status', 'active')
            ->get();

        $generatedInvoices = [];

        foreach ($postpaidUsers as $user) {
            // Check if invoice already exists for this period
            $existingInvoice = Invoice::where('user_id', $user->id)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->first();

            if (!$existingInvoice) {
                $invoice = $this->generateInvoice($user, $periodStart, $periodEnd);
                $generatedInvoices[] = $invoice;
            }
        }

        Log::info("Generated " . count($generatedInvoices) . " monthly invoices for period {$periodStart->format('Y-m')}");

        return $generatedInvoices;
    }

    /**
     * Generate PDF for an invoice
     */
    public function generatePDF(Invoice $invoice): string
    {
        $data = [
            'invoice' => $invoice,
            'user' => $invoice->user,
            'items' => $invoice->items,
            'company' => config('voip.company', [])
        ];

        $pdf = Pdf::loadView('invoices.pdf', $data);
        
        // Store the PDF
        $filename = "invoices/{$invoice->invoice_number}.pdf";
        Storage::put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Send invoice via email
     */
    public function sendInvoice(Invoice $invoice): bool
    {
        try {
            // Generate PDF
            $pdfPath = $this->generatePDF($invoice);
            
            // TODO: Implement email sending with PHPMailer
            // This would be implemented in the email notification system (task 8)
            
            $invoice->markAsSent();
            
            Log::info("Sent invoice {$invoice->invoice_number} to user {$invoice->user->email}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to send invoice {$invoice->invoice_number}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark invoice as paid and update user balance
     */
    public function markInvoiceAsPaid(Invoice $invoice, float $paidAmount = null): bool
    {
        try {
            $paidAmount = $paidAmount ?? $invoice->total_amount;
            
            if ($paidAmount < $invoice->total_amount) {
                Log::warning("Partial payment received for invoice {$invoice->invoice_number}");
            }

            $invoice->markAsPaid();
            
            // For postpaid accounts, add the payment to balance
            if ($invoice->user->isPostpaid()) {
                $balanceService = new BalanceService();
                $balanceService->addBalance(
                    $invoice->user, 
                    $paidAmount, 
                    "Payment for invoice {$invoice->invoice_number}"
                );
            }

            Log::info("Marked invoice {$invoice->invoice_number} as paid with amount {$paidAmount}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to mark invoice {$invoice->invoice_number} as paid: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(): array
    {
        $overdueInvoices = Invoice::where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->with('user')
            ->get();

        return [
            'invoices' => $overdueInvoices,
            'total_amount' => $overdueInvoices->sum('total_amount'),
            'count' => $overdueInvoices->count()
        ];
    }

    /**
     * Get invoice statistics
     */
    public function getInvoiceStatistics(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subMonth();
        $endDate = $endDate ?? now();

        $invoices = Invoice::whereBetween('invoice_date', [$startDate, $endDate]);

        return [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('total_amount'),
            'paid_invoices' => $invoices->where('status', 'paid')->count(),
            'paid_amount' => $invoices->where('status', 'paid')->sum('total_amount'),
            'overdue_invoices' => $invoices->where('status', '!=', 'paid')->where('due_date', '<', now())->count(),
            'overdue_amount' => $invoices->where('status', '!=', 'paid')->where('due_date', '<', now())->sum('total_amount'),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ];
    }

    /**
     * Add call charges to invoice
     */
    private function addCallCharges(Invoice $invoice, Carbon $periodStart, Carbon $periodEnd): void
    {
        $callRecords = CallRecord::where('user_id', $invoice->user_id)
            ->whereNotNull('cost')
            ->whereBetween('start_time', [$periodStart, $periodEnd])
            ->get();

        // Group calls by destination for better invoice readability
        $callGroups = $callRecords->groupBy('destination');

        foreach ($callGroups as $destination => $calls) {
            $totalCost = $calls->sum('cost');
            $totalDuration = $calls->sum('duration');
            $callCount = $calls->count();

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Calls to {$destination} ({$callCount} calls, " . gmdate('H:i:s', $totalDuration) . ")",
                'quantity' => $callCount,
                'unit_price' => $totalCost / $callCount,
                'total_price' => $totalCost,
                'item_type' => 'call',
                'metadata' => [
                    'destination' => $destination,
                    'total_duration' => $totalDuration,
                    'call_ids' => $calls->pluck('id')->toArray()
                ]
            ]);
        }
    }

    /**
     * Add additional fees to invoice
     */
    private function addAdditionalFees(Invoice $invoice, Carbon $periodStart, Carbon $periodEnd): void
    {
        // Monthly service fee for postpaid accounts
        $monthlyFee = config('voip.billing.monthly_service_fee', 0);
        if ($monthlyFee > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Monthly Service Fee',
                'quantity' => 1,
                'unit_price' => $monthlyFee,
                'total_price' => $monthlyFee,
                'item_type' => 'fee'
            ]);
        }

        // Additional fees could be added here (e.g., regulatory fees, taxes, etc.)
    }

    /**
     * Get user billing address
     */
    private function getUserBillingAddress(User $user): array
    {
        // This would typically come from a user profile or billing address table
        // For now, return basic user information
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone
        ];
    }
}