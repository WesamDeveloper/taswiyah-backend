<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use App\Models\Customer;

class WhatsAppController extends Controller
{
    /**
     * Get QR code for the authenticated user's store
     */
    public function getQrCode(Request $request, WhatsAppService $whatsAppService)
    {
        $tenantId = $request->user()->tenant_id;
        $response = $whatsAppService->initSession((string)$tenantId);
        
        return response()->json($response);
    }

    /**
     * Check connection status
     */
    public function getStatus(Request $request, WhatsAppService $whatsAppService)
    {
        $tenantId = $request->user()->tenant_id;
        $response = $whatsAppService->getStatus((string)$tenantId);
        
        return response()->json($response);
    }

    /**
     * Reset WhatsApp session
     */
    public function resetSession(Request $request, WhatsAppService $whatsAppService)
    {
        $tenantId = $request->user()->tenant_id;
        $response = $whatsAppService->resetSession((string)$tenantId);
        
        return response()->json($response);
    }

    /**
     * Manual WhatsApp messaging endpoint
     */
    public function sendManualMessage(Request $request, WhatsAppService $whatsAppService)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'message' => 'required|string',
        ]);

        $customer = Customer::findOrFail($request->customer_id);
        $tenantId = $request->user()->tenant_id;

        $success = $whatsAppService->sendMessage((string)$tenantId, $customer->primary_phone, $request->message);

        if ($success) {
            return response()->json(['status' => 'success', 'message' => 'تم إرسال رسالة الواتساب بنجاح.']);
        }

        return response()->json(['status' => 'error', 'message' => 'تعذر إرسال الرسالة، تأكد من ربط الواتساب في الإعدادات.'], 500);
    }
}
