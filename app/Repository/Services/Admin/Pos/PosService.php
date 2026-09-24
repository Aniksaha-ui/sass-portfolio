<?php

namespace App\Repository\Services\Admin\Pos;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosService
{
    public function catalog(string $search = '', ?int $categoryId = null, bool $featured = false): array
    {
        $stock = DB::table('inventory')->select('product_id', DB::raw('SUM(stock_quantity) as stock_quantity'))->groupBy('product_id');
        $query = DB::table('products as p')
            ->join('subcategories as sc', 'sc.id', '=', 'p.subcategory_id')
            ->join('categories as c', 'c.id', '=', 'sc.category_id')
            ->leftJoinSub($stock, 'stock', fn ($join) => $join->on('stock.product_id', '=', 'p.id'))
            ->where('p.is_active', 1)
            ->select('p.id', 'p.name', 'p.sku', 'p.price', 'c.id as category_id', 'c.name as category_name', 'sc.name as subcategory_name', DB::raw('COALESCE(stock.stock_quantity, 0) as stock_quantity'))
            ->selectSub(DB::table('product_images')->whereColumn('product_id', 'p.id')->orderByDesc('is_primary')->orderBy('id')->limit(1)->select('image_url'), 'image_url');
        if ($search !== '') $query->where(fn ($q) => $q->where('p.name', 'like', "%{$search}%")->orWhere('p.sku', 'like', "%{$search}%"));
        if ($categoryId) $query->where('c.id', $categoryId);
        if ($featured) $query->whereExists(fn ($q) => $q->selectRaw('1')->from('section_products')->whereColumn('section_products.product_id', 'p.id'));
        $products = $query->orderBy('p.name')->limit(120)->get();
        $discounts = DB::table('product_discounts')->whereIn('product_id', $products->pluck('id'))->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', \Carbon\Carbon::today(config('pos.timezone'))))->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', \Carbon\Carbon::today(config('pos.timezone'))))->orderByDesc('id')->get()->keyBy('product_id');
        foreach ($products as $product) {
            $product->base_price = (float) $product->price;
            $product->price = $this->effectivePrice($product, $discounts->get($product->id));
        }
        return [
            'products' => $products,
            'timezone' => config('pos.timezone'),
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'coupons' => DB::table('coupons')->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', \Carbon\Carbon::today(config('pos.timezone'))))->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', \Carbon\Carbon::today(config('pos.timezone'))))->get(['code', 'discount_type', 'discount_value', 'max_usage']),
            'customers' => DB::table('users')->where('role', 'customer')->where('email', '!=', 'pos-walkin@ecovani.local')->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'phone']),
        ];
    }

    public function checkout(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $customer = $this->customer($data);
            $items = [];
            $subtotal = 0.0;
            $costTotal = 0.0;
            foreach ($data['items'] as $requested) {
                $product = DB::table('products')->where('id', $requested['product_id'])->where('is_active', 1)->lockForUpdate()->first();
                if (! $product) throw new \InvalidArgumentException('A selected product is unavailable.');
                $inventory = DB::table('inventory')->where('product_id', $product->id)->orderBy('id')->lockForUpdate()->get();
                if ($inventory->sum('stock_quantity') < $requested['quantity']) throw new \InvalidArgumentException("Insufficient stock for {$product->name}.");
                $discount = DB::table('product_discounts')->where('product_id', $product->id)->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', \Carbon\Carbon::today(config('pos.timezone'))))->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', \Carbon\Carbon::today(config('pos.timezone'))))->orderByDesc('id')->first();
                $unitPrice = $this->effectivePrice($product, $discount);
                $unitCost = $this->unitCost($product->id);
                $items[] = ['product' => $product, 'quantity' => (int) $requested['quantity'], 'inventory' => $inventory, 'price' => $unitPrice, 'cost' => $unitCost];
                $subtotal += $unitPrice * $requested['quantity'];
                $costTotal += $unitCost * $requested['quantity'];
            }
            $manualDiscount = round((float) ($data['discount_amount'] ?? 0), 2);
            $coupon = $this->coupon($data['coupon_code'] ?? null, $subtotal);
            $couponDiscount = $coupon ? min($subtotal - $manualDiscount, $coupon->discount_type === 'percentage' ? round($subtotal * (float) $coupon->discount_value / 100, 2) : (float) $coupon->discount_value) : 0;
            $discountAmount = round($manualDiscount + max(0, $couponDiscount), 2);
            if ($discountAmount > $subtotal) throw new \InvalidArgumentException('Discount cannot exceed the subtotal.');
            $tax = round((float) ($data['tax_amount'] ?? 0), 2);
            $shipping = round((float) ($data['shipping_amount'] ?? 0), 2);
            $rounding = round((float) ($data['rounding_amount'] ?? 0), 2);
            $total = round($subtotal - $discountAmount + $tax + $shipping + $rounding, 2);
            if ($total <= 0) throw new \InvalidArgumentException('The sale total must be greater than zero.');
            $paid = round(collect($data['payments'])->where('method', '!=', 'pay_later')->sum('amount'), 2);
            $due = round(collect($data['payments'])->where('method', 'pay_later')->sum('amount'), 2);
            if (abs(($paid + $due) - $total) > 0.009) throw new \InvalidArgumentException('Split payments must add up to the order total.');
            $reference = 'POS-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5));
            $customerName = ! empty($data['customer_id']) ? $customer->name : (trim($data['walk_in_name'] ?? '') ?: 'Walk-in Customer');
            $customerPhone = ! empty($data['customer_id']) ? ($customer->phone ?? null) : ($data['walk_in_phone'] ?? null);
            $cartData = [
                'products' => array_map(fn ($item) => ['product_id' => $item['product']->id, 'quantity' => $item['quantity'], 'price' => $item['price'], 'cost' => $item['cost']], $items),
                'userInformation' => ['name' => $customerName, 'email' => $customer->email, 'phone' => $customerPhone, 'shipmentType' => 'pos'],
                'pos' => ['source' => 'pos', 'cashier_id' => Auth::id(), 'reference' => $reference, 'subtotal' => $subtotal, 'discount' => $discountAmount, 'coupon_code' => $coupon->code ?? null, 'tax' => $tax, 'shipping' => $shipping, 'rounding' => $rounding, 'note' => $data['note'] ?? null],
            ];
            $orderId = DB::table('orders')->insertGetId(['user_id' => $customer->id, 'total_amount' => $total, 'status' => 'delivered', 'payment_status' => $due > 0 ? 'unpaid' : 'paid', 'tran_id' => $reference, 'cart_data' => json_encode($cartData), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_sales')->insert(['order_id' => $orderId, 'cashier_id' => Auth::id(), 'customer_name' => $customerName, 'customer_phone' => $customerPhone, 'subtotal' => $subtotal, 'cost_total' => $costTotal, 'discount_amount' => $discountAmount, 'tax_amount' => $tax, 'shipping_amount' => $shipping, 'rounding_amount' => $rounding, 'coupon_code' => $coupon->code ?? null, 'created_at' => now(), 'updated_at' => now()]);
            if ($coupon) DB::table('pos_coupon_redemptions')->insert(['coupon_id' => $coupon->id, 'order_id' => $orderId, 'created_at' => now()]);
            foreach ($items as $item) {
                DB::table('order_items')->insert(['order_id' => $orderId, 'product_id' => $item['product']->id, 'quantity' => $item['quantity'], 'price' => $item['price']]);
                $remaining = $item['quantity'];
                foreach ($item['inventory'] as $stock) {
                    if (! $remaining) break;
                    $take = min($remaining, (int) $stock->stock_quantity);
                    if ($take < 1) continue;
                    DB::table('inventory')->where('id', $stock->id)->decrement('stock_quantity', $take);
                    DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => '_inventory_deducted', 'location' => $stock->id.':'.$take, 'updated_at' => now()]);
                    $remaining -= $take;
                }
            }
            foreach ($data['payments'] as $index => $payment) {
                $method = $payment['method'];
                $amount = round((float) $payment['amount'], 2);
                $paymentReference = trim($payment['reference'] ?? '') ?: $reference.'-'.($index + 1);
                DB::table('pos_payments')->insert(['order_id' => $orderId, 'method' => $method, 'amount' => $amount, 'reference' => $paymentReference, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('transactions')->insert(['order_id' => $orderId, 'transaction_type' => 'payment', 'amount' => $amount, 'payment_method' => $method, 'bank_ssl_id' => $paymentReference, 'tran_date' => now(), 'currency' => 'BDT', 'status' => $method === 'pay_later' ? 'pending' : 'success', 'created_at' => now()]);
                if ($method !== 'pay_later') $this->accountMovement($customer->id, $method, $amount, $reference.'-'.($index + 1), 'c');
            }
            DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => 'delivered', 'location' => 'POS sale', 'updated_at' => now()]);
            return $this->order($orderId);
        });
    }

    public function holds(): array
    {
        return DB::table('pos_holds')->where('cashier_id', Auth::id())->orderByDesc('id')->get()->map(function ($hold) {
            $hold->cart_data = json_decode($hold->cart_data, true);
            return $hold;
        })->all();
    }

    public function hold(array $data): array
    {
        $reference = 'HOLD-'.now()->format('ymdHis').'-'.Str::upper(Str::random(4));
        $id = DB::table('pos_holds')->insertGetId(['cashier_id' => Auth::id(), 'reference' => $reference, 'cart_data' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
        return ['id' => $id, 'reference' => $reference];
    }

    public function releaseHold(int $id): bool
    {
        return (bool) DB::table('pos_holds')->where('id', $id)->where('cashier_id', Auth::id())->delete();
    }

    private function customer(array $data)
    {
        if (! empty($data['customer_id'])) {
            $customer = DB::table('users')->where('id', $data['customer_id'])->first();
            if (! $customer || $customer->role !== 'customer') throw new \InvalidArgumentException('Customer not found.');
            return $customer;
        }

        $email = Str::lower(trim($data['walk_in_email'] ?? ''));
        $phone = trim($data['walk_in_phone'] ?? '');
        if ($email === '' && $phone === '') {
            $sharedEmail = 'pos-walkin@ecovani.local';
            $customer = DB::table('users')->where('email', $sharedEmail)->first();
            if (! $customer) {
                $id = DB::table('users')->insertGetId(['name' => 'Walk-in Customer', 'email' => $sharedEmail, 'role' => 'customer', 'password' => bcrypt(Str::random(40)), 'created_at' => now(), 'updated_at' => now()]);
                $customer = DB::table('users')->where('id', $id)->first();
            }
            return $customer;
        }

        $byEmail = $email !== '' ? DB::table('users')->where('email', $email)->lockForUpdate()->first() : null;
        $byPhone = $phone !== '' ? DB::table('users')->where('phone', $phone)->lockForUpdate()->get() : collect();
        if ($byPhone->count() > 1) throw new \InvalidArgumentException('This phone number matches multiple users. Select the customer explicitly.');
        $phoneCustomer = $byPhone->first();
        if ($byEmail && $phoneCustomer && $byEmail->id !== $phoneCustomer->id) {
            throw new \InvalidArgumentException('Email and phone belong to different accounts.');
        }
        $customer = $byEmail ?: $phoneCustomer;
        if ($customer) {
            if ($customer->role !== 'customer') throw new \InvalidArgumentException('These contact details belong to a non-customer account.');
            if ($email !== '' && $customer->email !== $email) {
                if (! Str::startsWith($customer->email, 'pos-phone-') || ! Str::endsWith($customer->email, '@ecovani.local')) {
                    throw new \InvalidArgumentException('This phone number belongs to an account with a different email. Select the customer explicitly.');
                }
                DB::table('users')->where('id', $customer->id)->update(['email' => $email, 'updated_at' => now()]);
                $customer->email = $email;
            }
            if ($phone !== '' && ! $customer->phone) {
                DB::table('users')->where('id', $customer->id)->update(['phone' => $phone, 'updated_at' => now()]);
                $customer->phone = $phone;
            }
            return $customer;
        }

        $id = DB::table('users')->insertGetId([
            'name' => trim($data['walk_in_name'] ?? '') ?: 'Walk-in Customer',
            'email' => $email ?: 'pos-phone-'.Str::uuid().'@ecovani.local',
            'phone' => $phone ?: null,
            'role' => 'customer',
            'password' => bcrypt(Str::random(40)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return DB::table('users')->where('id', $id)->first();
    }

    private function coupon(?string $code, float $subtotal)
    {
        if (! $code) return null;
        $coupon = DB::table('coupons')->where('code', trim($code))->lockForUpdate()->first();
        if (! $coupon || ($coupon->start_date && $coupon->start_date > \Carbon\Carbon::today(config('pos.timezone'))->toDateString()) || ($coupon->end_date && $coupon->end_date < \Carbon\Carbon::today(config('pos.timezone'))->toDateString())) throw new \InvalidArgumentException('Coupon is invalid or expired.');
        if ($coupon->max_usage > 0 && DB::table('pos_coupon_redemptions')->where('coupon_id', $coupon->id)->count() >= $coupon->max_usage) throw new \InvalidArgumentException('Coupon usage limit has been reached.');
        if ($subtotal <= 0) throw new \InvalidArgumentException('Coupon requires a positive subtotal.');
        return $coupon;
    }

    private function effectivePrice($product, $discount): float
    {
        $price = (float) $product->price;
        if (! $discount) return round($price, 2);
        $reduction = $discount->discount_type === 'percentage' ? $price * (float) $discount->discount_value / 100 : (float) $discount->discount_value;
        return round(max(0, $price - $reduction), 2);
    }

    private function unitCost(int $productId): float
    {
        $cost = DB::table('stock_receipts as sr')->join('requisition_products as rp', 'rp.id', '=', 'sr.requisition_product_id')->where('sr.product_id', $productId)->selectRaw('SUM(sr.quantity_received * rp.unit_cost) / NULLIF(SUM(sr.quantity_received), 0) as unit_cost')->value('unit_cost');
        return round((float) ($cost ?? 0), 2);
    }

    private function accountMovement(int $customerId, string $method, float $amount, string $reference, string $direction): void
    {
        $account = DB::table('company_accounts')->where('type', $method)->lockForUpdate()->first();
        if (! $account) {
            $id = DB::table('company_accounts')->insertGetId(['account_name' => ucfirst(str_replace('_', ' ', $method)).' POS', 'account_number' => $method, 'amount' => 0, 'type' => $method, 'created_at' => now(), 'updated_at' => now()]);
            $account = DB::table('company_accounts')->where('id', $id)->first();
        }
        $change = $direction === 'd' ? -$amount : $amount;
        DB::table('company_accounts')->where('id', $account->id)->increment('amount', $change, ['updated_at' => now()]);
        DB::table('account_history')->insert(['user_id' => $customerId, 'user_account_type' => substr($method, 0, 20), 'user_account_no' => substr($reference, 0, 20), 'getaway' => substr($method, 0, 20), 'amount' => $amount, 'com_account_no' => substr($account->account_number, 0, 20), 'transaction_reference' => substr($reference, 0, 20), 'transaction_type' => $direction, 'purpose' => $direction === 'd' ? 'order_refund' : 'order_payment', 'tran_date' => now(), 'ip_address' => null]);
    }

    public function order(int $id): ?array
    {
        $order = DB::table('orders as o')->join('pos_sales as ps', 'ps.order_id', '=', 'o.id')->where('o.id', $id)->select('o.*', 'ps.customer_name', 'ps.customer_phone', 'ps.subtotal', 'ps.cost_total', 'ps.discount_amount', 'ps.tax_amount', 'ps.shipping_amount', 'ps.rounding_amount', 'ps.returned_amount', 'ps.coupon_code')->first();
        if (! $order) return null;
        $order->items = DB::table('order_items as oi')->join('products as p', 'p.id', '=', 'oi.product_id')->where('oi.order_id', $id)->get(['oi.id', 'oi.product_id', 'oi.quantity', 'oi.price', 'p.name', 'p.sku']);
        $order->payments = DB::table('pos_payments')->where('order_id', $id)->orderBy('id')->get();
        $order->returns = DB::table('returns')->where('order_id', $id)->get();
        return (array) $order;
    }

    public function orders(string $search = ''): array
    {
        return DB::table('pos_sales as ps')->join('orders as o', 'o.id', '=', 'ps.order_id')->where(fn ($q) => $q->where('o.id', 'like', "%{$search}%")->orWhere('ps.customer_name', 'like', "%{$search}%"))->orderByDesc('ps.id')->limit(100)->get(['ps.order_id', 'ps.customer_name', 'o.total_amount', 'o.payment_status', 'o.created_at'])->all();
    }

    public function collectPayment(int $orderId, array $data): array
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            $sale = DB::table('pos_sales')->where('order_id', $orderId)->first();
            if (! $order || ! $sale) throw new \InvalidArgumentException('POS order not found.');
            $received = (float) DB::table('pos_payments')->where('order_id', $orderId)->where('kind', 'payment')->where('method', '!=', 'pay_later')->sum('amount');
            $remaining = round((float) $order->total_amount - (float) $sale->returned_amount - $received, 2);
            $amount = round((float) $data['amount'], 2);
            if ($remaining <= 0 || $amount > $remaining + 0.009) throw new \InvalidArgumentException('Payment exceeds the remaining balance.');
            $reference = trim($data['reference'] ?? '') ?: 'POS-PAY-'.Str::upper(Str::random(10));
            DB::table('pos_payments')->insert(['order_id' => $orderId, 'method' => $data['method'], 'amount' => $amount, 'reference' => $reference, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('transactions')->insert(['order_id' => $orderId, 'transaction_type' => 'payment', 'amount' => $amount, 'payment_method' => $data['method'], 'bank_ssl_id' => $reference, 'tran_date' => now(), 'currency' => 'BDT', 'status' => 'success', 'created_at' => now()]);
            $this->accountMovement($order->user_id, $data['method'], $amount, $reference, 'c');
            if ($amount >= $remaining - 0.009) DB::table('orders')->where('id', $orderId)->update(['payment_status' => 'paid', 'updated_at' => now()]);
            return $this->order($orderId);
        });
    }

    public function returnItems(int $orderId, array $data): array
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            $sale = DB::table('pos_sales')->where('order_id', $orderId)->lockForUpdate()->first();
            if (! $order || ! $sale || $order->status === 'cancelled') throw new \InvalidArgumentException('POS order not found or cannot be returned.');
            $received = (float) DB::table('pos_payments')->where('order_id', $orderId)->where('kind', 'payment')->where('method', '!=', 'pay_later')->sum('amount');
            $refunded = (float) DB::table('pos_payments')->where('order_id', $orderId)->where('kind', 'refund')->sum('amount');
            $outstanding = max(0, round((float) $order->total_amount - (float) $sale->returned_amount - ($received - $refunded), 2));
            $returnValue = 0.0;
            $cashRefund = 0.0;
            $returnedCost = 0.0;
            $cart = json_decode($order->cart_data, true) ?: [];
            foreach ($data['items'] as $requested) {
                $item = DB::table('order_items')->where('id', $requested['order_item_id'])->where('order_id', $orderId)->first();
                if (! $item) throw new \InvalidArgumentException('A returned item does not belong to this order.');
                $priorReturns = DB::table('returns')->where('order_id', $orderId)->where('product_id', $item->product_id)->whereIn('status', ['approved', 'refunded'])->get(['quantity']);
                $already = $priorReturns->sum(fn ($row) => $row->quantity ?? $item->quantity);
                if ($already + $requested['quantity'] > $item->quantity) throw new \InvalidArgumentException('Return quantity exceeds the sold quantity.');
                $fraction = $sale->subtotal > 0 ? max(0, 1 - (float) $sale->discount_amount / (float) $sale->subtotal) : 1;
                $lineValue = round((float) $item->price * $requested['quantity'] * $fraction, 2);
                $dueCredit = min($outstanding, $lineValue);
                $outstanding = round($outstanding - $dueCredit, 2);
                $lineRefund = round($lineValue - $dueCredit, 2);
                $returnValue += $lineValue;
                $cashRefund += $lineRefund;
                $original = collect($cart['products'] ?? [])->firstWhere('product_id', $item->product_id);
                $returnedCost += (float) ($original['cost'] ?? 0) * $requested['quantity'];
                $returnId = DB::table('returns')->insertGetId(['order_id' => $orderId, 'product_id' => $item->product_id, 'quantity' => $requested['quantity'], 'reason' => $data['reason'], 'status' => $lineRefund > 0 ? 'refunded' : 'approved', 'refund_amount' => $lineRefund ?: null, 'created_at' => now(), 'updated_at' => now()]);
                $stock = DB::table('inventory')->where('product_id', $item->product_id)->orderBy('id')->lockForUpdate()->first();
                if (! $stock) throw new \InvalidArgumentException('Inventory location is missing for the returned item.');
                DB::table('inventory')->where('id', $stock->id)->increment('stock_quantity', $requested['quantity']);
                DB::table('inventory_adjustments')->insert(['inventory_id' => $stock->id, 'product_id' => $item->product_id, 'warehouse_location' => $stock->warehouse_location, 'previous_quantity' => $stock->stock_quantity, 'new_quantity' => $stock->stock_quantity + $requested['quantity'], 'adjustment_quantity' => $requested['quantity'], 'reason' => 'POS return #'.$returnId, 'adjusted_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
                if ($lineRefund > 0) DB::table('refunds')->insert(['return_id' => $returnId, 'order_id' => $orderId, 'user_id' => $order->user_id, 'amount' => $lineRefund, 'status' => 'processed', 'refund_reference' => 'POS-R-'.Str::upper(Str::random(14)), 'processed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $returnValue = round($returnValue, 2);
            $cashRefund = round($cashRefund, 2);
            if ($cashRefund > $received - $refunded + 0.009) throw new \InvalidArgumentException('Refund exceeds the amount already paid.');
            DB::table('pos_sales')->where('order_id', $orderId)->increment('returned_amount', $returnValue, ['returned_cost' => (float) $sale->returned_cost + $returnedCost, 'updated_at' => now()]);
            if ($cashRefund > 0) {
                $reference = trim($data['reference'] ?? '') ?: 'POS-REF-'.Str::upper(Str::random(10));
                DB::table('pos_payments')->insert(['order_id' => $orderId, 'kind' => 'refund', 'method' => $data['method'], 'amount' => $cashRefund, 'reference' => $reference, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('transactions')->insert(['order_id' => $orderId, 'transaction_type' => 'refund', 'amount' => $cashRefund, 'payment_method' => $data['method'], 'bank_ssl_id' => $reference, 'tran_date' => now(), 'currency' => 'BDT', 'status' => 'success', 'created_at' => now()]);
                $this->accountMovement($order->user_id, $data['method'], $cashRefund, $reference, 'd');
            }
            $remainingSale = round((float) $order->total_amount - (float) $sale->returned_amount - $returnValue, 2);
            if ($outstanding <= 0) DB::table('orders')->where('id', $orderId)->update(['payment_status' => $remainingSale <= 0 ? 'refunded' : 'paid', 'updated_at' => now()]);
            DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => 'processing', 'location' => 'POS return: '.$data['reason'], 'updated_at' => now()]);
            return $this->order($orderId);
        });
    }
}
