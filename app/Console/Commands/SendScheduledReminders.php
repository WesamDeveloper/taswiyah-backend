<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\Customer;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendScheduledReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send scheduled WhatsApp reminders based on global or specific customer settings';

    public function handle(WhatsAppService $whatsapp)
    {
        $today = Carbon::now();
        $currentDayOfMonth = $today->day;
        
        // 1. GLOBAL REMINDERS (Tenants who set auto_remind_day == today)
        $tenants = Tenant::where('auto_remind_day', $currentDayOfMonth)->get();
        foreach ($tenants as $tenant) {
            $customers = Customer::where('tenant_id', $tenant->id)
                ->whereHas('debts', function($q) {
                    $q->where('status', '!=', 'paid');
                })
                ->with('debts')
                ->get();
                
            foreach ($customers as $customer) {
                // Skip if this customer has a specific schedule (handled below)
                if ($customer->reminder_frequency_days && $customer->next_reminder_date) {
                    continue;
                }
                
                $this->sendReminderToCustomer($customer, $tenant->id, $whatsapp);
            }
        }

        // 2. SPECIFIC REMINDERS (Customers whose next_reminder_date is today or earlier)
        $specificCustomers = Customer::whereNotNull('reminder_frequency_days')
            ->whereDate('next_reminder_date', '<=', $today->format('Y-m-d'))
            ->whereHas('debts', function($q) {
                $q->where('status', '!=', 'paid');
            })
            ->with('debts')
            ->get();
            
        foreach ($specificCustomers as $customer) {
            $this->sendReminderToCustomer($customer, $customer->tenant_id, $whatsapp);
            
            // Calculate next reminder date
            $customer->update([
                'next_reminder_date' => Carbon::now()->addDays($customer->reminder_frequency_days)
            ]);
        }
        
        $this->info('Scheduled reminders sent successfully.');
    }

    private function sendReminderToCustomer($customer, $tenantId, $whatsapp)
    {
        $totalDebt = $customer->debts->sum('amount');
        $totalPaid = $customer->debts->sum('paid');
        $remaining = $totalDebt - $totalPaid;
        
        if ($remaining > 0 && $customer->primary_phone) {
            $message = "📄 *تذكير دوري برصيد مستحق*\n\nمرحباً {$customer->name}،\nنود تذكيركم بأن الرصيد المتبقي المستحق عليكم هو: *{$remaining} ر.ي*.\nيرجى السداد في أقرب وقت. شكراً لتعاملكم معنا!";
            $whatsapp->sendMessage((string)$tenantId, $customer->primary_phone, $message);
        }
    }
}
