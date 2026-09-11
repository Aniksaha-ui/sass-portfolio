<?php

namespace App\Repository\Services\Admin\Product;

use App\Helpers\admin\FileManageHelper;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function paginate($perPage, $page, $search)
    {
        $inventoryTotals = DB::table('inventory')
            ->select('product_id', DB::raw('COALESCE(SUM(stock_quantity), 0) as stock_quantity'))
            ->groupBy('product_id');

        return DB::table('products as p')
            ->join('subcategories as sc', 'sc.id', '=', 'p.subcategory_id')
            ->join('categories as c', 'c.id', '=', 'sc.category_id')
            ->leftJoinSub($inventoryTotals, 'i', fn ($join) => $join->on('i.product_id', '=', 'p.id'))
            ->select('p.id', 'p.name', 'p.sku', 'p.price', 'p.is_active', 'p.updated_at', 'c.name as category_name', 'sc.name as subcategory_name', 'i.stock_quantity')
            ->where(function ($query) use ($search) {
                $query->where('p.name', 'like', '%' . $search . '%')
                    ->orWhere('p.sku', 'like', '%' . $search . '%')
                    ->orWhere('c.name', 'like', '%' . $search . '%')
                    ->orWhere('sc.name', 'like', '%' . $search . '%');
            })
            ->orderByDesc('p.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function options()
    {
        return [
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'subcategories' => DB::table('subcategories')->orderBy('name')->get(['id', 'category_id', 'name']),
            'sections' => DB::table('sections')->where('is_active', 1)->orderBy('display_order')->get(['id', 'name']),
        ];
    }

    public function find($id)
    {
        $product = DB::table('products as p')->join('subcategories as sc', 'sc.id', '=', 'p.subcategory_id')
            ->select('p.*', 'sc.category_id')->where('p.id', $id)->first();
        if (!$product) return null;

        $product->stock_breakdown = DB::table('inventory')
            ->where('product_id', $id)
            ->orderBy('warehouse_location')
            ->orderBy('id')
            ->get(['id', 'warehouse_location', 'stock_quantity']);
        $receipts = DB::table('stock_receipts as sr')
            ->join('procurements as procurement', 'procurement.id', '=', 'sr.procurement_id')
            ->join('requisitions as requisition', 'requisition.id', '=', 'procurement.requisition_id')
            ->leftJoin('users as receiver', 'receiver.id', '=', 'sr.received_by')
            ->where('sr.product_id', $id)
            ->select('sr.id', 'requisition.requisition_number', 'procurement.procurement_number', 'sr.warehouse_location')
            ->selectRaw("'Stock receipt' as transaction_type, sr.quantity_received as quantity_change, sr.stock_after, sr.received_at as transaction_date, receiver.name as performed_by, NULL as reason");
        $adjustments = DB::table('inventory_adjustments as adjustment')
            ->leftJoin('users as adjuster', 'adjuster.id', '=', 'adjustment.adjusted_by')
            ->where('adjustment.product_id', $id)
            ->select('adjustment.id')
            ->selectRaw("NULL as requisition_number, NULL as procurement_number, adjustment.warehouse_location, 'Stock adjustment' as transaction_type, adjustment.adjustment_quantity as quantity_change, adjustment.new_quantity as stock_after, adjustment.created_at as transaction_date, adjuster.name as performed_by, adjustment.reason");
        $product->stock_history = DB::query()->fromSub($receipts->unionAll($adjustments), 'stock_history')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
        $product->images = DB::table('product_images')->where('product_id', $id)->orderByDesc('is_primary')->orderBy('id')->get(['id', 'image_url', 'is_primary']);
        $product->discount = DB::table('product_discounts')->where('product_id', $id)->orderByDesc('id')->first(['discount_type', 'discount_value', 'start_date', 'end_date']);
        $sections = DB::table('section_products')->where('product_id', $id)->orderBy('display_order')->orderBy('id')->get(['section_id', 'display_order']);
        $product->section_ids = $sections->pluck('section_id')->values();
        $product->display_order = $sections->first() ? $sections->first()->display_order : 1;
        $product->stock_quantity = $product->stock_breakdown->sum('stock_quantity');
        return $product;
    }

    public function report($id)
    {
        $product = DB::table('products')->where('id', $id)->first(['id', 'name', 'sku', 'price']);
        if (!$product) return null;

        $sales = DB::table('order_items as item')
            ->join('orders as order', 'order.id', '=', 'item.order_id')
            ->where('item.product_id', $id)
            ->whereIn('order.status', ['processing', 'shipped', 'delivered'])
            ->selectRaw('COALESCE(SUM(item.quantity), 0) as units_sold, COALESCE(SUM(item.quantity * item.price), 0) as sales_amount, COUNT(DISTINCT order.id) as orders_count')
            ->first();
        $costs = DB::table('stock_receipts as receipt')
            ->join('requisition_products as line', 'line.id', '=', 'receipt.requisition_product_id')
            ->where('receipt.product_id', $id)
            ->selectRaw('COALESCE(SUM(receipt.quantity_received), 0) as units_received, COALESCE(SUM(receipt.quantity_received * line.unit_cost), 0) as total_cost')
            ->first();
        $averageUnitCost = (int) $costs->units_received > 0 ? (float) $costs->total_cost / (int) $costs->units_received : 0;
        $costOfSold = (float) $sales->units_sold * $averageUnitCost;

        return [
            'product' => $product,
            'summary' => [
                'units_sold' => (int) $sales->units_sold,
                'sales_amount' => round((float) $sales->sales_amount, 2),
                'orders_count' => (int) $sales->orders_count,
                'average_unit_cost' => round($averageUnitCost, 2),
                'cost_of_sold' => round($costOfSold, 2),
                'profit' => round((float) $sales->sales_amount - $costOfSold, 2),
            ],
            'requisitions' => DB::table('requisition_products as line')
                ->join('requisitions as requisition', 'requisition.id', '=', 'line.requisition_id')
                ->leftJoin('procurements as procurement', 'procurement.requisition_id', '=', 'requisition.id')
                ->where('line.product_id', $id)
                ->orderByDesc('requisition.id')
                ->get(['requisition.requisition_number', 'requisition.status as requisition_status', 'requisition.requested_by', 'requisition.supplier_name', 'requisition.reference_no', 'requisition.created_at as requisition_date', 'procurement.procurement_number', 'procurement.status as procurement_status', 'line.quantity as requested_quantity', 'line.quantity_received', 'line.unit_cost']),
        ];
    }

    public function save(array $data, $id = null)
    {
        return DB::transaction(function () use ($data, $id) {
            $productData = [
                'subcategory_id' => $data['subcategory_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'sku' => $data['sku'] ?: null,
                'is_active' => $data['is_active'],
            ];
            if ($id) {
                DB::table('products')->where('id', $id)->update($productData);
                $productId = $id;
            } else {
                $productId = DB::table('products')->insertGetId($productData);
            }

            if (!$id) {
                DB::table('inventory')->insert(['product_id' => $productId, 'stock_quantity' => $data['stock_quantity'], 'warehouse_location' => $data['warehouse_location'] ?? null]);
            }
            if (!empty($data['images'])) {
                $hasImages = DB::table('product_images')->where('product_id', $productId)->exists();
                foreach ($data['images'] as $index => $image) {
                    $path = FileManageHelper::uploadFile('products', $image);
                    DB::table('product_images')->insert([
                        'product_id' => $productId,
                        'image_url' => $path,
                        'is_primary' => !$hasImages && $index === 0,
                    ]);
                }
            }
            DB::table('product_discounts')->where('product_id', $productId)->delete();
            if (!empty($data['discount_type'])) DB::table('product_discounts')->insert(['product_id' => $productId, 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value'], 'start_date' => $data['discount_start_date'] ?: null, 'end_date' => $data['discount_end_date'] ?: null]);
            DB::table('section_products')->where('product_id', $productId)->delete();
            foreach ($data['section_ids'] as $sectionId) DB::table('section_products')->insert(['product_id' => $productId, 'section_id' => $sectionId, 'display_order' => $data['display_order']]);
            return $this->find($productId);
        });
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            if (!DB::table('products')->where('id', $id)->exists()) return false;
            $images = DB::table('product_images')->where('product_id', $id)->pluck('image_url');
            foreach (['product_images', 'product_discounts', 'inventory', 'section_products', 'product_reviews', 'supplier_products'] as $table) DB::table($table)->where('product_id', $id)->delete();
            foreach ($images as $path) FileManageHelper::deleteFile($path);
            return (bool) DB::table('products')->where('id', $id)->delete();
        });
    }
}
