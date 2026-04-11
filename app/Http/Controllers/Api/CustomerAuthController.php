<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\CustomerRegisterRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Customer API auth – uses Customer model and guard 'customer' (Sanctum).
 * Separate from User (staff/merchant); customers table only.
 */
class CustomerAuthController extends Controller
{
    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => $request->password, // hashed by Customer model cast
        ]);

        $token = $customer->createToken('customer-api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('Registration successful'),
            'data' => [
                'customer' => $customer->only('id', 'name', 'email', 'mobile'),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Customer login: mobile number + password or pin.
     * Mobile lookup tries exact value and normalized variants (with/without leading 0).
     */
    public function login(CustomerLoginRequest $request): JsonResponse
    {
        $mobileDigits = preg_replace('/\D/', '', $request->mobile);
        // BD: strip leading 88 (country code) so +8801712345678 -> 01712345678
        if (strlen($mobileDigits) === 13 && substr($mobileDigits, 0, 2) === '88') {
            $mobileDigits = substr($mobileDigits, 2);
        }
        $candidates = array_unique(array_filter([
            $request->mobile,
            $mobileDigits,
            strlen($mobileDigits) === 10 ? '0' . $mobileDigits : null,
            strlen($mobileDigits) === 11 && $mobileDigits[0] === '0' ? substr($mobileDigits, 1) : null,
        ]));
        $customer = Customer::whereIn('mobile', $candidates)->first();

        $secret = $request->filled('pin') ? $request->pin : $request->password;

        if (!$customer || !Hash::check($secret, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid credentials'),
            ], 401);
        }

        $customer->tokens()->where('name', 'customer-api')->delete();
        $token = $customer->createToken('customer-api')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('Login successful'),
            'data' => [
                'customer' => $customer->only('id', 'name', 'email', 'mobile'),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('customer')->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => __('Logged out'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $request->user('customer');

        return response()->json([
            'success' => true,
            'data' => $customer->only('id', 'name', 'email', 'mobile', 'created_at'),
        ]);
    }
}
