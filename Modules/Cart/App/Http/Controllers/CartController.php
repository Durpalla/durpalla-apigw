<?php

namespace Modules\Cart\App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\Cart\App\Http\Requests\CartValidateRequest;
use Modules\Cart\App\Http\Requests\StoreCart;
use Modules\Cart\App\Http\Requests\UpdateCart;
use Modules\Cart\App\Services\CartService;

class CartController extends Controller
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index(Request $request)
    {
        return response()->success(
            $this->cartService->getCarts($request)
        );
    }

    public function store(StoreCart $request)
    {
        return $this->cartService->create($request);
    }

    public function update(UpdateCart $request, $id)
    {
        //
    }

    public function destroy(Request $request, $id)
    {
        return $this->cartService->destroy($request, $id);
    }

    public function validate(CartValidateRequest $request): array
    {
        return $this->cartService->validate($request);
    }
}
