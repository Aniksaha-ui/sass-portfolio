<?php

namespace App\Repository\Services\User\Cart;


use App\Constants\CouponTypeConstant;
use App\Constants\ResponseConstants;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;
use Illuminate\Support\Facades\Auth;

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

            #check already this user have any cart previously
            if ($cartAlreadyExist) {
                $cartId = $cartAlreadyExist->id;

                $cartItems = [];
                foreach ($data as $item) {
                    #if product already exist in cart then update quantity
                    $product = DB::table('cart_items')
                        ->where('cart_id', $cartId)
                        ->where('product_id', $item['product_id'])
                        ->first();
                    if ($product) {
                        DB::table('cart_items')
                            ->where('cart_id', $cartId)
                            ->where('product_id', $item['product_id'])
                            ->update(['quantity' => $product->quantity + $item['quantity']]);
                        continue;
                    }

                    #if product not exist in cart then add
                    $cartItems[] = [
                        'cart_id' => $cartId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ];
                }

                #insert into cart items
                $cartProducts = DB::table('cart_items')->insert($cartItems);

                if ($cartProducts && $cartProducts) {
                    DB::commit();
                    return [
                        "status" => ResponseConstants::SUCCESS,
                        "message" => "Product added to cart successfully",
                        "data" => []
                    ];
                } else {
                    DB::rollBack();
                    return [
                        "status" => ResponseConstants::FAILED,
                        "message" => "Product not added to cart",
                        "data" => []
                    ];
                }
            }

            #if user have no cart previously
            $cartInfo = [
                'user_id' => Auth::user()->id,
            ];

            #create cart 
            $cartId = DB::table('cart')->insertGetId($cartInfo);
            $cartItems = [];
            foreach ($data as $item) {
                #if product already exist in cart then update quantity
                $product = DB::table('cart_items')
                    ->where('cart_id', $cartId)
                    ->where('product_id', $item['product_id'])
                    ->first();
                if ($product) {
                    DB::table('cart_items')
                        ->where('cart_id', $cartId)
                        ->where('product_id', $item['product_id'])
                        ->update(['quantity' => $product->quantity + $item['quantity']]);
                    continue;
                }
                #add to cart item
                $cartItems[] = [
                    'cart_id' => $cartId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }
            #insert into cart items
            $cartProducts = DB::table('cart_items')->insert($cartItems);

            if ($cartProducts && $cartProducts) {
                DB::commit();
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Product added to cart successfully",
                    "data" => []
                ];
            } else {
                DB::rollBack();
                return [
                    "status" => ResponseConstants::FAILED,
                    "message" => "Product not added to cart",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("CartService : addToCart function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function myCart()
    {
        try {
            $cartProducts = DB::table('cart')
                ->join('cart_items', 'cart.id', '=', 'cart_items.cart_id')
                ->join('products', 'cart_items.product_id', '=', 'products.id')
                ->select('products.name', 'products.price', 'cart_items.*')
                ->get();
            if ($cartProducts->count() > 0) {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Cart Product fetched successfully",
                    "data" => $cartProducts
                ];
            } else {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "No product in cart found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProductService :myCart function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function applyCoupon($couponId)
    {
        try {
            $cartProducts = $this->myCart();
            if ($cartProducts['status'] == ResponseConstants::SUCCESS && $cartProducts['data']->count() > 0) {
                $totalAmount = 0;
                foreach ($cartProducts['data'] as $item) {
                    $totalAmount += $item->price * $item->quantity;
                }

                $coupon = DB::table('coupons')->where('code', $couponId)->first();

                if (!$coupon) {
                    return [
                        'status' => ResponseConstants::SUCCESS,
                        'message' => 'Coupon not found',
                        'data' => []
                    ];
                }


                if ($coupon) {
                    if ($coupon->discount_value > $totalAmount) {
                        return [
                            'status' => ResponseConstants::SUCCESS,
                            'message' => 'Coupon amount is greater than total amount',
                            'data' => [
                                "totalAmount" => $totalAmount,
                                "couponAmount" => 0,
                                "grandTotal" => $totalAmount - 0
                            ]
                        ];
                    }

                    $discountType = $coupon->discount_type;
                    if ($discountType == CouponTypeConstant::PERCENTAGE) {
                        $couponAmount = $totalAmount * $coupon->discount_value / 100;
                    } else {
                        $couponAmount = $coupon->discount_value;
                    }
                    return [
                        "status" => ResponseConstants::SUCCESS,
                        "message" => "Coupon applied successfully",
                        "data" => [
                            "totalAmount" => $totalAmount,
                            "couponAmount" => $couponAmount,
                            "grandTotal" => $totalAmount - $couponAmount
                        ]
                    ];
                } else {
                    return [
                        "status" => ResponseConstants::SUCCESS,
                        "message" => "No coupon found",
                        "data" => []
                    ];
                }
            }
        } catch (Exception $ex) {
            Log::error("CartService : applyCoupon function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }
}
