<?php

namespace App\Http\Controllers\User\Cart;

use App\Http\Controllers\Controller;
use App\Repository\Services\User\Cart\CartService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{

    private $cartService;
    public function __construct(CartService $productService)
    {
        $this->cartService = $productService;
    }

    public function addToCart(Request $request)
    {
        try {
            $response = $this->cartService->addToCart($request->all());
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addToCart function error: " . $ex->getMessage());
        }
    }


    public function getMyCart()
    {
        try {
            $response = $this->cartService->myCart();
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addToCart function error: " . $ex->getMessage());
        }
    }


        public function applyCoupon($id)
    {
        try {
            $couponId = $id;
            $response = $this->cartService->applyCoupon($couponId);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addToCart function error: " . $ex->getMessage());
        }
    }

    


}
