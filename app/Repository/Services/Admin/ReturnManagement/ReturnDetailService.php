<?php

namespace App\Repository\Services\Admin\ReturnManagement;

use App\Repository\Services\Admin\Order\AdminOrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReturnDetailService
{
    public function __construct(private AdminOrderService $orders) {}

    public function findReturn(int $id): ?array
    {
        $return = DB::table('returns')->where('id', $id)->first();

        return $return ? $this->buildDetail($return) : null;
    }

    public function findRefund(int $id): ?array
    {
        $refund = DB::table('refunds')->where('id', $id)->first();
        if (! $refund) {
            return null;
        }

        $detail = $this->findReturn((int) $refund->return_id);
        if (! $detail) {
            return null;
        }

        $detail['refund'] = $refund;

        return $detail;
    }

    private function buildDetail(object $return): array
    {
        $order = $this->orders->find((int) $return->order_id);
        $product = DB::table('products')->where('id', $return->product_id)->first();
        $orderItems = DB::table('order_items')
            ->where('order_id', $return->order_id)
            ->where('product_id', $return->product_id)
            ->orderBy('id')
            ->get();
        $refund = DB::table('refunds')->where('return_id', $return->id)->first();
        $inventory = DB::table('inventory')
            ->where('product_id', $return->product_id)
            ->orderBy('id')
            ->get(['id', 'product_id', 'warehouse_location', 'stock_quantity']);
        $adjustments = DB::table('inventory_adjustments as adjustment')
            ->leftJoin('users as administrator', 'administrator.id', '=', 'adjustment.adjusted_by')
            ->where('adjustment.product_id', $return->product_id)
            ->where('adjustment.reason', 'like', 'Return #'.$return->id.' %')
            ->orderByDesc('adjustment.id')
            ->get([
                'adjustment.*',
                'administrator.name as adjusted_by_name',
                'administrator.email as adjusted_by_email',
            ]);

        if ($product) {
            $product->images = DB::table('product_images')
                ->where('product_id', $return->product_id)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get(['id', 'image_url', 'is_primary']);
        }

        return [
            'return' => $return,
            'refund' => $refund,
            'order' => $order,
            'product' => $product,
            'order_items' => $orderItems,
            'inventory' => $inventory,
            'stock_restorations' => $this->stockRestorations(
                $return,
                $order?->tracking ?? collect(),
                $adjustments
            ),
        ];
    }

    private function stockRestorations(object $return, Collection $tracking, Collection $adjustments): Collection
    {
        if ($adjustments->isNotEmpty()) {
            return $adjustments->map(function ($adjustment) {
                $adjustment->source = 'return_approval';

                return $adjustment;
            });
        }

        $restoredMarker = $tracking->firstWhere('status', '_inventory_restored');
        if (! $restoredMarker) {
            return collect();
        }

        return $tracking
            ->where('status', '_inventory_deducted')
            ->map(function ($movement) use ($return, $restoredMarker) {
                $parts = explode(':', (string) $movement->location);
                if (count($parts) !== 2) {
                    return null;
                }

                [$inventoryId, $quantity] = array_map('intval', $parts);
                $inventory = DB::table('inventory')->where('id', $inventoryId)->first();
                if (! $inventory || (int) $inventory->product_id !== (int) $return->product_id) {
                    return null;
                }

                return (object) [
                    'id' => null,
                    'source' => 'order_cancellation',
                    'inventory_id' => $inventoryId,
                    'product_id' => (int) $return->product_id,
                    'warehouse_location' => $inventory->warehouse_location,
                    'previous_quantity' => null,
                    'new_quantity' => $inventory->stock_quantity,
                    'adjustment_quantity' => $quantity,
                    'reason' => 'Inventory restored when order #'.$return->order_id.' was cancelled.',
                    'adjusted_by' => null,
                    'adjusted_by_name' => 'System',
                    'adjusted_by_email' => null,
                    'created_at' => $restoredMarker->updated_at,
                    'updated_at' => $restoredMarker->updated_at,
                ];
            })
            ->filter()
            ->values();
    }
}
