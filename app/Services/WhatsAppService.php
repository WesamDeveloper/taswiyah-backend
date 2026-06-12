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
            $response = Http::timeout(10)->post("{$this->gatewayUrl}/wa/api/whatsapp/init", [
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
            $response = Http::timeout(5)->get("{$this->gatewayUrl}/wa/api/whatsapp/status/{$tenantId}");
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
            $response = Http::timeout(10)->post("{$this->gatewayUrl}/wa/api/whatsapp/reset", [
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
    public function sendMessage(string $tenantId, string $phone, string $message): bool
    {
        try {
            $response = Http::post("{$this->gatewayUrl}/wa/api/send-message", [
                'tenant_id' => $tenantId,
                'phone' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WhatsApp Gateway Error: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Exception: ' . $e->getMessage());
            return false;
        }
    }
}
