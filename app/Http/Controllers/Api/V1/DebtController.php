<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Debt;
use App\Models\Customer;
use App\Services\WhatsAppService;
use Carbon\Carbon;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $debts = Debt::where('tenant_id', $tenantId)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $debts]);
    }

    public function store(Request $request, WhatsAppService $whatsapp)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        $tenantId = $request->user()->tenant_id;

        $debt = Debt::create([
            'tenant_id' => $tenantId,
            'branch_id' => $request->user()->branch_id,
            'customer_id' => $request->customer_id,
            'amount' => $request->amount,
            'paid' => 0,
            'status' => 'unpaid',
            'due_date' => $request->due_date ? Carbon::parse($request->due_date) : null,
            'notes' => $request->notes
        ]);

        // AUTOMATED WHATSAPP MESSAGE
        $customer = Customer::find($request->customer_id);
        if ($customer && $customer->primary_phone) {
            $message = "مرحباً *{$customer->name}*،\n\n";
            $message .= "تم تسجيل فاتورة دين جديدة على حسابكم.\n";
            $message .= "المبلغ: *{$debt->amount} ر.ي*\n";
            if ($debt->due_date) {
                $message .= "تاريخ الاستحقاق: {$debt->due_date->format('Y-m-d')}\n";
            }
            $message .= "\nشكراً لتعاملكم معنا! (تطبيق تسوية)";

            // Fire and forget via service
            $whatsapp->sendMessage((string)$tenantId, $customer->primary_phone, $message);
        }

        return response()->json(['status' => 'success', 'data' => $debt], 201);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $debt = Debt::where('tenant_id', $tenantId)->with('customer')->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $debt]);
    }

    // Process a payment (partial or full)
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount_paid' => 'required|numeric|min:1'
        ]);

        $tenantId = $request->user()->tenant_id;
        $debt = Debt::where('tenant_id', $tenantId)->findOrFail($id);

        $newPaid = $debt->paid + $request->amount_paid;
        $status = ($newPaid >= $debt->amount) ? 'paid' : 'partial';

        $debt->update([
            'paid' => $newPaid,
            'status' => $status
        ]);

        return response()->json(['status' => 'success', 'data' => $debt]);
    }
}
