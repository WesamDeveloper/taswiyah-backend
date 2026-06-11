<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\Api\V1\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    
    // Public Auth Routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    
    // Protected Routes (Require Authentication)
    Route::middleware('auth:sanctum')->group(function () {
        
        // User Profile & Logout
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/settings', [AuthController::class, 'updateSettings']);
        Route::post('/auth/activate', [AuthController::class, 'activate']);
        
        // Dashboard Stats
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        
        // Multi-Tenant / Branch Management
        Route::apiResource('branches', BranchController::class);
        
        // Customers & Debts
        Route::post('customers/remind-all', [CustomerController::class, 'remindAll']);
        Route::post('customers/remind-group', [CustomerController::class, 'remindGroup']);
        Route::post('customers/schedule-group', [CustomerController::class, 'scheduleGroup']);
        Route::apiResource('customers', CustomerController::class);
        Route::post('customers/{id}/pay', [CustomerController::class, 'payBalance']);
        Route::post('customers/{id}/remind', [CustomerController::class, 'remind']);
        Route::apiResource('debts', DebtController::class);
        Route::post('debts/{id}/pay', [DebtController::class, 'pay']);
        
        // Invoices & Financials
        Route::apiResource('invoices', InvoiceController::class);
        
        // WhatsApp Integration (Multi-Tenant QR and Messaging)
        Route::get('/whatsapp/qr', [WhatsAppController::class, 'getQrCode']);
        Route::get('/whatsapp/status', [WhatsAppController::class, 'getStatus']);
        Route::post('/whatsapp/send', [WhatsAppController::class, 'sendManualMessage']);
        
    });

});
