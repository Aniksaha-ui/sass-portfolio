<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->query('search', '');
        $records = DB::table('returns as r')->join('orders as o', 'o.id', '=', 'r.order_id')->join('products as p', 'p.id', '=', 'r.product_id')->where(fn ($query) => $query->where('o.id', 'like', "%{$search}%")->orWhere('p.name', 'like', "%{$search}%")->orWhere('r.status', 'like', "%{$search}%"))->orderByDesc('r.id')->paginate(min(max((int) $request->query('perPage', 10), 1), 100), ['r.*', 'p.name as product_name'], 'page', max((int) $request->query('page', 1), 1));

        return $this->respond(true, 'Returns fetched successfully', $records);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        if (! $this->orderContainsProduct($data['order_id'], $data['product_id'])) {
            return $this->respond(false, 'The product does not belong to this order.', [], 422);
        }
        $id = DB::table('returns')->insertGetId($data);

        return $this->respond(true, 'Return requested successfully', DB::table('returns')->find($id), 201);
    }

    public function update(int $id, Request $request)
    {
        $return = DB::table('returns')->where('id', $id)->first();
        if (! $return) {
            return $this->respond(false, 'Return not found', [], 404);
        }
        $data = $this->validated($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        if (! $this->orderContainsProduct($data['order_id'], $data['product_id'])) {
            return $this->respond(false, 'The product does not belong to this order.', [], 422);
        }
        DB::transaction(function () use ($return, $data, $id) {
            if ($data['status'] === 'approved' && $return->status !== 'approved') {
                $this->restoreReturnedStock($id, $return->order_id, $return->product_id);
            }
            DB::table('returns')->where('id', $id)->update($data);
            if ($data['status'] === 'refunded' && $return->status !== 'refunded') {
                $userId = DB::table('orders')->where('id', $return->order_id)->value('user_id');
                DB::table('refunds')->insert(['return_id' => $id, 'order_id' => $return->order_id, 'user_id' => $userId, 'amount' => $data['refund_amount'], 'status' => 'processed', 'refund_reference' => 'REF-'.$id.'-'.now()->format('YmdHis'), 'processed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return $this->respond(true, 'Return updated successfully', DB::table('returns')->find($id));
    }

    public function destroy(int $id)
    {
        return DB::table('returns')->where('id', $id)->delete() ? $this->respond(true, 'Return deleted successfully', []) : $this->respond(false, 'Return not found', [], 404);
    }

    private function restoreReturnedStock(int $returnId, int $orderId, int $productId): void
    {
        $quantity = (int) DB::table('order_items')->where('order_id', $orderId)->where('product_id', $productId)->sum('quantity');
        $stock = DB::table('inventory')->where('product_id', $productId)->orderBy('id')->lockForUpdate()->first();
        if (! $stock || $quantity < 1) {
            throw new \InvalidArgumentException('Unable to restore returned stock.');
        }
        DB::table('inventory')->where('id', $stock->id)->increment('stock_quantity', $quantity);
        DB::table('inventory_adjustments')->insert(['inventory_id' => $stock->id, 'product_id' => $productId, 'warehouse_location' => $stock->warehouse_location, 'previous_quantity' => $stock->stock_quantity, 'new_quantity' => $stock->stock_quantity + $quantity, 'adjustment_quantity' => $quantity, 'reason' => 'Return #'.$returnId.' approved for order #'.$orderId, 'adjusted_by' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function orderContainsProduct(int $orderId, int $productId): bool
    {
        return DB::table('order_items')->where('order_id', $orderId)->where('product_id', $productId)->exists();
    }

    private function validated(Request $request)
    {
        $validator = Validator::make($request->all(), ['order_id' => 'required|integer|exists:orders,id', 'product_id' => 'required|integer|exists:products,id', 'reason' => 'nullable|string', 'status' => 'required|in:requested,approved,rejected,refunded', 'refund_amount' => 'nullable|numeric|min:0']);

        return $validator->fails() ? $this->respond(false, 'Validation error', $validator->errors(), 422) : $validator->validated();
    }

    private function respond(bool $status, string $message, $data, int $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
