<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Debt;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $totalDebts = Debt::where('tenant_id', $tenantId)->sum('amount');
        $totalCollected = \App\Models\Payment::where('tenant_id', $tenantId)->sum('amount');
        $remainingBalance = $totalDebts - $totalCollected;

        $overdueCount = Debt::where('tenant_id', $tenantId)
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::now())
            ->count();

        $activeCustomers = Customer::where('tenant_id', $tenantId)->count();

        // Chart Data (Last 7 Days)
        $chartCollections = [];
        $chartNewDebts = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $col = \App\Models\Payment::where('tenant_id', $tenantId)
                ->whereDate('created_at', $date)
                ->sum('amount');
            $deb = Debt::where('tenant_id', $tenantId)
                ->whereDate('created_at', $date)
                ->sum('amount');
            
            $chartCollections[] = $col;
            $chartNewDebts[] = $deb;
        }

        $chartData = [
            'collections' => $chartCollections,
            'new_debts' => $chartNewDebts,
        ];

        // Recent Activity
        $recentDebts = Debt::where('tenant_id', $tenantId)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($d) {
                return [
                    'id' => $d->id,
                    'type' => 'debt',
                    'title' => 'سلفة لـ ' . ($d->customer->name ?? 'عميل'),
                    'amount' => $d->amount,
                    'created_at' => $d->created_at->toIso8601String()
                ];
            });

        $recentPayments = \App\Models\Payment::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($p) {
                $debt = Debt::with('customer')->find($p->debt_id);
                return [
                    'id' => $p->id,
                    'type' => 'payment',
                    'title' => 'تحصيل من ' . ($debt->customer->name ?? 'عميل'),
                    'amount' => $p->amount,
                    'created_at' => $p->created_at->toIso8601String()
                ];
            });

        $recentActivity = collect($recentDebts)->merge($recentPayments)->sortByDesc('created_at')->take(5)->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_name' => $request->user()->name,
                'company_name' => $request->user()->tenant->name,
                'avatar_icon' => $request->user()->avatar_icon,
                'total_debts' => $totalDebts,
                'total_collected' => $totalCollected,
                'remaining_balance' => $remainingBalance,
                'overdue_count' => $overdueCount,
                'active_customers' => $activeCustomers,
                'chart_data' => $chartData,
                'recent_activity' => $recentActivity
            ]
        ]);
    }
}
