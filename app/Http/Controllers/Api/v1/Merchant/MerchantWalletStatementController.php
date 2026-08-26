<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\AccountStatement;
use App\Services\AccountStatementService;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantWalletStatementController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(private readonly AccountStatementService $statements)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $rows = $this->statements->history(
            AccountStatement::ACCOUNT_MERCHANT,
            $ownerId,
            (int) $request->query('per_page', 20)
        );

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $rows,
        ]);
    }
}
