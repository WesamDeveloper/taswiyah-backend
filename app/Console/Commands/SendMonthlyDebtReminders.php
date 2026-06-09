<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Services\WhatsAppService;

class SendMonthlyDebtReminders extends Command
{
    protected $signature = 'debts:send-monthly-reminders';
    protected $description = 'Automatically send free WhatsApp reminders to customers with unpaid debts at the end of the month';

    public function handle(WhatsAppService $whatsAppService)
    {
        $this->info('Starting automated monthly debt reminders...');

        // Get customers who have unpaid debts, calculate their total debt
        $customers = Customer::with(['debts' => function($query) {
            $query->where('status', 'unpaid')->orWhere('status', 'partial');
        }])->get();

        $count = 0;

        foreach ($customers as $customer) {
            $totalDebt = $customer->debts->sum('amount') - $customer->debts->sum('paid');

            if ($totalDebt > 0) {
                // Formatting the automated professional WhatsApp message
                $message = "مرحباً عميلنا العزيز *{$customer->name}*، 👋\n\n";
                $message .= "نأمل أن تكونوا بخير. هذا إشعار دوري تلقائي من قسم الحسابات.\n";
                $message .= "نود تذكيركم بأن إجمالي الرصيد المتبقي (الديون غير المسددة) حتى نهاية هذا الشهر هو: *{$totalDebt} ر.ي*.\n\n";
                $message .= "يرجى التكرم بترتيب السداد في أقرب وقت لضمان استمرار تقديم خدماتنا بأفضل جودة.\n\n";
                $message .= "شكراً لتعاملكم المستمر معنا! 🌟\n";
                $message .= "_(رسالة آلية من نظام Taswiyah AI)_";

                // Send via Free Node.js Gateway
                $success = $whatsAppService->sendMessage($customer->primary_phone, $message);

                if ($success) {
                    $count++;
                    $this->info("Sent to {$customer->name} ({$customer->primary_phone})");
                }
            }
        }

        $this->info("Completed! Sent {$count} automated WhatsApp messages completely free.");
        return 0;
    }
}
