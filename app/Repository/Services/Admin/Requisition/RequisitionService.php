<?php

namespace App\Repository\Services\Admin\Requisition;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequisitionService
{
    public function options()
    {
        $balanceColumn = Schema::hasColumn('company_accounts', 'amount') ? 'amount' : (Schema::hasColumn('company_accounts', 'balance') ? 'balance' : null);
        $accounts = DB::table('company_accounts')->orderBy('account_name')->select(['id', 'account_name', 'account_number', 'type']);
        $accounts->selectRaw($balanceColumn ? "{$balanceColumn} as balance" : '0 as balance');
        return ['products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'sku']), 'warehouses' => DB::table('inventory')->whereNotNull('warehouse_location')->where('warehouse_location', '!=', '')->distinct()->orderBy('warehouse_location')->pluck('warehouse_location')->values(), 'company_accounts' => $accounts->get()];
    }
    public function requisitions($perPage, $page, $search)
    {
        $rows = DB::table('requisitions as r')->leftJoin('requisition_products as rp', 'rp.requisition_id', '=', 'r.id')->leftJoin('users as approver', 'approver.id', '=', 'r.accepted_by')
            ->select('r.id', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.status', 'r.created_at', 'r.accepted_at', 'approver.name as approved_by_name', DB::raw('count(rp.id) as items_count'), DB::raw('coalesce(sum(rp.quantity * rp.unit_cost), 0) as total_amount'))
            ->where(fn($q) => $q->where('r.requisition_number', 'like', "%{$search}%")->orWhere('r.requested_by', 'like', "%{$search}%"))->groupBy('r.id', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.status', 'r.created_at', 'r.accepted_at', 'approver.name')->orderByDesc('r.id')->paginate($perPage, ['*'], 'page', $page);
        foreach ($rows as $row) $row->items = $this->requisitionItems($row->id);
        return $rows;
    }
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $id = DB::table('requisitions')->insertGetId(['requisition_number' => 'REQ-' . now()->format('YmdHis') . '-' . random_int(100, 999), 'requested_by' => $data['requested_by'], 'department' => $data['department'] ?? null, 'priority' => $data['priority'] ?? 'normal', 'required_by' => $data['required_by'] ?? null, 'supplier_name' => $data['supplier_name'] ?? null, 'reference_no' => $data['reference_no'] ?? null, 'notes' => $data['notes'] ?? null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $item) DB::table('requisition_products')->insert(['requisition_id' => $id, 'product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'unit_cost' => $item['unit_cost'] ?? 0, 'created_at' => now(), 'updated_at' => now()]);
            return $this->requisition($id);
        });
    }
    public function accept($id, $userId = null)
    {
        return DB::transaction(function () use ($id, $userId) {
            $requisition = DB::table('requisitions')->where('id', $id)->lockForUpdate()->first();
            if (!$requisition || $requisition->status !== 'pending') return null;
            DB::table('requisitions')->where('id', $id)->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => $userId, 'updated_at' => now()]);
            DB::table('procurements')->insert(['requisition_id' => $id, 'procurement_number' => 'PO-' . now()->format('YmdHis') . '-' . random_int(100, 999), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            return $this->requisition($id);
        });
    }
    public function procurements($perPage, $page, $search)
    {
        $rows = DB::table('procurements as p')->join('requisitions as r', 'r.id', '=', 'p.requisition_id')->join('requisition_products as rp', 'rp.requisition_id', '=', 'r.id')->leftJoin('users as approver', 'approver.id', '=', 'r.accepted_by')
            ->select('p.id', 'p.procurement_number', 'p.status', 'p.paid_at', 'p.payment_amount', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.accepted_at', 'approver.name as approved_by_name', DB::raw('count(rp.id) as items_count'), DB::raw('coalesce(sum(rp.quantity * rp.unit_cost), 0) as total_amount'))
            ->where(fn($q) => $q->where('p.procurement_number', 'like', "%{$search}%")->orWhere('r.requisition_number', 'like', "%{$search}%"))->groupBy('p.id', 'p.procurement_number', 'p.status', 'p.paid_at', 'p.payment_amount', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.accepted_at', 'approver.name')->orderByDesc('p.id')->paginate($perPage, ['*'], 'page', $page);
        foreach ($rows as $row) $row->items = $this->procurementItems($row->id);
        return $rows;
    }
    public function receive($id, array $items, $warehouseLocation, $userId = null)
    {
        return DB::transaction(function () use ($id, $items, $warehouseLocation, $userId) {
            $procurement = DB::table('procurements')->where('id', $id)->lockForUpdate()->first();
            if (!$procurement || $procurement->status === 'on_hand') return null;
            $allowed = DB::table('requisition_products')->where('requisition_id', $procurement->requisition_id)->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $line = $allowed->get($item['requisition_product_id']);
                $quantity = (int) $item['quantity_received'];
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
    public function markOnHand($id, $warehouseLocation, array $payments, $userId = null, $ipAddress = null)
    {
        return DB::transaction(function () use ($id, $warehouseLocation, $payments, $userId, $ipAddress) {
            $procurement = DB::table('procurements')->where('id', $id)->lockForUpdate()->first();
            if (!$procurement || $procurement->status === 'on_hand') return null;
            $items = DB::table('requisition_products')->where('requisition_id', $procurement->requisition_id)->lockForUpdate()->get(['id', 'quantity', 'quantity_received', 'unit_cost']);
            $outstanding = $items->filter(fn($item) => $item->quantity > $item->quantity_received);
            if ($outstanding->isEmpty()) return null;
            $amount = round((float) $outstanding->sum(fn($item) => ($item->quantity - $item->quantity_received) * $item->unit_cost), 2);
            $paidAmount = round((float) collect($payments)->sum(fn($payment) => (float) $payment['amount']), 2);
            if (abs($paidAmount - $amount) > 0.009) throw new \InvalidArgumentException('Payment amounts must equal the procurement total of ' . number_format($amount, 2) . '.');
            $accountIds = collect($payments)->pluck('company_account_id')->map(fn($id) => (int) $id)->all();
            $balanceColumn = Schema::hasColumn('company_accounts', 'amount') ? 'amount' : (Schema::hasColumn('company_accounts', 'balance') ? 'balance' : null);
            if (!$balanceColumn) throw new \InvalidArgumentException('Company account balance column was not found.');
            $accounts = DB::table('company_accounts')->whereIn('id', $accountIds)->orderBy('id')->select('*')->selectRaw("{$balanceColumn} as amount")->lockForUpdate()->get()->keyBy('id');
            foreach ($payments as $payment) {
                $account = $accounts->get((int) $payment['company_account_id']);
                if (!$account) throw new \InvalidArgumentException('Selected company account was not found.');
                if ((float) $account->amount < (float) $payment['amount']) throw new \InvalidArgumentException($account->account_name . ' does not have enough balance.');
            }
            $receiptItems = $outstanding->map(fn($item) => ['requisition_product_id' => $item->id, 'quantity_received' => $item->quantity - $item->quantity_received])->values()->all();
            $record = $this->receive($id, $receiptItems, $warehouseLocation, $userId);
            if (!$record) return null;
            $references = [];
            foreach (array_values($payments) as $index => $payment) {
                $account = $accounts->get((int) $payment['company_account_id']);
                $paymentAmount = round((float) $payment['amount'], 2);
                $reference = 'PROC-' . $id . '-' . ($index + 1) . '-' . now()->format('His');
                $references[] = $reference;
                DB::table('company_accounts')->where('id', $account->id)->decrement($balanceColumn, $paymentAmount, ['updated_at' => now()]);
                DB::table('account_history')->insert(['user_id' => $userId ?: 0, 'user_account_type' => $account->type, 'user_account_no' => $account->account_number, 'getaway' => 'hand_cash', 'amount' => $paymentAmount, 'com_account_no' => $account->account_number, 'transaction_reference' => $reference, 'transaction_type' => 'd', 'purpose' => 'procurement', 'tran_date' => now(), 'ip_address' => $ipAddress]);
                DB::table('procurement_payments')->insert(['procurement_id' => $id, 'company_account_id' => $account->id, 'amount' => $paymentAmount, 'payment_reference' => $reference, 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('procurements')->where('id', $id)->update(['company_account_id' => count($payments) === 1 ? $accountIds[0] : null, 'payment_amount' => $amount, 'payment_reference' => implode(',', $references), 'paid_at' => now(), 'updated_at' => now()]);
            return $this->procurement($id);
        });
    }
    public function stocks($perPage, $page, $search, $procurementId = null, $productId = null, $warehouseLocation = null)
    {
        $query = $this->stockReceiptQuery()->where(fn($q) => $q->where('pr.name', 'like', "%{$search}%")->orWhere('p.procurement_number', 'like', "%{$search}%"));
        if ($procurementId !== null) $query->where('sr.procurement_id', (int) $procurementId);
        if ($productId !== null) $query->where('sr.product_id', (int) $productId);
        if ($warehouseLocation !== null) $query->where('sr.warehouse_location', $warehouseLocation);
        return $query->orderByDesc('sr.id')->paginate($perPage, ['*'], 'page', $page);
    }
    public function stockReceipt($id)
    {
        $receipt = $this->stockReceiptQuery()->where('sr.id', $id)->first();
        if (!$receipt) return null;
        $procurement = DB::table('procurements as p')
            ->join('requisitions as r', 'r.id', '=', 'p.requisition_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'r.accepted_by')
            ->leftJoin('users as receiver', 'receiver.id', '=', 'p.received_by')
            ->where('p.id', $receipt->procurement_id)
            ->first(['p.id', 'p.procurement_number', 'p.status', 'p.created_at as procurement_created_at', 'p.updated_at as procurement_updated_at', 'p.payment_amount', 'p.payment_reference', 'p.paid_at', 'p.received_at', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.status as requisition_status', 'r.created_at as requested_at', 'r.accepted_at', 'approver.name as approved_by_name', 'receiver.name as received_by_name']);
        $items = DB::table('stock_receipts as sr')->join('products as pr', 'pr.id', '=', 'sr.product_id')->join('requisition_products as rp', 'rp.id', '=', 'sr.requisition_product_id')->where('sr.procurement_id', $receipt->procurement_id)->orderBy('sr.id')->get(['sr.id', 'sr.product_id', 'sr.warehouse_location', 'sr.quantity_received', 'sr.stock_after', 'sr.received_at', 'pr.name as product_name', 'pr.sku as product_sku', 'rp.unit_cost']);
        $payments = DB::table('procurement_payments as pp')->leftJoin('company_accounts as ca', 'ca.id', '=', 'pp.company_account_id')->where('pp.procurement_id', $receipt->procurement_id)->orderBy('pp.id')->get(['pp.amount', 'pp.payment_reference', 'pp.paid_at', 'ca.account_name', 'ca.account_number']);
        return (object) ['receipt_id' => $receipt->id, 'procurement' => $procurement, 'items' => $items, 'payments' => $payments, 'received_by_name' => $receipt->received_by_name];
    }
    public function stockOptions()
    {
        return ['products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'sku']), 'warehouses' => DB::table('inventory')->whereNotNull('warehouse_location')->where('warehouse_location', '!=', '')->distinct()->orderBy('warehouse_location')->pluck('warehouse_location')->values()];
    }
    public function productStocks($perPage, $page, $search)
    {
        return DB::table('inventory as i')->join('products as p', 'p.id', '=', 'i.product_id')->select('i.id', 'i.product_id', 'i.warehouse_location', 'i.stock_quantity', 'p.name as product_name', 'p.sku')->where(fn($q) => $q->where('p.name', 'like', "%{$search}%")->orWhere('p.sku', 'like', "%{$search}%")->orWhere('i.warehouse_location', 'like', "%{$search}%"))->orderBy('p.name')->orderBy('i.warehouse_location')->paginate($perPage, ['*'], 'page', $page);
    }
    public function inventoryAdjustments($perPage, $page, $search)
    {
        return DB::table('inventory_adjustments as ia')->join('products as p', 'p.id', '=', 'ia.product_id')->leftJoin('users as u', 'u.id', '=', 'ia.adjusted_by')->select('ia.*', 'p.name as product_name', 'p.sku', 'u.name as adjusted_by_name')->where(fn($q) => $q->where('p.name', 'like', "%{$search}%")->orWhere('p.sku', 'like', "%{$search}%")->orWhere('ia.warehouse_location', 'like', "%{$search}%")->orWhere('ia.reason', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%"))->orderByDesc('ia.id')->paginate($perPage, ['*'], 'page', $page);
    }
    public function inventoryAdjustment($id)
    {
        return DB::table('inventory_adjustments as ia')->join('products as p', 'p.id', '=', 'ia.product_id')->leftJoin('users as u', 'u.id', '=', 'ia.adjusted_by')->where('ia.id', $id)->first(['ia.*', 'p.name as product_name', 'p.sku', 'p.price as product_price', 'u.name as adjusted_by_name', 'u.email as adjusted_by_email']);
    }
    public function saveProductStock(array $data, $userId = null)
    {
        return DB::transaction(function () use ($data, $userId) {
            $stock = DB::table('inventory')->where('product_id', $data['product_id'])->where('warehouse_location', $data['warehouse_location'])->lockForUpdate()->first();
            $previous = $stock->stock_quantity ?? 0;
            if ($stock) DB::table('inventory')->where('id', $stock->id)->update(['stock_quantity' => $data['stock_quantity']]);
            else {
                $id = DB::table('inventory')->insertGetId(['product_id' => $data['product_id'], 'warehouse_location' => $data['warehouse_location'], 'stock_quantity' => $data['stock_quantity']]);
                $stock = (object) ['id' => $id];
            }
            $this->recordAdjustment($stock->id, $data['product_id'], $data['warehouse_location'], $previous, $data['stock_quantity'], $data['reason'] ?? 'Opening stock', $userId);
            return DB::table('inventory as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.id', $stock->id)->first(['i.id', 'i.product_id', 'i.warehouse_location', 'i.stock_quantity', 'p.name as product_name', 'p.sku']);
        });
    }
    public function adjustProductStock($id, array $data, $userId = null)
    {
        return DB::transaction(function () use ($id, $data, $userId) {
            $stock = DB::table('inventory')->where('id', $id)->lockForUpdate()->first();
            if (!$stock) return null;
            DB::table('inventory')->where('id', $id)->update(['stock_quantity' => $data['stock_quantity']]);
            $this->recordAdjustment($id, $stock->product_id, $stock->warehouse_location, $stock->stock_quantity, $data['stock_quantity'], $data['reason'] ?? 'Manual stock adjustment', $userId);
            return DB::table('inventory as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.id', $id)->first(['i.id', 'i.product_id', 'i.warehouse_location', 'i.stock_quantity', 'p.name as product_name', 'p.sku']);
        });
    }
    private function recordAdjustment($inventoryId, $productId, $warehouse, $previous, $current, $reason, $userId)
    {
        DB::table('inventory_adjustments')->insert(['inventory_id' => $inventoryId, 'product_id' => $productId, 'warehouse_location' => $warehouse, 'previous_quantity' => $previous, 'new_quantity' => $current, 'adjustment_quantity' => $current - $previous, 'reason' => $reason, 'adjusted_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    }
    private function stockReceiptQuery()
    {
        return DB::table('stock_receipts as sr')->join('products as pr', 'pr.id', '=', 'sr.product_id')->join('procurements as p', 'p.id', '=', 'sr.procurement_id')->leftJoin('users as receiver', 'receiver.id', '=', 'sr.received_by')->select('sr.*', 'pr.name as product_name', 'pr.sku as product_sku', 'p.procurement_number', 'receiver.name as received_by_name');
    }
    private function requisition($id)
    {
        $record = DB::table('requisitions')->where('id', $id)->first();
        if ($record) $record->items = $this->requisitionItems($id);
        return $record;
    }
    private function procurement($id)
    {
        $record = DB::table('procurements')->where('id', $id)->first();
        if ($record) $record->items = $this->procurementItems($id);
        return $record;
    }
    private function procurementItems($id)
    {
        return DB::table('procurements as p')->join('requisition_products as rp', 'rp.requisition_id', '=', 'p.requisition_id')->join('products as pr', 'pr.id', '=', 'rp.product_id')->where('p.id', $id)->get(['rp.id as requisition_product_id', 'rp.product_id', 'rp.quantity', 'rp.quantity_received', 'rp.unit_cost', 'pr.name as product_name', 'pr.sku']);
    }
    private function requisitionItems($id)
    {
        return DB::table('requisition_products as rp')->join('products as pr', 'pr.id', '=', 'rp.product_id')->where('rp.requisition_id', $id)->get(['rp.id as requisition_product_id', 'rp.product_id', 'rp.quantity', 'rp.quantity_received', 'rp.unit_cost', 'pr.name as product_name', 'pr.sku']);
    }
}
