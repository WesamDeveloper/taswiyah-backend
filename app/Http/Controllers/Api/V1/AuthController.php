<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;
use App\Models\ActivationCode;
use App\Models\OtpCode;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle multi-tenant user registration
     */
    public function register(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        // Create the company (Tenant)
        $tenant = Tenant::create([
            'name' => $request->company_name,
            'app_name' => $request->company_name,
        ]);

        // Create the main branch for this company
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'الفرع الرئيسي',
        ]);

        // Create the admin user
        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'is_activated' => false,
        ]);

        // Generate Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    /**
     * Handle user login and token generation
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد غير صحيحة.'], // "Credentials are incorrect"
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب موقوف أو غير مفعل.'], // "Account is suspended"
            ]);
        }

        // Generate Sanctum Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'branch_id' => $user->branch_id,
                'is_activated' => $user->is_activated,
                'whatsapp_number' => $user->whatsapp_number,
                'avatar_icon' => $user->avatar_icon,
            ]
        ]);
    }

    /**
     * Activate the user account using a valid code
     */
    public function activate(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if ($user->is_activated) {
            return response()->json([
                'status' => 'success',
                'message' => 'الحساب مفعل مسبقاً.'
            ]);
        }

        $activationCode = ActivationCode::where('code', $request->code)->first();

        if (!$activationCode) {
            throw ValidationException::withMessages([
                'code' => ['كود التفعيل غير صحيح.'],
            ]);
        }

        if ($activationCode->is_used) {
            throw ValidationException::withMessages([
                'code' => ['كود التفعيل هذا مستخدم مسبقاً.'],
            ]);
        }

        // Mark code as used
        $activationCode->update([
            'is_used' => true,
            'used_by' => $user->id,
            'used_at' => now(),
        ]);

        // Activate user
        $user->update(['is_activated' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تفعيل الحساب بنجاح.',
            'user' => $user
        ]);
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج بنجاح.' // "Logged out successfully"
        ]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'auto_remind_day' => 'nullable|integer|min:1|max:31'
        ]);

        $tenant = $request->user()->tenant;
        $tenant->update(['auto_remind_day' => $request->auto_remind_day]);

        return response()->json(['status' => 'success', 'message' => 'Settings updated', 'tenant' => $tenant]);
    }

    /**
     * Get current authenticated user profile
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()->load(['tenant', 'branch'])
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'whatsapp_number' => 'nullable|string|max:50',
            'avatar_icon' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6'
        ]);

        $user = $request->user();
        $data = $request->only(['name', 'whatsapp_number', 'avatar_icon']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث الملف الشخصي بنجاح.',
            'user' => $user
        ]);
    }

    public function forgotPassword(Request $request, WhatsAppService $whatsAppService)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages(['email' => ['هذا البريد الإلكتروني غير مسجل لدينا.']]);
        }

        if (!$user->whatsapp_number) {
            return response()->json([
                'status' => 'error',
                'needs_support' => true,
                'message' => 'لم تقم بربط رقم واتساب بحسابك مسبقاً. يرجى التواصل مع خدمة العملاء.'
            ], 400);
        }

        // Generate 6 digit OTP
        $code = rand(100000, 999999);

        OtpCode::create([
            'user_id' => $user->id,
            'code' => (string)$code,
            'expires_at' => now()->addMinutes(10),
        ]);

        $message = "🔐 *إعادة تعيين كلمة المرور*\n\nمرحباً {$user->name}،\nرمز التحقق الخاص بك هو: *{$code}*\n\nالرمز صالح لمدة 10 دقائق. لا تشاركه مع أحد.";
        
        $success = $whatsAppService->sendMessage((string)$user->tenant_id, $user->whatsapp_number, $message);

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'تم إرسال رمز التحقق إلى رقم الواتساب الخاص بك.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'حدث خطأ أثناء إرسال الرمز. قد يكون الواتساب غير متصل بالخادم.'
        ], 500);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            throw ValidationException::withMessages(['email' => ['بيانات غير صحيحة.']]);
        }

        $otp = OtpCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages(['code' => ['الرمز غير صحيح أو منتهي الصلاحية.']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم التحقق من الرمز بنجاح. يمكنك الآن تعيين كلمة مرور جديدة.',
            'reset_token' => $otp->id // Using the otp ID as a simple token for the next step
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_token' => 'required|integer',
            'password' => 'required|string|min:6'
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            throw ValidationException::withMessages(['email' => ['البيانات غير صحيحة.']]);
        }

        $otp = OtpCode::where('id', $request->reset_token)
            ->where('user_id', $user->id)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages(['reset_token' => ['الجلسة غير صالحة أو منتهية. يرجى طلب رمز جديد.']]);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        $otp->update(['is_used' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.'
        ]);
    }
}
