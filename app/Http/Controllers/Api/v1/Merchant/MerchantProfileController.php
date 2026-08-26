<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Services\MerchantCancellationPolicyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Services\MerchantTwoFactorService;

class MerchantProfileController extends Controller
{
    private function authActor(Request $request): Merchant|MerchantStaff
    {
        $user = $request->user();
        if ($user instanceof Merchant || $user instanceof MerchantStaff) {
            return $user;
        }

        abort(401);
    }

    public function show(Request $request): JsonResponse
    {
        $u = $this->authActor($request);

        return response()->json([
            'success' => true,
            'data' => $this->profilePayload($u),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        if ($u instanceof Merchant) {
            if (array_key_exists('name', $validated)) {
                $u->merchant_name = $validated['name'];
            }
            if (array_key_exists('email', $validated)) {
                $u->merchant_email = $validated['email'];
            }
            if (array_key_exists('mobile', $validated)) {
                $u->merchant_mobile = $validated['mobile'];
            }
            if (array_key_exists('address', $validated)) {
                $u->merchant_address = $validated['address'];
            }
            if (array_key_exists('phone', $validated)) {
                $u->merchant_phone = $validated['phone'];
            }
        } else {
            $u->fill(collect($validated)->only(['name', 'email', 'mobile'])->all());
        }
        $u->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data' => $this->profilePayload($u->fresh()),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        if (! $u instanceof Merchant) {
            return response()->json([
                'success' => false,
                'message' => 'Only the merchant owner can update the business logo.',
            ], 403);
        }

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['logo'];
        $directory = public_path('logos/merchants');
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = $u->id.'-'.time().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);
        $storedPath = 'logos/merchants/'.$filename;

        $previous = (string) ($u->logo ?? '');
        if ($previous !== '' && str_starts_with($previous, 'logos/merchants/')) {
            $previousPath = public_path($previous);
            if (File::isFile($previousPath)) {
                File::delete($previousPath);
            }
        }

        $u->logo = $storedPath;
        $u->save();

        return response()->json([
            'success' => true,
            'message' => 'Business logo updated.',
            'data' => $this->profilePayload($u->fresh()),
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['avatar'];
        $directory = public_path('avatars/merchants');
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $prefix = $u instanceof Merchant ? 'm' : 's';
        $filename = $prefix.'-'.$u->id.'-'.time().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);
        $storedPath = 'avatars/merchants/'.$filename;

        if ($u instanceof Merchant) {
            $previous = (string) ($u->profile_pic ?? '');
            if ($previous !== '' && str_starts_with($previous, 'avatars/merchants/')) {
                $previousPath = public_path($previous);
                if (File::isFile($previousPath)) {
                    File::delete($previousPath);
                }
            }
            $u->profile_pic = $storedPath;
        } else {
            $previous = (string) ($u->profile_pic ?? '');
            if ($previous !== '' && (str_starts_with($previous, 'avatars/') || str_starts_with($previous, 'uploads/'))) {
                $previousPath = public_path($previous);
                if (File::isFile($previousPath)) {
                    File::delete($previousPath);
                }
            }
            $u->profile_pic = $storedPath;
        }
        $u->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated.',
            'data' => $this->profilePayload($u->fresh()),
        ]);
    }

    /**
     * Enable Email OTP (default 2FA). Requires email.
     */
    public function enableEmail2fa(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $email = $u instanceof Merchant ? $u->merchant_email : $u->email;
        if (! $email) {
            return response()->json(['success' => false, 'message' => 'No email configured for OTP.'], 422);
        }
        $u->update([
            'two_factor_type' => 'email',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor method set to Email OTP.',
            'data' => $this->profilePayload($u->fresh()),
        ]);
    }

    /**
     * Disable 2FA (reverts to no 2FA; login will not require OTP).
     */
    public function disable2fa(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:191'],
        ]);
        if (! Hash::check($validated['current_password'], $u->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $u->update([
            'two_factor_type' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication disabled.',
            'data' => $this->profilePayload($u->fresh()),
        ]);
    }

    /**
     * Start authenticator setup: returns secret + otpauth URL to add in app.
     */
    public function authenticatorSetup(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        if (! config('auth.2fa_enabled', true)) {
            return response()->json(['success' => false, 'message' => '2FA is disabled for this application.'], 422);
        }
        $email = $u instanceof Merchant ? $u->merchant_email : $u->email;
        if (! $email) {
            return response()->json(['success' => false, 'message' => 'Email is required to set up authenticator.'], 422);
        }

        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        $otpauth = $service->getQRCodeUrl($u, $secret);

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'otpauth_url' => $otpauth,
            ],
        ]);
    }

    /**
     * Confirm authenticator code and persist secret.
     */
    public function authenticatorConfirm(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        if (! config('auth.2fa_enabled', true)) {
            return response()->json(['success' => false, 'message' => '2FA is disabled for this application.'], 422);
        }

        $validated = $request->validate([
            'secret' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $service = app(TwoFactorService::class);
        if (! $service->verify($validated['secret'], $validated['code'])) {
            return response()->json(['success' => false, 'message' => 'Invalid code.'], 422);
        }

        $recovery = $service->generateRecoveryCodes();
        $u->update([
            'two_factor_type' => 'authenticator',
            'two_factor_secret' => encrypt($validated['secret']),
            'two_factor_recovery_codes' => $recovery,
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Authenticator 2FA enabled.',
            'data' => array_merge($this->profilePayload($u->fresh()), [
                'recovery_codes' => $recovery,
            ]),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $u = $this->authActor($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:6', 'max:191', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $u->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $u->password = $validated['password'];
        $u->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Merchant|MerchantStaff $u): array
    {
        $merchant = $u instanceof Merchant ? $u : Merchant::query()->find($u->merchant_id);

        if ($u instanceof Merchant) {
            return array_merge([
                'id' => (string) $u->id,
                'name' => (string) $u->merchant_name,
                'email' => (string) ($u->merchant_email ?? ''),
                'mobile' => (string) ($u->merchant_mobile ?? ''),
                'merchant_id' => (string) $u->id,
                'role' => 'merchant',
                'avatar_url' => $u->profile_pic_url,
                'two_factor_type' => $u->two_factor_type ?: null,
                'two_factor_enabled' => ! empty($u->two_factor_type) && ! empty($u->two_factor_confirmed_at),
                'two_factor_confirmed_at' => $u->two_factor_confirmed_at?->format('c'),
            ], $this->merchantBusinessPayload($merchant));
        }

        return array_merge([
            'id' => (string) $u->id,
            'name' => (string) $u->name,
            'email' => (string) ($u->email ?? ''),
            'mobile' => (string) ($u->mobile ?? ''),
            'merchant_id' => (string) ($u->merchant_id ?? ''),
            'type' => (string) ($u->type ?? ''),
            'role' => (string) ($u->type ?? ''),
            'permissions' => $u->permissionNames(),
            'avatar_url' => $u->profile_pic_url,
            'two_factor_type' => $u->two_factor_type ?: null,
            'two_factor_enabled' => ! empty($u->two_factor_type) && ! empty($u->two_factor_confirmed_at),
            'two_factor_confirmed_at' => $u->two_factor_confirmed_at?->format('c'),
        ], $this->merchantBusinessPayload($merchant));
    }

    /**
     * @return array<string, mixed>
     */
    private function merchantBusinessPayload(?Merchant $merchant): array
    {
        if ($merchant === null) {
            return ['merchant' => null];
        }

        $cancellationPolicyLines = app(MerchantCancellationPolicyResolver::class)
            ->invoicePolicyLines((int) $merchant->id, 'transport');

        return [
            'merchant' => [
                'name' => (string) $merchant->merchant_name,
                'address' => (string) ($merchant->merchant_address ?? ''),
                'email' => (string) ($merchant->merchant_email ?? ''),
                'mobile' => (string) ($merchant->merchant_mobile ?? ''),
                'phone' => (string) ($merchant->merchant_phone ?? ''),
                'registration_no' => (string) ($merchant->merchant_reg_no ?? ''),
                'logo_url' => $merchant->logo_url,
                'cancellation_policy_lines' => $cancellationPolicyLines,
            ],
        ];
    }
}
