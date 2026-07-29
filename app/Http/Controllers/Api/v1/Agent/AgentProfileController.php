<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\UserMeta;
use App\Services\AgentPushNotificationService;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AgentProfileController extends Controller
{
    public function __construct(private readonly AgentPushNotificationService $push)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '',
            'data' => AgentApiPresenter::user(auth()->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $agent = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|string|min:3|max:191',
            'email' => 'bail|required|email|max:191|unique:agents,email,'.$agent->id,
            'mobile' => 'bail|required|regex:/^(01)[3456789]\d{8}$/|unique:agents,mobile,'.$agent->id,
            'city' => 'bail|nullable|string|max:191',
            'address' => 'bail|nullable|string|max:500',
            'profile_pic' => 'bail|nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $agent->name = $request->name;
        $agent->email = $request->email;
        $agent->mobile = $request->mobile;

        if ($request->hasFile('profile_pic')) {
            $disk = config('filesystems.profile_disk', 'public');
            $path = $this->storeUpload($request->file('profile_pic'), 'agents/selfie', $disk);
            $agent->profile_pic = $path;
        }

        $agent->save();

        $meta = UserMeta::query()->firstOrNew(['user_id' => $agent->id]);
        $meta->city = $request->input('city');
        $meta->address = $request->input('address');
        if (! $meta->exists) {
            $meta->created_by = 'self';
            $meta->platform = 'android';
        }
        $meta->save();

        return response()->json([
            'success' => true,
            'message' => __('Profile updated successfully'),
            'data' => AgentApiPresenter::user($agent->fresh('meta')),
        ]);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! $agent instanceof Agent) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated'),
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'bail|required|string|min:20|max:512',
            'platform' => 'bail|nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $platform = $request->input('platform', 'agent_android');
        if (! str_starts_with((string) $platform, 'agent')) {
            $platform = 'agent_'.ltrim((string) $platform, '_');
        }

        $this->push->registerToken($agent, (string) $request->input('fcm_token'), (string) $platform);

        return response()->json([
            'success' => true,
            'message' => __('FCM token updated'),
            'data' => null,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'bail|required|string',
            'password' => 'bail|required|min:8|max:20',
            'confirm_password' => 'bail|required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $agent = auth()->user();
        if (! Hash::check($request->current_password, $agent->password)) {
            return response()->json([
                'success' => false,
                'message' => __('Current password is incorrect'),
            ], 422);
        }

        $agent->password = Hash::make($request->password);
        $agent->save();

        return response()->json([
            'success' => true,
            'message' => __('Password changed successfully'),
            'data' => null,
        ]);
    }

    private function storeUpload($file, string $folder, string $disk): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $name = Str::uuid()->toString().'.'.$ext;

        return Storage::disk($disk)->putFileAs($folder, $file, $name);
    }
}
