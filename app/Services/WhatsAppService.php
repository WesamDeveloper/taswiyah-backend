<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $gatewayUrl;

    public function __construct()
    {
        // Points to our free Node.js Baileys Gateway
        $this->gatewayUrl = env('WHATSAPP_GATEWAY_URL', 'http://127.0.0.1:3000');
    }

    /**
     * Initialize session and request QR code from Gateway
     */
    public function initSession(string $tenantId)
    {
        try {
            $response = Http::timeout(10)->post("{$this->gatewayUrl}/api/whatsapp/init", [
                'tenant_id' => $tenantId
            ]);
            return $response->json();
        } catch (\Exception $e) {
            return ['error' => 'Gateway Unreachable'];
        }
    }

    /**
     * Check if a specific tenant's WhatsApp is connected
     */
    public function getStatus(string $tenantId)
    {
        try {
            $response = Http::timeout(5)->get("{$this->gatewayUrl}/api/whatsapp/status/{$tenantId}");
            return $response->json();
        } catch (\Exception $e) {
            return ['error' => 'Gateway Unreachable'];
        }
    }

    /**
     * Reset the tenant's session
     */
    public function resetSession(string $tenantId)
    {
        try {
            $response = Http::timeout(10)->post("{$this->gatewayUrl}/api/whatsapp/reset", [
                'tenant_id' => $tenantId
            ]);
            return $response->json();
        } catch (\Exception $e) {
            return ['error' => 'Gateway Unreachable'];
        }
    }

    /**
     * Send WhatsApp message using the specific tenant's session
     */
    public function sendMessage(string $tenantId, string $phone, string $message): array
    {
        try {
            $response = Http::post("{$this->gatewayUrl}/api/send-message", [
                'tenant_id' => $tenantId,
                'phone' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                return ['success' => true, 'error' => null];
            }

            $errorData = $response->json();
            $errorMsg = $errorData['error'] ?? $response->body();
            if (isset($errorData['details'])) {
                $errorMsg .= " (" . $errorData['details'] . ")";
            }
            Log::error('WhatsApp Gateway Error: ' . $errorMsg);
            return ['success' => false, 'error' => "Gateway Error: " . $errorMsg];
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => "Service Exception: " . $e->getMessage()];
        }
    }
}
