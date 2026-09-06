<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\MerchantForgotPasswordRequest;
use App\Http\Requests\Merchant\MerchantResetPasswordRequest;
use App\Http\Requests\Merchant\MerchantVerifyResetOtpRequest;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Services\Merchant\MerchantPasswordResetService;
use App\Support\PasswordVerifier;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use App\Traits\Auth2FaTrait;
use App\Services\TwoFactorService;

class MerchantAuthController extends Controller
{
    use Auth2FaTrait;

    public function __construct(
        private readonly MerchantPasswordResetService $passwordResetService,
    ) {
    }

    /**
     * Merchant Desk Pro login step-1: validate credentials and start 2FA (default email OTP).
     *
     * If 2FA is required, returns:
     * { "success": false, "two_factor_required": true, "method": "email|authenticator", "token": "<encrypted actor ref>", "message": "..." }
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'bail|required|string|max:191',
            'password' => 'bail|nullable|string|min:3|max:191',
            'pin' => 'bail|nullable|string|min:3|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $login = trim((string) $request->input('login'));
        $secret = $request->filled('pin') ? (string) $request->input('pin') : (string) $request->input('password');
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Password or PIN is required.',
            ], 422);
        }

        try {
            $user = $this->findMerchantActor($login);

            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
            }
            if ((int) $user->status !== 1) {
                return response()->json(['success' => false, 'message' => 'Account is inactive.'], 403);
            }
            if (($user->merchant_id ?? null) && (int) optional($user->merchant)->status !== 1) {
                return response()->json(['success' => false, 'message' => 'This account is inactive. Please contact support.'], 403);
            }

            $passwordHash = (string) ($user->getAttributes()['password'] ?? '');
            if (! PasswordVerifier::check($secret, $passwordHash)) {
                return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
            }

            // Honor per-account 2FA from profile (Email OTP / authenticator).
            $method = $this->resolveMerchant2FaMethod($user);
            if ($method) {
                $actorToken = $this->generateToken($this->encodeActorRef($user));

                if ($method === 'email') {
                    $email = $this->actorEmail($user);
                    if (! $email) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This account has no email configured for OTP.',
                        ], 422);
                    }

                    if (! \Illuminate\Support\Facades\Schema::hasTable('otps')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'OTP service is temporarily unavailable.',
                        ], 503);
                    }

                    $this->createLoginOtp($email);

                    return response()->json([
                        'success' => false,
                        'two_factor_required' => true,
                        'method' => 'email',
                        'token' => $actorToken,
                        'message' => App::environment('local', 'development')
                            ? 'OTP sent to email (dev default may be 111111).'
                            : 'OTP sent to your email.',
                    ], 200);
                }

                if ($method === 'authenticator') {
                    return response()->json([
                        'success' => false,
                        'two_factor_required' => true,
                        'method' => 'authenticator',
                        'token' => $actorToken,
                        'message' => 'Enter the 6-digit code from your authenticator app.',
                    ], 200);
                }
            }

            return $this->issueTokenResponse($user);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('merchant_login_failed', [
                'login' => $login,
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to sign in right now. Please try again.',
            ], 500);
        }
    }

    /**
     * Merchant Desk Pro login step-2: verify 2FA and return token + user.
     *
     * POST /api/v1/auth/merchant/verify-2fa
     * Body: { "token": "<encrypted merchant:id|staff:id>", "code": "123456" }
     */
    public function verify2fa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'bail|required|string',
            'code' => 'bail|required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $ref = (string) $this->decryptToken((string) $request->input('token'));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Invalid token.'], 422);
        }

        $user = $this->resolveActorFromRef($ref);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }
        if ((int) $user->status !== 1) {
            return response()->json(['success' => false, 'message' => 'Account is inactive.'], 403);
        }
        if (($user->merchant_id ?? null) && (int) optional($user->merchant)->status !== 1) {
            return response()->json(['success' => false, 'message' => 'This account is inactive. Please contact support.'], 403);
        }

        $method = $this->resolveMerchant2FaMethod($user);
        if (!$method) {
            return response()->json(['success' => false, 'message' => 'Two-factor authentication is not enabled for this account.'], 422);
        }

        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        if (strlen($code) !== 6) {
            return response()->json(['success' => false, 'message' => 'Invalid code.'], 422);
        }

        if ($method === 'authenticator') {
            $twoFactorService = app(TwoFactorService::class);
            try {
                $secret = decrypt($user->two_factor_secret);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Authenticator is not configured properly.'], 422);
            }
            if (!$twoFactorService->verify($secret, $code)) {
                return response()->json(['success' => false, 'message' => 'Invalid code.'], 422);
            }
            return $this->issueTokenResponse($user);
        }

        $email = $this->actorEmail($user);
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'This account has no email configured for OTP.'], 422);
        }

        if (!$this->verifyLoginOtp($email, $code)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired code.'], 422);
        }

        $this->revokeLoginOtps($email);

        return $this->issueTokenResponse($user);
    }

    public function forgotPassword(MerchantForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->requestOtp((string) $request->input('email'));

        return response()->json(
            array_diff_key($result, ['status' => true]),
            (int) ($result['status'] ?? 200),
        );
    }

    public function verifyResetOtp(MerchantVerifyResetOtpRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->verifyOtp(
            (string) $request->input('token'),
            (string) $request->input('code'),
        );

        return response()->json(
            array_diff_key($result, ['status' => true]),
            (int) ($result['status'] ?? 200),
        );
    }

    public function resetPassword(MerchantResetPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->resetPassword(
            (string) $request->input('token'),
            (string) $request->input('password'),
        );

        return response()->json(
            array_diff_key($result, ['status' => true]),
            (int) ($result['status'] ?? 200),
        );
    }

    /**
     * @return null|'email'|'authenticator'
     */
    private function resolveMerchant2FaMethod(Authenticatable $user): ?string
    {
        if (empty($user->two_factor_type) || empty($user->two_factor_confirmed_at)) {
            return null;
        }

        $method = (string) $user->two_factor_type;

        if ($method === 'authenticator' && empty($user->two_factor_secret)) {
            return 'email';
        }

        if ($method === 'email' || $method === 'authenticator') {
            return $method;
        }

        return 'email';
    }

    private function issueTokenResponse(Authenticatable $user): JsonResponse
    {
        try {
            $token = $user->createToken(config('app.name'))->plainTextToken;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('merchant_create_token_failed', [
                'user_type' => $user::class,
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create session. Please contact support.',
            ], 500);
        }

        $appRole = $this->mapToDeskRole($user);
        $merchantOwnerId = $user instanceof Merchant
            ? (string) $user->id
            : (string) ($user->merchant_id ?? '');

        $name = $user instanceof Merchant
            ? (string) $user->merchant_name
            : (string) ($user->name ?? '');

        $email = $this->actorEmail($user) ?? '';
        $phone = $user instanceof Merchant
            ? ($user->merchant_mobile ? (string) $user->merchant_mobile : null)
            : ($user->mobile ? (string) $user->mobile : null);

        $merchant = $user instanceof Merchant
            ? $user
            : ($user->merchant ?? $this->merchantQuery()->find($merchantOwnerId));

        $permissions = $user instanceof Merchant
            ? ['*']
            : ($user instanceof MerchantStaff ? $user->permissionNames() : []);

        $avatarUrl = rescue(static fn () => $user->profile_pic_url ?? null, null, false);
        $logoUrl = rescue(static fn () => $merchant?->logo_url, null, false);

        return response()->json([
            'success' => true,
            'message' => 'Login success',
            'token' => $token,
            'user' => [
                'id' => (string) $user->id,
                'name' => $name,
                'email' => (string) $email,
                'phone' => $phone,
                'role' => $appRole,
                'type' => $user instanceof Merchant ? 'merchant' : (string) ($user->type ?? $appRole),
                'permissions' => $permissions,
                'merchant_id' => $merchantOwnerId,
                'avatar_url' => $avatarUrl,
                'merchant_logo_url' => $logoUrl,
                'merchant_name' => $merchant
                    ? (string) ($merchant->merchant_name ?? $merchant->name ?? '')
                    : null,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function findMerchantActor(string $login): Merchant|MerchantStaff|null
    {
        try {
            if (str_contains($login, '@')) {
                $merchant = $this->merchantQuery()->where('merchant_email', $login)->first();
                if ($merchant) {
                    return $merchant;
                }

                return $this->merchantStaffQuery()->where('email', $login)->first();
            }

            $candidates = $this->normalizeMobileCandidates($login);

            $merchant = $this->merchantQuery()->whereIn('merchant_mobile', $candidates)->first();
            if ($merchant) {
                return $merchant;
            }

            return $this->merchantStaffQuery()->whereIn('mobile', $candidates)->first();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('merchant_actor_lookup_failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Merchant>
     */
    private function merchantQuery()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('merchants')) {
            throw new \RuntimeException('merchants table is missing');
        }

        $q = Merchant::query();
        if (! \Illuminate\Support\Facades\Schema::hasColumn('merchants', 'deleted_at')) {
            $q->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
        }

        return $q;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\MerchantStaff>
     */
    private function merchantStaffQuery()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('merchant_staff')) {
            // Staff table optional — treat as no staff matches.
            return MerchantStaff::query()->whereRaw('0 = 1');
        }

        $q = MerchantStaff::query();
        if (! \Illuminate\Support\Facades\Schema::hasColumn('merchant_staff', 'deleted_at')) {
            $q->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
        }

        return $q;
    }

    /**
     * @return list<string>
     */
    private function normalizeMobileCandidates(string $login): array
    {
        $digits = preg_replace('/\D/', '', $login) ?? '';
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '88') {
            $digits = substr($digits, 2);
        }

        return array_values(array_unique(array_filter([
            $login,
            $digits,
            strlen($digits) === 10 ? '0' . $digits : null,
            strlen($digits) === 11 && ($digits[0] ?? '') === '0' ? substr($digits, 1) : null,
        ])));
    }

    private function encodeActorRef(Authenticatable $user): string
    {
        if ($user instanceof Merchant) {
            return 'merchant:' . $user->id;
        }

        return 'staff:' . $user->id;
    }

    private function resolveActorFromRef(string $ref): Merchant|MerchantStaff|null
    {
        // Prefer "merchant:5" / "staff:3"; also accept legacy "merchant|5" / "staff|5"
        if (preg_match('/^(merchant|staff)[:|](\d+)$/', trim($ref), $m)) {
            return $m[1] === 'merchant'
                ? $this->merchantQuery()->find((int) $m[2])
                : $this->merchantStaffQuery()->find((int) $m[2]);
        }

        // Legacy: plain numeric id treated as merchant
        if (ctype_digit(trim($ref))) {
            return $this->merchantQuery()->find((int) $ref);
        }

        return null;
    }

    private function actorEmail(Authenticatable $user): ?string
    {
        if ($user instanceof Merchant) {
            return $user->merchant_email ?: null;
        }

        return $user->email ?: null;
    }

    /**
     * Map actor into Merchant Desk Pro roles (merchant|manager|accountant|viewer|...).
     */
    private function mapToDeskRole(Authenticatable $user): string
    {
        if ($user instanceof Merchant) {
            return 'merchant';
        }

        $type = (string) ($user->type ?? '');

        return match ($type) {
            'merchant' => 'merchant',
            'manager' => 'manager',
            'accountant' => 'accountant',
            'executive' => 'executive',
            'counter', 'counter-officer', 'ticket_master' => 'counter',
            'supervisor' => 'supervisor',
            default => 'viewer',
        };
    }
}
