<?php

namespace App\Repository\Services\User\Cart;

use App\Constants\CouponTypeConstant;
use App\Constants\ResponseConstants;
use App\Repository\Services\Common\CommonService;
use DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CommonLogger as Log;

class CartService
{
    private $commonService;

    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function addToCart($data)
    {
        try {
            DB::beginTransaction();

            $cartAlreadyExist = DB::table('cart')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($cartAlreadyExist) {
                $cartId = $cartAlreadyExist->id;
                $cartItems = [];
                $hasUpdatedExistingItem = false;

                foreach ($data as $item) {
                    $product = DB::table('cart_items')
                        ->where('cart_id', $cartId)
                        ->where('product_id', $item['product_id'])
                        ->first();

                    if ($product) {
                        DB::table('cart_items')
                            ->where('cart_id', $cartId)
                            ->where('product_id', $item['product_id'])
                            ->update(['quantity' => $product->quantity + $item['quantity']]);
                        $hasUpdatedExistingItem = true;
                        continue;
                    }

                    $cartItems[] = [
                        'cart_id' => $cartId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ];
                }

                $hasInsertedNewItems = !empty($cartItems)
                    ? DB::table('cart_items')->insert($cartItems)
                    : false;

                if ($hasUpdatedExistingItem || $hasInsertedNewItems) {
                    DB::commit();
                    return [
                        'status' => ResponseConstants::SUCCESS,
                        'message' => 'Product added to cart successfully',
                        'data' => [],
                    ];
                }

                DB::rollBack();
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Product not added to cart',
                    'data' => [],
                ];
            }

            $cartId = DB::table('cart')->insertGetId([
                'user_id' => Auth::user()->id,
            ]);

            $cartItems = [];
            foreach ($data as $item) {
                $cartItems[] = [
                    'cart_id' => $cartId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }

            $cartProducts = DB::table('cart_items')->insert($cartItems);

            if ($cartProducts) {
                DB::commit();
                return [
                    'status' => ResponseConstants::SUCCESS,
                    'message' => 'Product added to cart successfully',
                    'data' => [],
                ];
            }

            DB::rollBack();
            return [
                'status' => ResponseConstants::FAILED,
                'message' => 'Product not added to cart',
                'data' => [],
            ];
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('CartService : addToCart function error: ' . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, 'Internal Server Error. Please Contact Admin', []);
        }
    }

    public function updateCart($data)
    {
        try {
            $cartItemId = $data['id'] ?? null;
            $quantity = (int) ($data['quantity'] ?? 0);

            if (!$cartItemId) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Cart item id is required',
                    'data' => [],
                ];
            }

            if ($quantity < 1) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Quantity must be at least 1',
                    'data' => [],
                ];
            }

            $cartItem = DB::table('cart_items')
                ->join('cart', 'cart.id', '=', 'cart_items.cart_id')
                ->where('cart.user_id', Auth::user()->id)
                ->where('cart_items.id', $cartItemId)
                ->select('cart_items.id')
                ->first();

            if (!$cartItem) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Cart item not found',
                    'data' => [],
                ];
            }

            DB::table('cart_items')
                ->where('id', $cartItemId)
                ->update(['quantity' => $quantity]);

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => 'Cart updated successfully',
                'data' => [],
            ];
        } catch (Exception $ex) {
            Log::error('CartService : updateCart function error: ' . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, 'Internal Server Error. Please Contact Admin', []);
        }
    }

    public function removeCartItem($cartItemId)
    {
        try {
            $cartItem = DB::table('cart_items')
                ->join('cart', 'cart.id', '=', 'cart_items.cart_id')
                ->where('cart.user_id', Auth::user()->id)
                ->where('cart_items.id', $cartItemId)
                ->select('cart_items.id', 'cart_items.cart_id')
                ->first();

            if (!$cartItem) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Cart item not found',
                    'data' => [],
                ];
            }

            DB::beginTransaction();

            DB::table('cart_items')
                ->where('id', $cartItemId)
                ->delete();

            $remainingItems = DB::table('cart_items')
                ->where('cart_id', $cartItem->cart_id)
                ->count();

            if ($remainingItems === 0) {
                DB::table('cart')
                    ->where('id', $cartItem->cart_id)
                    ->delete();
            }

            DB::commit();

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => 'Cart item removed successfully',
                'data' => [],
            ];
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('CartService : removeCartItem function error: ' . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, 'Internal Server Error. Please Contact Admin', []);
        }
    }

    public function myCart()
    {
        try {
            $cartProducts = DB::table('cart')
                ->join('cart_items', 'cart.id', '=', 'cart_items.cart_id')
                ->join('products', 'cart_items.product_id', '=', 'products.id')
                ->join('product_images', 'products.id', '=', 'product_images.product_id')
                ->where('cart.user_id', Auth::user()->id)
                ->where('is_primary', 1)
                ->select(
                    'products.name',
                    'products.price',
                    'product_images.image_url',
                    'cart_items.*'
                )
                ->get();

            Log::info('CartService : myCart function executed successfully. Response' . json_encode($cartProducts));

            if ($cartProducts->count() > 0) {
                return [
                    'status' => ResponseConstants::SUCCESS,
                    'message' => 'Cart Product fetched successfully',
                    'data' => $cartProducts,
                ];
            }

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => 'No product in cart found',
                'data' => [],
            ];
        } catch (Exception $ex) {
            Log::error('ProductService :myCart function error: ' . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, 'Internal Server Error. Please Contact Admin', []);
        }
    }

    public function applyCoupon($couponId)
    {
        try {
            $cartProducts = $this->myCart();
            if ($cartProducts['status'] !== ResponseConstants::SUCCESS || $cartProducts['data']->count() === 0) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Add an item to your cart before applying a coupon.',
                    'data' => ['totalAmount' => 0, 'couponAmount' => 0, 'grandTotal' => 0],
                ];
            }

            $totalAmount = 0;
            foreach ($cartProducts['data'] as $item) {
                $totalAmount += $item->price * $item->quantity;
            }

            $couponCode = trim((string) $couponId);
            $coupon = DB::table('coupons')
                ->whereRaw('LOWER(code) = ?', [strtolower($couponCode)])
                ->first();

            if (!$coupon) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Coupon not found.',
                    'data' => ['totalAmount' => $totalAmount, 'couponAmount' => 0, 'grandTotal' => $totalAmount],
                ];
            }

            $today = now()->toDateString();
            if (($coupon->start_date && $coupon->start_date > $today) || ($coupon->end_date && $coupon->end_date < $today)) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Coupon is not currently valid.',
                    'data' => ['totalAmount' => $totalAmount, 'couponAmount' => 0, 'grandTotal' => $totalAmount],
                ];
            }

            if ($coupon->discount_type !== CouponTypeConstant::PERCENTAGE && $coupon->discount_value > $totalAmount) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'This coupon amount is greater than the cart total.',
                    'data' => ['totalAmount' => $totalAmount, 'couponAmount' => 0, 'grandTotal' => $totalAmount],
                ];
            }

            if ($coupon->discount_type === CouponTypeConstant::PERCENTAGE) {
                $couponAmount = $totalAmount * $coupon->discount_value / 100;
            } else {
                $couponAmount = $coupon->discount_value;
            }

            $couponAmount = min(round((float) $couponAmount, 2), $totalAmount);

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => 'Coupon applied successfully.',
                'data' => [
                    'code' => $coupon->code,
                    'discountType' => $coupon->discount_type,
                    'totalAmount' => $totalAmount,
                    'couponAmount' => $couponAmount,
                    'grandTotal' => round($totalAmount - $couponAmount, 2),
                ],
            ];
        } catch (Exception $ex) {
            Log::error('CartService : applyCoupon function error: ' . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, 'Internal Server Error. Please Contact Admin', []);
        }
    }
}
