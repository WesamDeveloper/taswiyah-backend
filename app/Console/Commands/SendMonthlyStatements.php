<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Services\WhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class SendMonthlyStatements extends Command
{
    protected $signature = 'taswiyah:send-statements';
    protected $description = 'Generate and send monthly PDF statements to customers via WhatsApp';

    public function handle(WhatsAppService $whatsapp)
    {
        $this->info('Starting monthly statement generation...');

        $customers = Customer::with(['debts' => function($q) {
            $q->orderBy('created_at', 'desc');
        }])->get();

        foreach ($customers as $customer) {
            $totalDebt = $customer->debts->sum('amount');
            $totalPaid = $customer->debts->sum('paid');
            $remaining = $totalDebt - $totalPaid;

            if ($remaining <= 0) {
                continue; // Skip customers with no outstanding balance
            }

            // Generate PDF
            $pdf = Pdf::loadView('pdf.monthly_statement', [
                'customer' => $customer,
                'totalDebt' => $totalDebt,
                'totalPaid' => $totalPaid,
                'remaining' => $remaining,
                'date' => Carbon::now()->format('Y-m-d')
            ]);

            // Save PDF
            $fileName = 'statements/statement_' . $customer->id . '_' . date('Y_m') . '.pdf';
            Storage::disk('public')->put($fileName, $pdf->output());

            // Build detailed WhatsApp Message
            $message = "📄 *كشف حساب شهري - تطبيق تسوية*\n\n";
            $message .= "مرحباً *{$customer->name}*،\n";
            $message .= "إليك ملخص حسابك حتى تاريخ " . Carbon::now()->format('Y-m-d') . ":\n\n";
            $message .= "🔹 إجمالي الديون: {$totalDebt} ر.ي\n";
            $message .= "🔹 إجمالي المسدد: {$totalPaid} ر.ي\n";
            $message .= "🔴 *المبلغ المتبقي الساري: {$remaining} ر.ي*\n\n";

            $message .= "يرجى مراجعة المتجر لتسوية المبالغ المتبقية. شكراً لتعاملكم!";

            $whatsapp->sendMessage((string)$customer->tenant_id, $customer->primary_phone, $message);
            $this->info("Sent statement to {$customer->name}");
        }

        $this->info('All statements sent successfully!');
    }
}
