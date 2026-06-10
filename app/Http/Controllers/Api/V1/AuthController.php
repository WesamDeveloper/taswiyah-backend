<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Branch;
use App\Models\ActivationCode;
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
}
