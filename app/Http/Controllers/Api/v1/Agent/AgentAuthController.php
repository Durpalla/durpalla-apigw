<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Constants\AppConst;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentIncentive;
use App\Models\UserMeta;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AgentAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11',
            'password' => 'bail|required|min:8|max:20',
            'device_id' => 'bail|nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $agent = Agent::with(['meta', 'incentive'])
            ->where('mobile', $request->mobile)
            ->first();

        if (! $agent) {
            return response()->json([
                'success' => false,
                'message' => __('Account not found.'),
            ]);
        }

        if ((int) $agent->status !== AppConst::USER_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => __('Your agent application is pending approval.'),
            ]);
        }

        if (! Hash::check($request->password, $agent->password)) {
            return response()->json([
                'success' => false,
                'message' => __('Your password does not match.'),
            ]);
        }

        if ($request->filled('device_id')) {
            $agent->device_id = (string) $request->device_id;
            $agent->save();
        }

        Auth::guard('agent')->setUser($agent);
        $token = $agent->createToken(config('app.name'))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('Login success'),
            'token' => $token,
            'user' => AgentApiPresenter::user($agent),
        ]);
    }

    public function onboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string|min:3|max:191',
            'email' => 'bail|required|email|max:191|unique:agents,email',
            'mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11|unique:agents,mobile',
            'city' => 'bail|required|string|max:191',
            'address' => 'bail|required|string|max:500',
            'password' => 'bail|required|min:8|max:20',
            'confirm_password' => 'bail|required|same:password',
            'platform' => 'bail|nullable|string|max:32',
            'device_id' => 'bail|nullable|string|max:191',
            'selfie' => 'bail|required|image|mimes:jpg,jpeg,png|max:5120',
            'nid_front' => 'bail|required|image|mimes:jpg,jpeg,png|max:5120',
            'nid_back' => 'bail|required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $disk = config('filesystems.profile_disk', 'public');

        try {
            DB::transaction(function () use ($request, $disk) {
                $selfiePath = $this->storeUpload($request->file('selfie'), 'agents/selfie', $disk);
                $nidFrontPath = $this->storeUpload($request->file('nid_front'), 'agents/nid', $disk);
                $nidBackPath = $this->storeUpload($request->file('nid_back'), 'agents/nid', $disk);

                $agent = Agent::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'password' => Hash::make($request->password),
                    'status' => 0,
                    'email_verified_at' => now(),
                    'profile_pic' => $selfiePath,
                ]);

                try {
                    UserMeta::create([
                        'user_id' => $agent->id,
                        'created_by' => 'self',
                        'platform' => $request->input('platform', 'android'),
                        'address' => $request->address,
                        'city' => $request->city,
                        'nid_no' => null,
                        'nid_photo' => $nidFrontPath,
                        'nid_back_side' => $nidBackPath,
                        'trade_license' => null,
                        'trade_license_photo' => null,
                        'nid_verified' => 0,
                    ]);
                } catch (\Throwable $metaException) {
                    Log::warning('Agent onboard UserMeta write skipped', [
                        'agent_id' => $agent->id,
                        'message' => $metaException->getMessage(),
                    ]);
                }

                AgentIncentive::create([
                    'agent_id' => $agent->id,
                    'incentive' => 0,
                    'incentive_type' => 'percent',
                ]);

                if ($request->filled('device_id')) {
                    $agent->device_id = (string) $request->device_id;
                    $agent->saveQuietly();
                }
            }, 2);
        } catch (\Throwable $exception) {
            Log::error('Agent onboard failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $exception->getMessage()
                    : __('Application could not be submitted. Please try again.'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Application submitted! We will review and notify you.'),
        ]);
    }

    private function storeUpload($file, string $directory, string $disk): string
    {
        $filename = uniqid('', true) . '.' . $file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => 'public',
        ]);
    }
}
