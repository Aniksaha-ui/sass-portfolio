<?php

namespace App\Http\Controllers\User\Order;

use App\Http\Controllers\Controller;
use App\Repository\Services\User\Order\OrderService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{

    private $orderService;
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }
    public function order(Request $request)
    {
        try {
            $response = $this->orderService->order($request->all());
            return response()->json($response);
        } catch (Exception $ex) {
            Log::info("OrderController : order function error: " . $ex->getMessage());
        }
    }

    public function getMyOrders()
    {
        try {
            $response = $this->orderService->myOrders();
            return response()->json([
                'isExecuted' => $response['status'],
                'message' => $response['message'],
                'data' => $response['data'],
            ], 200);
        } catch (Exception $ex) {
            Log::info('OrderController : getMyOrders function error: ' . $ex->getMessage());
        }
    }

    public function getOrderDetails($id)
    {
        try {
            $response = $this->orderService->orderDetails($id);
            return response()->json([
                'isExecuted' => $response['status'],
                'message' => $response['message'],
                'data' => $response['data'],
            ], $response['status'] ? 200 : 404);
        } catch (Exception $ex) {
            Log::info('OrderController : getOrderDetails function error: ' . $ex->getMessage());
        }
    }

    public function success(Request $request)
    {
        return $this->orderService->success($request);
    }

    public function fail(Request $request)
    {
        return $this->orderService->fail($request);
    }

    public function cancel(Request $request)
    {
        return $this->orderService->cancel($request);
    }
}
