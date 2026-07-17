<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\ResellerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only wallet endpoints for the reseller API (balance + ledger). Top-ups
 * are handled by the durpalla-admin partner portal, not this booking gateway.
 */
class ResellerWalletController extends Controller
{
    public function __construct(private readonly ResellerWalletService $wallet)
    {
    }

    public function balance(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('api_partner');

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $this->wallet->getBalance((int) $partner->id),
                'currency' => optional($partner->wallet)->currency ?? 'BDT',
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('api_partner');

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        $transactions = WalletTransaction::where('party_id', $partner->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(['success' => true, 'data' => $transactions]);
    }
}
