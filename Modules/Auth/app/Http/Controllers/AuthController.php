<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // 1. تسجيل الدخول
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string'
        ]);

        $credentials = $request->only('phone', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'رقم الهاتف أو كلمة المرور غير صحيحة'], 401);
        }

        return $this->respondWithToken($token);
    }

    // 2. جلب بيانات المستخدم الحالي
    public function me()
    {
        $user = auth('api')->user();

        return response()->json([
            'data' => $user->load(['role.permissions', 'shift']) // بنجيب اليوزر مع الرول بتاعته وصلاحياته
        ]);
    }

    // 3. تسجيل الخروج
    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    // دالة مساعدة لتنسيق التوكن
    protected function respondWithToken($token)
    {
        $ttl = auth('api')->factory()->getTTL();
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl ? $ttl * 60 : null,
            'user' => auth('api')->user()->load(['role.permissions', 'shift'])
        ]);
    }
}
