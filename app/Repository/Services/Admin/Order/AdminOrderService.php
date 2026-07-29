<?php

namespace App\Repository\Services\Admin\Order;

use Illuminate\Support\Facades\DB;

class AdminOrderService
{
    public function paginate(int $perPage, int $page, string $search)
    {
        return DB::table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin(DB::raw('(SELECT order_id, SUM(quantity) item_count FROM order_items GROUP BY order_id) oi'), 'oi.order_id', '=', 'o.id')
            ->select('o.id', 'o.total_amount', 'o.status', 'o.payment_status', 'o.tran_id', 'o.created_at', 'u.name as customer_name', 'u.email as customer_email', DB::raw('COALESCE(oi.item_count, 0) as item_count'))
            ->where(function ($query) use ($search) {
                $query->where('o.id', 'like', "%{$search}%")
                    ->orWhere('u.name', 'like', "%{$search}%")
                    ->orWhere('u.email', 'like', "%{$search}%")
                    ->orWhere('o.tran_id', 'like', "%{$search}%");
            })
            ->orderByDesc('o.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $id)
    {
        $order = DB::table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('user_addresses as a', 'a.id', '=', 'o.address_id')
            ->where('o.id', $id)
            ->select('o.*', 'u.name as customer_name', 'u.email as customer_email', 'u.phone as customer_phone', 'a.address_line1', 'a.address_line2', 'a.city as address_city', 'a.state as address_state', 'a.postal_code', 'a.country as address_country', 'a.phone as address_phone')
            ->first();
        if (! $order) {
            return null;
        }

        $order->items = DB::table('order_items as oi')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.order_id', $id)
            ->select('oi.id', 'oi.product_id', 'oi.quantity', 'oi.price', 'p.name as product_name', 'p.sku')
            ->get();
        $order->tracking = DB::table('order_tracking')->where('order_id', $id)->orderBy('updated_at')->orderBy('id')->get();

        return $order;
    }

    public function updateTracking(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $order = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            if (! $order) {
                return null;
            }
            $status = $data['status'];
            if (in_array($status, ['processing', 'shipped', 'delivered'], true) && ! DB::table('order_tracking')->where('order_id', $id)->where('status', '_inventory_deducted')->exists()) {
                $this->deductInventory($id);
            }
            if ($status === 'cancelled' && DB::table('order_tracking')->where('order_id', $id)->where('status', '_inventory_deducted')->exists() && ! DB::table('order_tracking')->where('order_id', $id)->where('status', '_inventory_restored')->exists()) {
                $this->restoreInventory($id, '_inventory_deducted', '_inventory_restored');
            }
            DB::table('orders')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
            DB::table('order_tracking')->insert(['order_id' => $id, 'status' => $status, 'location' => $data['location'] ?? null, 'updated_at' => now()]);

            return $this->find($id);
        });
    }

    private function deductInventory(int $orderId): void
    {
        foreach (DB::table('order_items')->where('order_id', $orderId)->get(['product_id', 'quantity']) as $item) {
            $stock = DB::table('inventory')->where('product_id', $item->product_id)->orderBy('id')->lockForUpdate()->first();
            if (! $stock || $stock->stock_quantity < $item->quantity) {
                throw new \InvalidArgumentException('Insufficient inventory for product #'.$item->product_id.'.');
            }
            DB::table('inventory')->where('id', $stock->id)->decrement('stock_quantity', $item->quantity);
            DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => '_inventory_deducted', 'location' => $stock->id.':'.$item->quantity, 'updated_at' => now()]);
        }
    }

    private function restoreInventory(int $orderId, string $sourceStatus, string $restoreStatus): void
    {
        foreach (DB::table('order_tracking')->where('order_id', $orderId)->where('status', $sourceStatus)->get() as $movement) {
            [$inventoryId, $quantity] = array_map('intval', explode(':', (string) $movement->location));
            DB::table('inventory')->where('id', $inventoryId)->increment('stock_quantity', $quantity);
        }
        DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => $restoreStatus, 'location' => null, 'updated_at' => now()]);
    }
}
