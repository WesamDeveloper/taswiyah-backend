<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Payment;

class SyncController extends Controller
{
    public function initial(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $customers = Customer::where('tenant_id', $tenantId)
            ->withSum('debts as total_debt', 'amount')
            ->withSum('debts as total_paid', 'paid')
            ->get()
            ->map(function ($customer) {
                $customerArray = $customer->toArray();
                $customerArray['remaining_balance'] = ($customer->total_debt ?? 0) - ($customer->total_paid ?? 0);
                return $customerArray;
            });
        
        $debts = Debt::where('tenant_id', $tenantId)
            ->with(['customer' => function($q) {
                $q->select('id', 'name');
            }])
            ->get()
            ->map(function ($debt) {
                $debtArray = $debt->toArray();
                $debtArray['customer_name'] = $debt->customer?->name ?? 'غير محدد';
                unset($debtArray['customer']);
                return $debtArray;
            });
            
        $payments = Payment::where('tenant_id', $tenantId)
            ->with(['debt.customer' => function($q) {
                $q->select('id', 'name');
            }])
            ->get()
            ->map(function ($payment) {
                $paymentArray = $payment->toArray();
                $paymentArray['customer_id'] = $payment->debt?->customer?->id ?? null;
                $paymentArray['customer_name'] = $payment->debt?->customer?->name ?? 'غير محدد';
                unset($paymentArray['debt']);
                return $paymentArray;
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'customers' => $customers->values(),
                'debts' => $debts->values(),
                'payments' => $payments->values()
            ]
        ]);
    }
}
