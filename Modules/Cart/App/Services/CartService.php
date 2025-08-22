<?php

namespace Modules\Cart\App\Services;

use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use Modules\Cart\App\Models\Cart;
use Modules\Cart\App\Models\CartItem;
use Modules\Product\Entities\Product;

class CartService
{
    public function getCarts(Request $request)
    {
        try {
            return Cart::with('items')->filter($request)->first()?->format();
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'CUSTOMER_CART_LIST_EXCEPTION'
            ]);
            return null;
        }
    }

    public function create($request)
    {
        try {
            LogHelper::debug([
                'cookie' => $request->input('guest_unique_id'),
                'request' => $request->all()
            ]);
            $product = Product::find($request->product_id);
            $priceArr = [
                'price' => $product->price,
                'amount' => $request->input('qty', 1) * $product->price,
                'qty' => $request->input('qty', 1)
            ];

            $cart = Cart::updateOrCreate(
                [
                    'token' => decrypt($request->input('guest_unique_id'))
                ],
                [
                    'customer_id' => auth('api')->id()
                ]
            );
            if ($cart->items()->count()) {
                $existingItem = $cart->items()->where('product_id', $request->input('product_id'));

                if ($item = $existingItem->first()) {
                    $item->update($priceArr);
                } else {
                    $cart->items()->save(
                        new CartItem(
                            $request->validated() + $priceArr
                        )
                    );
                }
            } else {
                $cart->items()->save(
                    new CartItem(
                        $request->validated() + $priceArr
                    )
                );
            }
            return response()->success($cart->format());
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'ADD_TO_CART_EXCEPTION'
            ]);
            return response()->failed();
        }
    }

    public function destroy($request, $id)
    {
        try {
            $cart = $this->getCarts($request);
            if (!$cart) {
                throw new \Exception('Cart not found');
            }

            if ($id) {
                $item = CartItem::where('item_id', $id)->first();
                if ($item && $item->cart->token == $cart['token']) {
                    $item->delete();
                }
            }

            return response()->success(
                $this->getCarts($request)
            );
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'DELETE_TO_CART_EXCEPTION'
            ]);
            return response()->failed();
        }
    }

}
