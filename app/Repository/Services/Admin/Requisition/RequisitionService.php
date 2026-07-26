<?php

namespace App\Repository\Services\Admin\Requisition;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequisitionService
{
    public function options() {
        $balanceColumn = Schema::hasColumn('company_accounts', 'amount') ? 'amount' : (Schema::hasColumn('company_accounts', 'balance') ? 'balance' : null);
        $accounts = DB::table('company_accounts')->orderBy('account_name')->select(['id', 'account_name', 'account_number', 'type']);
        $accounts->selectRaw($balanceColumn ? "{$balanceColumn} as balance" : '0 as balance');
        return ['products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'sku']), 'warehouses' => DB::table('inventory')->whereNotNull('warehouse_location')->where('warehouse_location', '!=', '')->distinct()->orderBy('warehouse_location')->pluck('warehouse_location')->values(), 'company_accounts' => $accounts->get()];
    }
    public function requisitions($perPage, $page, $search) {
        $rows = DB::table('requisitions as r')->leftJoin('requisition_products as rp', 'rp.requisition_id', '=', 'r.id')->leftJoin('users as approver', 'approver.id', '=', 'r.accepted_by')
            ->select('r.id', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.status', 'r.created_at', 'r.accepted_at', 'approver.name as approved_by_name', DB::raw('count(rp.id) as items_count'), DB::raw('coalesce(sum(rp.quantity * rp.unit_cost), 0) as total_amount'))
            ->where(fn ($q) => $q->where('r.requisition_number', 'like', "%{$search}%")->orWhere('r.requested_by', 'like', "%{$search}%"))->groupBy('r.id', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.status', 'r.created_at', 'r.accepted_at', 'approver.name')->orderByDesc('r.id')->paginate($perPage, ['*'], 'page', $page);
        foreach ($rows as $row) $row->items = $this->requisitionItems($row->id);
        return $rows;
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
        $rows = DB::table('procurements as p')->join('requisitions as r', 'r.id', '=', 'p.requisition_id')->join('requisition_products as rp', 'rp.requisition_id', '=', 'r.id')->leftJoin('users as approver', 'approver.id', '=', 'r.accepted_by')
            ->select('p.id', 'p.procurement_number', 'p.status', 'p.paid_at', 'p.payment_amount', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.accepted_at', 'approver.name as approved_by_name', DB::raw('count(rp.id) as items_count'), DB::raw('coalesce(sum(rp.quantity * rp.unit_cost), 0) as total_amount'))
            ->where(fn ($q) => $q->where('p.procurement_number', 'like', "%{$search}%")->orWhere('r.requisition_number', 'like', "%{$search}%"))->groupBy('p.id', 'p.procurement_number', 'p.status', 'p.paid_at', 'p.payment_amount', 'r.requisition_number', 'r.requested_by', 'r.department', 'r.priority', 'r.required_by', 'r.supplier_name', 'r.reference_no', 'r.notes', 'r.accepted_at', 'approver.name')->orderByDesc('p.id')->paginate($perPage, ['*'], 'page', $page);
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
    public function markOnHand($id, $warehouseLocation, array $payments, $userId = null, $ipAddress = null) {
        return DB::transaction(function () use ($id, $warehouseLocation, $payments, $userId, $ipAddress) {
            $procurement = DB::table('procurements')->where('id', $id)->lockForUpdate()->first();
            if (!$procurement || $procurement->status === 'on_hand') return null;
            $items = DB::table('requisition_products')->where('requisition_id', $procurement->requisition_id)->lockForUpdate()->get(['id', 'quantity', 'quantity_received', 'unit_cost']);
            $outstanding = $items->filter(fn ($item) => $item->quantity > $item->quantity_received);
            if ($outstanding->isEmpty()) return null;
            $amount = round((float) $outstanding->sum(fn ($item) => ($item->quantity - $item->quantity_received) * $item->unit_cost), 2);
            $paidAmount = round((float) collect($payments)->sum(fn ($payment) => (float) $payment['amount']), 2);
            if (abs($paidAmount - $amount) > 0.009) throw new \InvalidArgumentException('Payment amounts must equal the procurement total of ' . number_format($amount, 2) . '.');
            $accountIds = collect($payments)->pluck('company_account_id')->map(fn ($id) => (int) $id)->all();
            $balanceColumn = Schema::hasColumn('company_accounts', 'amount') ? 'amount' : (Schema::hasColumn('company_accounts', 'balance') ? 'balance' : null);
            if (!$balanceColumn) throw new \InvalidArgumentException('Company account balance column was not found.');
            $accounts = DB::table('company_accounts')->whereIn('id', $accountIds)->orderBy('id')->select('*')->selectRaw("{$balanceColumn} as amount")->lockForUpdate()->get()->keyBy('id');
            foreach ($payments as $payment) { $account = $accounts->get((int) $payment['company_account_id']); if (!$account) throw new \InvalidArgumentException('Selected company account was not found.'); if ((float) $account->amount < (float) $payment['amount']) throw new \InvalidArgumentException($account->account_name . ' does not have enough balance.'); }
            $receiptItems = $outstanding->map(fn ($item) => ['requisition_product_id' => $item->id, 'quantity_received' => $item->quantity - $item->quantity_received])->values()->all();
            $record = $this->receive($id, $receiptItems, $warehouseLocation, $userId);
            if (!$record) return null;
            $references = [];
            foreach (array_values($payments) as $index => $payment) { $account = $accounts->get((int) $payment['company_account_id']); $paymentAmount = round((float) $payment['amount'], 2); $reference = 'PROC-' . $id . '-' . ($index + 1) . '-' . now()->format('His'); $references[] = $reference; DB::table('company_accounts')->where('id', $account->id)->decrement($balanceColumn, $paymentAmount, ['updated_at' => now()]); DB::table('account_history')->insert(['user_id' => $userId ?: 0, 'user_account_type' => $account->type, 'user_account_no' => $account->account_number, 'getaway' => 'hand_cash', 'amount' => $paymentAmount, 'com_account_no' => $account->account_number, 'transaction_reference' => $reference, 'transaction_type' => 'd', 'purpose' => 'procurement', 'tran_date' => now(), 'ip_address' => $ipAddress]); DB::table('procurement_payments')->insert(['procurement_id' => $id, 'company_account_id' => $account->id, 'amount' => $paymentAmount, 'payment_reference' => $reference, 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now()]); }
            DB::table('procurements')->where('id', $id)->update(['company_account_id' => count($payments) === 1 ? $accountIds[0] : null, 'payment_amount' => $amount, 'payment_reference' => implode(',', $references), 'paid_at' => now(), 'updated_at' => now()]);
            return $this->procurement($id);
        });
    }
    public function stocks($perPage, $page, $search) { return DB::table('stock_receipts as sr')->join('products as pr', 'pr.id', '=', 'sr.product_id')->join('procurements as p', 'p.id', '=', 'sr.procurement_id')->select('sr.*', 'pr.name as product_name', 'p.procurement_number')->where(fn ($q) => $q->where('pr.name', 'like', "%{$search}%")->orWhere('p.procurement_number', 'like', "%{$search}%"))->orderByDesc('sr.id')->paginate($perPage, ['*'], 'page', $page); }
    private function requisition($id) { $record = DB::table('requisitions')->where('id', $id)->first(); if ($record) $record->items = $this->requisitionItems($id); return $record; }
    private function procurement($id) { $record = DB::table('procurements')->where('id', $id)->first(); if ($record) $record->items = $this->procurementItems($id); return $record; }
    private function procurementItems($id) { return DB::table('procurements as p')->join('requisition_products as rp', 'rp.requisition_id', '=', 'p.requisition_id')->join('products as pr', 'pr.id', '=', 'rp.product_id')->where('p.id', $id)->get(['rp.id as requisition_product_id', 'rp.product_id', 'rp.quantity', 'rp.quantity_received', 'rp.unit_cost', 'pr.name as product_name', 'pr.sku']); }
    private function requisitionItems($id) { return DB::table('requisition_products as rp')->join('products as pr', 'pr.id', '=', 'rp.product_id')->where('rp.requisition_id', $id)->get(['rp.id as requisition_product_id', 'rp.product_id', 'rp.quantity', 'rp.quantity_received', 'rp.unit_cost', 'pr.name as product_name', 'pr.sku']); }
}
