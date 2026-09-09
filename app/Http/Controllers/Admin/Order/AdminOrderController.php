<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderTrackingRequest;
use App\Repository\Services\Admin\Order\AdminOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminOrderController extends Controller
{
    public function __construct(private AdminOrderService $orders) {}

    public function index(Request $request)
    {
        return $this->respond(true, 'Orders fetched successfully', $this->orders->paginate(min(max((int) $request->query('perPage', 10), 1), 100), max((int) $request->query('page', 1), 1), (string) ($request->query('search') ?? '')));
    }

    public function show($id)
    {
        $order = $this->orders->find((int) $id);

        return $order ? $this->respond(true, 'Order fetched successfully', $order) : $this->respond(false, 'Order not found.', [], 404);
    }

    public function updateTracking($id, OrderTrackingRequest $request)
    {
        $order = $this->orders->updateTracking((int) $id, $request->validated());

        return $order ? $this->respond(true, 'Order tracking updated successfully.', $order) : $this->respond(false, 'Order not found.', [], 404);
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
