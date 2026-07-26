<?php

namespace App\Repository\Services\Admin\Requisition;

use Illuminate\Support\Facades\DB;

class RequisitionService
{
    public function options() { return ['products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'sku']), 'warehouses' => DB::table('inventory')->whereNotNull('warehouse_location')->where('warehouse_location', '!=', '')->distinct()->orderBy('warehouse_location')->pluck('warehouse_location')->values()]; }
    public function requisitions($perPage, $page, $search) {
        return DB::table('requisitions as r')->leftJoin('requisition_products as rp', 'rp.requisition_id', '=', 'r.id')
            ->select('r.id', 'r.requisition_number', 'r.requested_by', 'r.status', 'r.created_at', DB::raw('count(rp.id) as items_count'), DB::raw('coalesce(sum(rp.quantity * rp.unit_cost), 0) as total_amount'))
            ->where(fn ($q) => $q->where('r.requisition_number', 'like', "%{$search}%")->orWhere('r.requested_by', 'like', "%{$search}%"))->groupBy('r.id', 'r.requisition_number', 'r.requested_by', 'r.status', 'r.created_at')->orderByDesc('r.id')->paginate($perPage, ['*'], 'page', $page);
    }
    public function create(array $data) {
        return DB::transaction(function () use ($data) {
            $id = DB::table('requisitions')->insertGetId(['requisition_number' => 'REQ-' . now()->format('YmdHis') . '-' . random_int(100, 999), 'requested_by' => $data['requested_by'], 'department' => $data['department'] ?? null, 'priority' => $data['priority'] ?? 'normal', 'required_by' => $data['required_by'] ?? null, 'supplier_name' => $data['supplier_name'] ?? null, 'reference_no' => $data['reference_no'] ?? null, 'notes' => $data['notes'] ?? null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $item) DB::table('requisition_products')->insert(['requisition_id' => $id, 'product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'unit_cost' => $item['unit_cost'] ?? 0, 'created_at' => now(), 'updated_at' => now()]);
            return $this->requisition($id);
        });
    }
    public function accept($id, $userId = null) {
        return DB::transaction(function () use ($id, $userId) {
            $requisition = DB::table('requisitions')->where('id', $id)->lockForUpdate()->first();
            if (!$requisition || $requisition->status !== 'pending') return null;
            DB::table('requisitions')->where('id', $id)->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => $userId, 'updated_at' => now()]);
            DB::table('procurements')->insert(['requisition_id' => $id, 'procurement_number' => 'PO-' . now()->format('YmdHis') . '-' . random_int(100, 999), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            return $this->requisition($id);
        });
    }
    public function procurements($perPage, $page, $search) {
        $rows = DB::table('procurements as p')->join('requisitions as r', 'r.id', '=', 'p.requisition_id')->join('requisition_products as rp', 'rp.requisition_id', '=', 'r.id')
            ->select('p.id', 'p.procurement_number', 'p.status', 'r.requisition_number', DB::raw('count(rp.id) as items_count'), DB::raw('coalesce(sum(rp.quantity * rp.unit_cost), 0) as total_amount'))
            ->where(fn ($q) => $q->where('p.procurement_number', 'like', "%{$search}%")->orWhere('r.requisition_number', 'like', "%{$search}%"))->groupBy('p.id', 'p.procurement_number', 'p.status', 'r.requisition_number')->orderByDesc('p.id')->paginate($perPage, ['*'], 'page', $page);
        foreach ($rows as $row) $row->items = $this->procurementItems($row->id);
        return $rows;
    }
    public function receive($id, array $items, $warehouseLocation, $userId = null) {
        return DB::transaction(function () use ($id, $items, $warehouseLocation, $userId) {
            $procurement = DB::table('procurements')->where('id', $id)->lockForUpdate()->first();
            if (!$procurement || $procurement->status === 'on_hand') return null;
            $allowed = DB::table('requisition_products')->where('requisition_id', $procurement->requisition_id)->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $line = $allowed->get($item['requisition_product_id']); $quantity = (int) $item['quantity_received'];
                if (!$line || $quantity < 1 || $quantity > ($line->quantity - $line->quantity_received)) throw new \InvalidArgumentException('Invalid received quantity.');
                $inventory = DB::table('inventory')->where('product_id', $line->product_id)->where('warehouse_location', $warehouseLocation)->lockForUpdate()->first();
                $stockAfter = ($inventory->stock_quantity ?? 0) + $quantity;
                if ($inventory) DB::table('inventory')->where('id', $inventory->id)->update(['stock_quantity' => $stockAfter]);
                else DB::table('inventory')->insert(['product_id' => $line->product_id, 'stock_quantity' => $stockAfter, 'warehouse_location' => $warehouseLocation]);
                DB::table('requisition_products')->where('id', $line->id)->increment('quantity_received', $quantity, ['updated_at' => now()]);
                DB::table('stock_receipts')->insert(['procurement_id' => $id, 'requisition_product_id' => $line->id, 'product_id' => $line->product_id, 'warehouse_location' => $warehouseLocation, 'quantity_received' => $quantity, 'stock_after' => $stockAfter, 'received_at' => now(), 'received_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            }
            $remaining = DB::table('requisition_products')->where('requisition_id', $procurement->requisition_id)->whereColumn('quantity_received', '<', 'quantity')->exists();
            DB::table('procurements')->where('id', $id)->update(['status' => $remaining ? 'partial' : 'on_hand', 'received_at' => $remaining ? null : now(), 'received_by' => $userId, 'updated_at' => now()]);
            return $this->procurement($id);
        });
    }
    public function markOnHand($id, $warehouseLocation, $userId = null) {
        $procurement = DB::table('procurements')->where('id', $id)->first();
        if (!$procurement || $procurement->status === 'on_hand') return null;
        $items = DB::table('requisition_products')->where('requisition_id', $procurement->requisition_id)->get(['id', 'quantity', 'quantity_received'])->map(fn ($item) => ['requisition_product_id' => $item->id, 'quantity_received' => $item->quantity - $item->quantity_received])->filter(fn ($item) => $item['quantity_received'] > 0)->values()->all();
        return $items ? $this->receive($id, $items, $warehouseLocation, $userId) : null;
    }
    public function stocks($perPage, $page, $search) { return DB::table('stock_receipts as sr')->join('products as pr', 'pr.id', '=', 'sr.product_id')->join('procurements as p', 'p.id', '=', 'sr.procurement_id')->select('sr.*', 'pr.name as product_name', 'p.procurement_number')->where(fn ($q) => $q->where('pr.name', 'like', "%{$search}%")->orWhere('p.procurement_number', 'like', "%{$search}%"))->orderByDesc('sr.id')->paginate($perPage, ['*'], 'page', $page); }
    private function requisition($id) { $record = DB::table('requisitions')->where('id', $id)->first(); if ($record) $record->items = DB::table('requisition_products as rp')->join('products as p', 'p.id', '=', 'rp.product_id')->where('rp.requisition_id', $id)->get(['rp.*', 'p.name as product_name']); return $record; }
    private function procurement($id) { $record = DB::table('procurements')->where('id', $id)->first(); if ($record) $record->items = $this->procurementItems($id); return $record; }
    private function procurementItems($id) { return DB::table('procurements as p')->join('requisition_products as rp', 'rp.requisition_id', '=', 'p.requisition_id')->join('products as pr', 'pr.id', '=', 'rp.product_id')->where('p.id', $id)->get(['rp.id as requisition_product_id', 'rp.quantity', 'rp.quantity_received', 'pr.name as product_name']); }
}
