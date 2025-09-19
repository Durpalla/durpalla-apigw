<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\LoginVerifyRequest;
use Modules\Auth\Services\VendorAuthService;

class AuthController extends Controller
{
    private VendorAuthService $authService;

    public function __construct(VendorAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        return $this->authService->login($request);
    }

    public function verify(LoginVerifyRequest $request)
    {
        return $this->authService->verify($request);
    }

    public function resendOtp($reference)
    {
        return $this->authService->resendOtp($reference);
    }

    public function logout(Request $request)
    {
        return $this->authService->logout($request);
    }

    public function dashboard(Request $request)
    {
        return $this->authService->dashboard($request);
    }
}
