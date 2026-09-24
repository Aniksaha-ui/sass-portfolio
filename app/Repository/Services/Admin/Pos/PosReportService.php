<?php

namespace App\Repository\Services\Admin\Pos;

use Illuminate\Support\Facades\DB;

class PosReportService
{
    public function today(): array
    {
        $start = \Carbon\Carbon::today(config('pos.timezone'))->setTimezone('UTC');
        $end = $start->copy()->addDay();
        $sales = DB::table('pos_sales as ps')->join('orders as o', 'o.id', '=', 'ps.order_id')
            ->where('ps.created_at', '>=', $start)->where('ps.created_at', '<', $end)->orderByDesc('ps.id')
            ->get(['ps.order_id', 'ps.customer_name', 'ps.cost_total', 'ps.returned_amount', 'o.total_amount', 'o.payment_status', 'o.status', 'ps.created_at']);
        $transactions = DB::table('transactions as t')->join('pos_sales as ps', 'ps.order_id', '=', 't.order_id')
            ->where('t.created_at', '>=', $start)->where('t.created_at', '<', $end)->orderByDesc('t.id')->limit(200)
            ->get(['t.id', 't.order_id', 't.transaction_type', 't.amount', 't.payment_method', 't.status', 't.bank_ssl_id', 't.created_at', 'ps.customer_name']);
        $returns = DB::table('returns as r')->join('orders as o', 'o.id', '=', 'r.order_id')
            ->join('pos_sales as ps', 'ps.order_id', '=', 'o.id')
            ->join('order_items as oi', fn ($join) => $join->on('oi.order_id', '=', 'r.order_id')->on('oi.product_id', '=', 'r.product_id'))
            ->where('r.created_at', '>=', $start)->where('r.created_at', '<', $end)->whereIn('r.status', ['approved', 'refunded'])
            ->get(['r.product_id', 'r.quantity', 'oi.price', 'ps.subtotal', 'ps.discount_amount', 'o.cart_data']);
        $returnedValue = 0.0;
        $reversedCost = 0.0;
        foreach ($returns as $return) {
            $fraction = $return->subtotal > 0 ? max(0, 1 - (float) $return->discount_amount / (float) $return->subtotal) : 1;
            $returnedValue += round((float) $return->price * (int) $return->quantity * $fraction, 2);
            $cart = json_decode($return->cart_data, true) ?: [];
            foreach ($cart['products'] ?? [] as $item) {
                if ((int) $item['product_id'] === (int) $return->product_id) {
                    $reversedCost += (float) ($item['cost'] ?? 0) * (int) $return->quantity;
                    break;
                }
            }
        }
        $gross = round($sales->sum('total_amount'), 2);
        $returnsTotal = round($returnedValue, 2);
        $cashRefunds = round($transactions->where('transaction_type', 'refund')->where('status', 'success')->sum('amount'), 2);
        $cost = round($sales->sum('cost_total') - $reversedCost, 2);
        return [
            'orders' => $sales,
            'transactions' => $transactions,
            'count' => $sales->count(),
            'gross_sales' => $gross,
            'refunds' => $returnsTotal,
            'cash_refunds' => $cashRefunds,
            'net_sales' => round($gross - $returnsTotal, 2),
            'estimated_cost' => $cost,
            'estimated_profit' => round($gross - $returnsTotal - $cost, 2),
            'last_order' => $sales->first(),
        ];
    }
}
