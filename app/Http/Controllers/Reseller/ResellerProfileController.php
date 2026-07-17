<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Services\ResellerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerProfileController extends Controller
{
    public function __construct(private readonly ResellerWalletService $wallet)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('api_partner');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'slug' => $partner->slug,
                'email' => $partner->email,
                'mobile' => $partner->mobile,
                'commission_share_percent' => $partner->commissionSharePercent(),
                'wallet' => [
                    'balance' => $this->wallet->getBalance((int) $partner->id),
                    'currency' => optional($partner->wallet)->currency ?? 'BDT',
                ],
            ],
        ]);
    }
}
