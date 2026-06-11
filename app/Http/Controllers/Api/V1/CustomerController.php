<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        
        $customers = Customer::where('tenant_id', $tenantId)
            ->withSum('debts as total_debt', 'amount')
            ->withSum('debts as total_paid', 'paid')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Append calculated balance to avoid doing it on mobile
        $customers->each(function($customer) {
            $customer->remaining_balance = ($customer->total_debt ?? 0) - ($customer->total_paid ?? 0);
        });

        return response()->json(['status' => 'success', 'data' => $customers]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'primary_phone' => 'required|string|max:20',
        ]);

        $tenantId = $request->user()->tenant_id;

        // Check for duplicates
        $exists = Customer::where('tenant_id', $tenantId)
            ->where(function ($query) use ($request) {
                $query->where('name', $request->name)
                      ->orWhere('primary_phone', $request->primary_phone);
            })->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'يوجد عميل مسجل مسبقاً بنفس الاسم أو رقم الهاتف.'
            ], 422);
        }

        $customer = Customer::create([
            'tenant_id' => $request->user()->tenant_id,
            'branch_id' => $request->user()->branch_id,
            'name' => $request->name,
            'primary_phone' => $request->primary_phone,
            'clv' => 0,
            'risk_score' => 'low',
            'churn_probability' => 0,
        ]);

        return response()->json(['status' => 'success', 'data' => $customer], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)
            ->with([
                'debts' => function($q) { $q->orderBy('created_at', 'desc'); },
                'payments' => function($q) { $q->orderBy('created_at', 'desc'); }
            ])
            ->findOrFail($id);
            
        return response()->json(['status' => 'success', 'data' => $customer]);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($id);
        
        $data = $request->only(['name', 'primary_phone', 'address', 'email', 'reminder_frequency_days', 'notify_on_debt']);
        if ($request->has('reminder_frequency_days')) {
            $data['next_reminder_date'] = $request->reminder_frequency_days ? \Carbon\Carbon::now()->addDays($request->reminder_frequency_days) : null;
        }

        $customer->update($data);
        
        return response()->json(['status' => 'success', 'data' => $customer]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($id);
        
        // Delete all debts and payments associated with this customer
        // Assuming models are Debt and Payment, though cascade usually handles it.
        $customer->debts()->delete();
        
        $customer->delete();
        
        return response()->json(['status' => 'success', 'message' => 'تم حذف العميل بنجاح']);
    }

    public function payBalance(Request $request, $id)
    {
        $request->validate(['amount' => 'required|numeric|min:1']);
        $tenantId = $request->user()->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($id);
        
        $amountToPay = $request->amount;
        $debts = $customer->debts()->where('status', '!=', 'paid')->orderBy('created_at', 'asc')->get();
        
        foreach ($debts as $debt) {
            if ($amountToPay <= 0) break;
            $remainingOnDebt = $debt->amount - $debt->paid;
            
            if ($amountToPay >= $remainingOnDebt) {
                \App\Models\Payment::create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $request->user()->branch_id,
                    'debt_id' => $debt->id,
                    'user_id' => $request->user()->id,
                    'amount' => $remainingOnDebt,
                    'method' => 'cash'
                ]);

                $debt->update(['paid' => $debt->amount, 'status' => 'paid']);
                $amountToPay -= $remainingOnDebt;
            } else {
                \App\Models\Payment::create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $request->user()->branch_id,
                    'debt_id' => $debt->id,
                    'user_id' => $request->user()->id,
                    'amount' => $amountToPay,
                    'method' => 'cash'
                ]);

                $debt->update(['paid' => $debt->paid + $amountToPay, 'status' => 'partial']);
                $amountToPay = 0;
            }
        }
        
        return response()->json(['status' => 'success', 'message' => 'Payment applied']);
    }

    public function remind(Request $request, $id, WhatsAppService $whatsapp)
    {
        $tenantId = $request->user()->tenant_id;
        
        // Allow frontend to pass the latest calculated values directly
        $remaining = $request->input('remaining');
        $phone = $request->input('phone');
        $name = $request->input('name');
        
        if ($remaining && $phone && $name) {
            if ($remaining > 0) {
                $message = "📄 *تذكير رصيد مستحق*\n\nمرحباً {$name}،\nنود تذكيركم بأن الرصيد المتبقي المستحق عليكم هو: *{$remaining} ر.ي*.\nيرجى السداد في أقرب وقت. شكراً لتعاملكم معنا!";
                $success = $whatsapp->sendMessage((string)$tenantId, $phone, $message);
                
                if ($success) {
                    return response()->json(['status' => 'success', 'message' => 'تم إرسال التذكير بنجاح']);
                } else {
                    return response()->json(['status' => 'error', 'message' => 'فشل الإرسال! تأكد من: 1. ربط الواتساب من قسم (المزيد) 2. أن رقم العميل يبدأ بالرمز الدولي (مثل 966 أو 967)'], 400);
                }
            }
            return response()->json(['status' => 'error', 'message' => 'ليس عليه ديون متبقية'], 400);
        }

        // Fallback to database if frontend didn't pass data (e.g. old clients)
        $customer = Customer::where('tenant_id', $tenantId)->with('debts')->findOrFail($id);
        
        $totalDebt = $customer->debts->sum('amount');
        $totalPaid = $customer->debts->sum('paid');
        $remaining = $totalDebt - $totalPaid;
        
        if ($remaining > 0) {
            $message = "📄 *تذكير رصيد مستحق*\n\nمرحباً {$customer->name}،\nنود تذكيركم بأن الرصيد المتبقي المستحق عليكم هو: *{$remaining} ر.ي*.\nيرجى السداد في أقرب وقت. شكراً لتعاملكم معنا!";
            $success = $whatsapp->sendMessage((string)$tenantId, $customer->primary_phone, $message);
            
            if ($success) {
                return response()->json(['status' => 'success', 'message' => 'تم إرسال التذكير بنجاح']);
            } else {
                return response()->json(['status' => 'error', 'message' => 'فشل الإرسال! تأكد من: 1. ربط الواتساب من قسم (المزيد) 2. أن رقم العميل يبدأ بالرمز الدولي (مثل 966 أو 967)'], 400);
            }
        }
        return response()->json(['status' => 'error', 'message' => 'ليس عليه ديون متبقية'], 400);
    }

    public function remindAll(Request $request, WhatsAppService $whatsapp)
    {
        $tenantId = $request->user()->tenant_id;
        $customers = Customer::where('tenant_id', $tenantId)
            ->whereHas('debts', function($q) {
                $q->where('status', '!=', 'paid');
            })
            ->with('debts')
            ->get();
            
        $sentCount = 0;
        foreach ($customers as $customer) {
            $totalDebt = $customer->debts->sum('amount');
            $totalPaid = $customer->debts->sum('paid');
            $remaining = $totalDebt - $totalPaid;
            
            if ($remaining > 0 && $customer->primary_phone) {
                $message = "📄 *تذكير رصيد مستحق*\n\nمرحباً {$customer->name}،\nنود تذكيركم بأن الرصيد المتبقي المستحق عليكم هو: *{$remaining} ر.ي*.\nيرجى السداد في أقرب وقت. شكراً لتعاملكم معنا!";
                if ($whatsapp->sendMessage((string)$tenantId, $customer->primary_phone, $message)) {
                    $sentCount++;
                }
            }
        }
        
        return response()->json(['status' => 'success', 'message' => "تم إرسال التذكير بنجاح لـ $sentCount عميل/عملاء."]);
    }

    public function remindGroup(Request $request, WhatsAppService $whatsapp)
    {
        $request->validate(['customer_ids' => 'required|array']);
        $tenantId = $request->user()->tenant_id;
        $customers = Customer::where('tenant_id', $tenantId)
            ->whereIn('id', $request->customer_ids)
            ->whereHas('debts', function($q) {
                $q->where('status', '!=', 'paid');
            })
            ->with('debts')
            ->get();
            
        $sentCount = 0;
        foreach ($customers as $customer) {
            $totalDebt = $customer->debts->sum('amount');
            $totalPaid = $customer->debts->sum('paid');
            $remaining = $totalDebt - $totalPaid;
            
            if ($remaining > 0 && $customer->primary_phone) {
                $message = "📄 *تذكير رصيد مستحق*\n\nمرحباً {$customer->name}،\nنود تذكيركم بأن الرصيد المتبقي المستحق عليكم هو: *{$remaining} ر.ي*.\nيرجى السداد في أقرب وقت. شكراً لتعاملكم معنا!";
                if ($whatsapp->sendMessage((string)$tenantId, $customer->primary_phone, $message)) {
                    $sentCount++;
                }
            }
        }
        
        return response()->json(['status' => 'success', 'message' => "تم إرسال التذكير بنجاح لـ $sentCount عميل/عملاء."]);
    }

    public function scheduleGroup(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array',
            'next_reminder_date' => 'nullable|date',
            'reminder_frequency_days' => 'nullable|integer'
        ]);
        
        $tenantId = $request->user()->tenant_id;
        
        Customer::where('tenant_id', $tenantId)
            ->whereIn('id', $request->customer_ids)
            ->update([
                'next_reminder_date' => $request->next_reminder_date,
                'reminder_frequency_days' => $request->reminder_frequency_days
            ]);
            
        return response()->json(['status' => 'success', 'message' => "تم جدولة التذكير بنجاح."]);
    }
}
