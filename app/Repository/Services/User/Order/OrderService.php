<?php

namespace App\Repository\Services\User\Order;

use App\Constants\ResponseConstants;
use App\Repository\Services\Common\CommonService;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OrderService
{
    public function cancelMyOrder(int $orderId): array
    {
        return DB::transaction(function () use ($orderId) {
            $order = DB::table('orders')->where('id', $orderId)->where('user_id', Auth::id())->lockForUpdate()->first();
            if (! $order) {
                return ['status' => false, 'message' => 'Order not found.', 'data' => []];
            }
            if (in_array($order->status, ['shipped', 'delivered', 'cancelled'], true)) {
                return ['status' => false, 'message' => 'This order can no longer be cancelled.', 'data' => []];
            }
            $deductions = DB::table('order_tracking')->where('order_id', $orderId)->where('status', '_inventory_deducted')->get();
            if ($deductions->isNotEmpty() && ! DB::table('order_tracking')->where('order_id', $orderId)->where('status', '_inventory_restored')->exists()) {
                foreach ($deductions as $movement) {
                    [$inventoryId, $quantity] = array_map('intval', explode(':', (string) $movement->location));
                    DB::table('inventory')->where('id', $inventoryId)->increment('stock_quantity', $quantity);
                }
                DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => '_inventory_restored', 'location' => null, 'updated_at' => now()]);
            }
            DB::table('orders')->where('id', $orderId)->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('order_tracking')->insert(['order_id' => $orderId, 'status' => 'cancelled', 'location' => 'Cancelled by customer', 'updated_at' => now()]);

            $isPaid = $order->payment_status === 'paid';
            foreach (DB::table('order_items')->where('order_id', $orderId)->get(['product_id', 'quantity', 'price']) as $item) {
                $return = DB::table('returns')->where('order_id', $orderId)->where('product_id', $item->product_id)->first();
                if ($return) {
                    continue;
                }
                $amount = round((float) $item->quantity * (float) $item->price, 2);
                $returnId = DB::table('returns')->insertGetId(['order_id' => $orderId, 'product_id' => $item->product_id, 'reason' => 'Order cancelled by customer', 'status' => $isPaid ? 'refunded' : 'approved', 'refund_amount' => $isPaid ? $amount : null, 'created_at' => now(), 'updated_at' => now()]);
                if ($isPaid) {
                    DB::table('refunds')->insert(['return_id' => $returnId, 'order_id' => $orderId, 'user_id' => $order->user_id, 'amount' => $amount, 'status' => 'processed', 'refund_reference' => 'CANCEL-'.$orderId.'-'.$returnId.'-'.now()->format('His'), 'processed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            return ['status' => true, 'message' => 'Order cancelled successfully.', 'data' => ['return_records_created' => true, 'refund_processed' => $isPaid]];
        });
    }

    private const DEFAULT_COMPANY_ACCOUNT_TYPE = 'account';

    private const ACCOUNT_HISTORY_PURPOSE = 'order_payment';

    private $commonService;

    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function order($data)
    {
        try {
            if (($data['payment_method'] ?? '') === 'ssl') {
                return $this->initSSLTransaction($data);
            }

            return ['status' => 'failed', 'message' => 'Invalid payment method'];
        } catch (InvalidArgumentException $ex) {
            return [
                'status' => ResponseConstants::FAILED,
                'message' => $ex->getMessage(),
                'data' => [],
            ];
        } catch (Exception $ex) {
            Log::error('OrderService : order function error: '.$ex->getMessage());

            return $this->commonService->internalServerErrorResponse(
                ResponseConstants::FAILED,
                'Internal Server Error. Please Contact Admin',
                []
            );
        }
    }

    public function myOrders()
    {
        try {
            $orders = DB::table('orders')
                ->leftJoin(DB::raw('(SELECT order_id, SUM(quantity) AS item_count FROM order_items GROUP BY order_id) AS order_items_summary'), 'orders.id', '=', 'order_items_summary.order_id')
                ->where('orders.user_id', Auth::id())
                ->orderByDesc('orders.id')
                ->select(
                    'orders.*',
                    DB::raw('COALESCE(order_items_summary.item_count, 0) AS item_count')
                )
                ->get();

            $data = $orders->map(function ($order) {
                return $this->transformOrderSummary($order);
            })->values();

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => $data->count() > 0 ? 'Orders fetched successfully' : 'No orders found',
                'data' => $data,
            ];
        } catch (Exception $ex) {
            Log::error('OrderService : myOrders function error: '.$ex->getMessage());

            return $this->commonService->internalServerErrorResponse(
                ResponseConstants::FAILED,
                'Internal Server Error. Please Contact Admin',
                []
            );
        }
    }

    public function orderDetails($id)
    {
        try {
            $order = DB::table('orders')
                ->where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (! $order) {
                return [
                    'status' => ResponseConstants::FAILED,
                    'message' => 'Order not found',
                    'data' => [],
                ];
            }

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => 'Order details fetched successfully',
                'data' => $this->transformOrderDetails($order),
            ];
        } catch (Exception $ex) {
            Log::error('OrderService : orderDetails function error: '.$ex->getMessage());

            return $this->commonService->internalServerErrorResponse(
                ResponseConstants::FAILED,
                'Internal Server Error. Please Contact Admin',
                []
            );
        }
    }

    public function initSSLTransaction($paymentInformation)
    {
        DB::beginTransaction();

        try {
            $tran_id = uniqid('SSL_');
            $selectedAddress = $this->resolveCheckoutAddress($paymentInformation);
            $preparedCartData = $this->prepareOrderCartData($paymentInformation, $selectedAddress);

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => Auth::id(),
                'address_id' => $selectedAddress['id'] ?? null,
                'total_amount' => $paymentInformation['totalAmount'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'cart_data' => json_encode($preparedCartData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($preparedCartData['products'] as $product) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'price' => $product['price'],
                ]);
            }

            DB::table('transactions')->insert([
                'order_id' => $orderId,
                'transaction_type' => 'payment',
                'amount' => $paymentInformation['totalAmount'],
                'payment_method' => 'card',
                'status' => 'failed',
                'created_at' => now(),
            ]);

            $post_data = [
                'store_id' => env('STORE_ID'),
                'store_passwd' => env('STORE_PASSWORD'),
                'total_amount' => $paymentInformation['totalAmount'],
                'currency' => 'BDT',
                'tran_id' => $tran_id,
                'success_url' => route('payment.success'),
                'fail_url' => route('payment.fail'),
                'cancel_url' => route('payment.cancel'),
                'emi_option' => 0,
                'cus_name' => $preparedCartData['userInformation']['name'] ?? Auth::user()->name,
                'cus_email' => Auth::user()->email,
                'cus_add1' => $preparedCartData['userInformation']['address'] ?? 'N/A',
                'cus_city' => $preparedCartData['userInformation']['city'] ?? 'N/A',
                'cus_state' => $preparedCartData['userInformation']['state'] ?? 'N/A',
                'cus_postcode' => $preparedCartData['userInformation']['zip'] ?? '0000',
                'cus_country' => $preparedCartData['userInformation']['country'] ?? 'Bangladesh',
                'cus_phone' => $preparedCartData['userInformation']['phone'] ?? 'N/A',
                'shipping_method' => 'NO',
                'product_name' => 'Order #'.$orderId,
                'product_category' => 'Ecommerce',
                'product_profile' => 'general',
                'value_a' => $orderId,
            ];

            $url = env('IS_SANDBOX')
                ? 'https://uat-securepay.sslcommerz.com/gwprocess/v4/api.php'
                : 'https://uat-securepay.sslcommerz.com/gwprocess/v4/api.php';

            $response = Http::asForm()->post($url, $post_data);
            $sslResponse = $response->json();

            if (! empty($sslResponse['GatewayPageURL'])) {
                DB::commit();

                return [
                    'status' => 'success',
                    'url' => $sslResponse['GatewayPageURL'],
                    'tran_id' => $tran_id,
                    'message' => 'Redirect to SSLCommerz gateway',
                ];
            }

            DB::rollBack();

            return ['status' => 'failed', 'message' => 'SSLCommerz gateway initialization failed'];
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('PaymentService : initSSLTransaction() => '.$ex->getMessage());

            return $this->commonService->internalServerErrorResponse(
                ResponseConstants::FAILED,
                'Internal Server Error. Please Contact Admin',
                []
            );
        }
    }

    public function success(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info('Payment Success Callback: '.json_encode($request->all()));

            $verifyURL = env('IS_SANDBOX')
                ? 'https://uat-securepay.sslcommerz.com/validator/api/validationserverAPI.php'
                : 'https://uat-securepay.sslcommerz.com/validator/api/validationserverAPI.php';

            $verifyResponse = Http::get($verifyURL, [
                'val_id' => $request->val_id,
                'store_id' => env('STORE_ID'),
                'store_passwd' => env('STORE_PASSWORD'),
                'v' => 1,
                'format' => 'json',
            ]);

            $verifyData = $verifyResponse->json();

            if (($verifyData['status'] ?? '') === 'VALID' || ($verifyData['status'] ?? '') === 'VALIDATED') {
                $paymentChannel = $this->resolvePaymentChannel($verifyData);
                $order = DB::table('orders')->where('id', $verifyData['value_a'])->first();

                if (! $order) {
                    DB::rollBack();

                    return response()->json(['status' => 'failed', 'message' => 'Order not found']);
                }

                DB::table('orders')->where('id', $verifyData['value_a'])->update([
                    'status' => 'processing',
                    'payment_status' => 'paid',
                    'tran_id' => $request->tran_id,
                    'updated_at' => now(),
                ]);

                DB::table('transactions')->where('order_id', $verifyData['value_a'])->update([
                    'status' => 'success',
                    'bank_ssl_id' => $verifyData['bank_tran_id'] ?? null,
                    'tran_date' => $verifyData['tran_date'] ?? null,
                    'currency' => $verifyData['currency'] ?? null,
                    'store_amount' => $verifyData['store_amount'] ?? null,
                    'card_no' => $verifyData['card_no'] ?? null,
                    'risk_title' => $verifyData['risk_title'] ?? null,
                    'settlement_status' => $verifyData['settlement_status'] ?? null,
                    'bank_approval_id' => $verifyData['bank_approval_id'] ?? null,
                    'cus_phone' => $verifyData['cus_phone'] ?? null,
                    'discount_percentage' => $verifyData['discount_percentage'] ?? null,
                    'discount_remarks' => $verifyData['discount_remarks'] ?? null,
                    'payment_method' => $paymentChannel['type'],
                ]);

                $this->recordCompanyAccountSettlement($order, $request, $verifyData, $paymentChannel);

                DB::table('order_tracking')->insert([
                    'order_id' => $verifyData['value_a'],
                    'status' => 'pending',
                    'location' => $request->card_issuer_country_code ?? 'N/A',
                ]);

                DB::table('cart')->where('user_id', $order->user_id)->delete();

                DB::commit();

                return redirect($this->buildFrontendPaymentUrl('success', $request->tran_id));
            }

            DB::rollBack();

            return response()->json(['status' => 'failed', 'message' => 'Payment validation failed']);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('PaymentService : paymentSuccess() => '.$ex->getMessage());

            return response()->json(['status' => 'failed', 'message' => $ex->getMessage()]);
        }
    }

    public function fail(Request $request)
    {
        return $this->handlePaymentOutcome($request, 'cancelled', 'failed', 'unpaid');
    }

    public function cancel(Request $request)
    {
        return $this->handlePaymentOutcome($request, 'cancelled', 'cancel', 'unpaid');
    }

    private function recordCompanyAccountSettlement($order, Request $request, array $verifyData, array $paymentChannel): void
    {
        $transactionReference = $this->resolveTransactionReference($request, $verifyData);

        $historyAlreadyExists = DB::table('account_history')
            ->where('transaction_reference', $transactionReference)
            ->where('purpose', self::ACCOUNT_HISTORY_PURPOSE)
            ->exists();

        if ($historyAlreadyExists) {
            return;
        }

        $settledAmount = $this->resolveSettledAmount($order, $verifyData);
        $historyAmount = $settledAmount;

        $companyAccount = DB::table('company_accounts')
            ->whereRaw('LOWER(type) = ?', [$paymentChannel['type']])
            ->first();

        if (! $companyAccount) {
            $companyAccountId = DB::table('company_accounts')->insertGetId([
                'account_name' => $paymentChannel['name'],
                'account_number' => $paymentChannel['account_number'],
                'amount' => 0,
                'type' => $paymentChannel['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $companyAccount = DB::table('company_accounts')->where('id', $companyAccountId)->first();
        }

        DB::table('company_accounts')
            ->where('id', $companyAccount->id)
            ->update([
                'amount' => (float) $companyAccount->amount + $settledAmount,
                'updated_at' => now(),
            ]);

        DB::table('account_history')->insert([
            'user_id' => $order->user_id,
            'user_account_type' => $this->limitValue($verifyData['card_type'] ?? $verifyData['card_brand'] ?? $verifyData['card_issuer'] ?? null, 20),
            'user_account_no' => $this->limitValue($verifyData['card_no'] ?? $request->card_no ?? null, 20),
            'getaway' => $this->limitValue($paymentChannel['type'], 20),
            'amount' => $historyAmount,
            'com_account_no' => $this->limitValue($companyAccount->account_number, 20),
            'transaction_reference' => $transactionReference,
            'transaction_type' => 'c',
            'purpose' => self::ACCOUNT_HISTORY_PURPOSE,
            'tran_date' => $this->normalizeTransactionDate($verifyData['tran_date'] ?? null),
            'ip_address' => $this->limitValue($request->ip(), 20),
        ]);
    }

    private function resolvePaymentChannel(array $verifyData): array
    {
        $brandSource = strtolower(implode(' ', array_filter([
            $verifyData['card_brand'] ?? null,
            $verifyData['card_type'] ?? null,
            $verifyData['card_issuer'] ?? null,
            $verifyData['card_sub_brand'] ?? null,
        ])));

        if (strpos($brandSource, 'bkash') !== false) {
            return [
                'type' => 'bkash',
                'name' => 'bKash',
                'account_number' => 'bkash',
            ];
        }

        if (strpos($brandSource, 'nagad') !== false) {
            return [
                'type' => 'nagad',
                'name' => 'Nagad',
                'account_number' => 'nagad',
            ];
        }

        return [
            'type' => self::DEFAULT_COMPANY_ACCOUNT_TYPE,
            'name' => 'Account',
            'account_number' => self::DEFAULT_COMPANY_ACCOUNT_TYPE,
        ];
    }

    private function resolveTransactionReference(Request $request, array $verifyData): string
    {
        $reference = $verifyData['bank_tran_id']
            ?? $request->bank_tran_id
            ?? $request->tran_id
            ?? $verifyData['tran_id']
            ?? '';

        return substr((string) $reference, 0, 20);
    }

    private function handlePaymentOutcome(Request $request, string $orderStatus, string $frontendStatus, string $paymentStatus)
    {
        DB::beginTransaction();

        try {
            Log::info(sprintf('Payment %s Callback: %s', ucfirst($frontendStatus), json_encode($request->all())));

            $order = $this->resolveOrderFromCallback($request);

            if (! $order) {
                DB::rollBack();

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order not found',
                ], 404);
            }

            DB::table('orders')->where('id', $order->id)->update([
                'status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'updated_at' => now(),
            ]);

            DB::table('transactions')->where('order_id', $order->id)->update([
                'status' => 'failed',
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => $orderStatus,
                'location' => $request->card_issuer_country_code ?? 'N/A',
            ]);

            DB::commit();

            return redirect($this->buildFrontendPaymentUrl($frontendStatus, $request->tran_id));
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error(sprintf('PaymentService : payment%s() => %s', ucfirst($frontendStatus), $ex->getMessage()));

            return response()->json([
                'status' => 'failed',
                'message' => $ex->getMessage(),
            ]);
        }
    }

    private function resolveOrderFromCallback(Request $request)
    {
        $orderId = $request->value_a;

        if (! empty($orderId)) {
            return DB::table('orders')->where('id', $orderId)->first();
        }

        if (! empty($request->tran_id)) {
            return DB::table('orders')->where('tran_id', $request->tran_id)->first();
        }

        return null;
    }

    private function buildFrontendPaymentUrl(string $status, ?string $transactionId): string
    {
        $baseUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5174'), '/');
        $query = http_build_query(array_filter([
            'tran_id' => $transactionId,
            'status' => $status,
        ]));

        return $baseUrl.'/payment/'.$status.($query !== '' ? '?'.$query : '');
    }

    private function prepareOrderCartData(array $paymentInformation, ?array $selectedAddress = null): array
    {
        $products = array_map(function ($product) {
            return [
                'product_id' => (int) ($product['product_id'] ?? 0),
                'quantity' => (int) ($product['quantity'] ?? 1),
                'price' => (float) ($product['price'] ?? 0),
            ];
        }, $paymentInformation['products'] ?? []);

        $userInformation = [
            'name' => $paymentInformation['userInformation']['name'] ?? Auth::user()->name,
            'address' => $paymentInformation['userInformation']['address'] ?? '',
            'apartment' => $paymentInformation['userInformation']['apartment'] ?? '',
            'city' => $paymentInformation['userInformation']['city'] ?? '',
            'state' => $paymentInformation['userInformation']['state'] ?? '',
            'zip' => $paymentInformation['userInformation']['zip'] ?? '',
            'phone' => $paymentInformation['userInformation']['phone'] ?? '',
            'country' => $paymentInformation['userInformation']['country'] ?? 'Bangladesh',
            'deliveryTime' => $paymentInformation['userInformation']['deliveryTime'] ?? '',
            'shipmentType' => $paymentInformation['userInformation']['shipmentType'] ?? '',
            'addressType' => $paymentInformation['userInformation']['addressType'] ?? '',
            'email' => $paymentInformation['userInformation']['email'] ?? Auth::user()->email,
        ];

        if ($selectedAddress) {
            $userInformation['address'] = $selectedAddress['address_line1'] ?? $userInformation['address'];
            $userInformation['apartment'] = $selectedAddress['address_line2'] ?? $userInformation['apartment'];
            $userInformation['city'] = $selectedAddress['city'] ?? $userInformation['city'];
            $userInformation['state'] = $selectedAddress['state'] ?? $userInformation['state'];
            $userInformation['zip'] = $selectedAddress['postal_code'] ?? $userInformation['zip'];
            $userInformation['phone'] = $selectedAddress['phone'] ?? $userInformation['phone'];
            $userInformation['country'] = $selectedAddress['country'] ?? $userInformation['country'];
            $userInformation['savedAddressId'] = $selectedAddress['id'] ?? null;
        }

        return [
            'products' => $products,
            'userInformation' => $userInformation,
        ];
    }

    private function transformOrderSummary($order): array
    {
        $cartData = $this->parseOrderCartData($order->cart_data);
        $products = $this->extractProductsFromCartData($cartData);

        return [
            'id' => (int) $order->id,
            'order_number' => '#'.$order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'tran_id' => $order->tran_id,
            'order_date' => $order->created_at,
            'item_count' => (int) ($order->item_count ?? $this->sumProductQuantity($products)),
            'delivery_method' => $this->resolveDeliveryMethod($cartData),
            'amount_payable' => (float) $order->total_amount,
            'can_order_again' => count($products) > 0,
        ];
    }

    private function transformOrderDetails($order): array
    {
        $cartData = $this->parseOrderCartData($order->cart_data);
        $products = $this->extractProductsFromCartData($cartData);
        $customer = DB::table('users')
            ->where('id', $order->user_id)
            ->select('name', 'email', 'phone', 'address1', 'address2', 'city', 'state', 'postcode', 'country')
            ->first();

        $trackingEntries = DB::table('order_tracking')
            ->where('order_id', $order->id)
            ->orderBy('updated_at')
            ->get();

        $transaction = DB::table('transactions')
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        $items = DB::table('order_items')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('subcategories', 'products.subcategory_id', '=', 'subcategories.id')
            ->leftJoin('product_images', function ($join) {
                $join->on('products.id', '=', 'product_images.product_id')
                    ->where('product_images.is_primary', 1);
            })
            ->where('order_items.order_id', $order->id)
            ->select(
                'order_items.id',
                'order_items.product_id',
                'order_items.quantity',
                'order_items.price as order_price',
                'products.name as product_name',
                'products.price as current_price',
                'products.description',
                'subcategories.name as category_name',
                'product_images.image_url'
            )
            ->get()
            ->map(function ($item) use ($order) {
                $currentPrice = (float) ($item->current_price ?? $item->order_price ?? 0);
                $orderPrice = (float) ($item->order_price ?? 0);

                return [
                    'id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'name' => $item->product_name ?: 'Product',
                    'category' => $item->category_name ?: 'Store item',
                    'description' => $item->description ?: '',
                    'quantity' => (int) ($item->quantity ?? 0),
                    'price' => $orderPrice,
                    'current_price' => $currentPrice,
                    'compare_price' => $currentPrice > $orderPrice ? $currentPrice : null,
                    'image_url' => $item->image_url,
                    'status' => $order->status,
                ];
            })
            ->values();

        return [
            'id' => (int) $order->id,
            'order_number' => '#'.$order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'tran_id' => $order->tran_id,
            'order_date' => $order->created_at,
            'updated_at' => $order->updated_at,
            'amount_payable' => (float) $order->total_amount,
            'delivery_method' => $this->resolveDeliveryMethod($cartData),
            'item_count' => $items->sum('quantity'),
            'shipping_address' => $this->buildShippingAddress($cartData, $customer, $order->address_id ?? null),
            'items' => $items,
            'timeline' => $this->buildOrderTimeline($order, $trackingEntries, $transaction),
        ];
    }

    private function parseOrderCartData($rawCartData): array
    {
        if (empty($rawCartData)) {
            return ['products' => [], 'userInformation' => []];
        }

        $decoded = json_decode($rawCartData, true);

        if (! is_array($decoded)) {
            return ['products' => [], 'userInformation' => []];
        }

        if (isset($decoded['products']) || isset($decoded['userInformation'])) {
            return [
                'products' => is_array($decoded['products'] ?? null) ? $decoded['products'] : [],
                'userInformation' => is_array($decoded['userInformation'] ?? null) ? $decoded['userInformation'] : [],
            ];
        }

        return [
            'products' => array_values(array_filter($decoded, function ($item) {
                return is_array($item);
            })),
            'userInformation' => [],
        ];
    }

    private function extractProductsFromCartData(array $cartData): array
    {
        return is_array($cartData['products'] ?? null) ? $cartData['products'] : [];
    }

    private function sumProductQuantity(array $products): int
    {
        return array_reduce($products, function ($carry, $item) {
            return $carry + (int) ($item['quantity'] ?? 0);
        }, 0);
    }

    private function resolveDeliveryMethod(array $cartData): string
    {
        $shipmentType = strtolower((string) ($cartData['userInformation']['shipmentType'] ?? ''));

        if ($shipmentType === 'free') {
            return 'Free Delivery';
        }

        if ($shipmentType === 'flat') {
            return 'Flat Rate Shipment';
        }

        return 'Standard Delivery';
    }

    private function buildShippingAddress(array $cartData, $customer, ?int $addressId = null): array
    {
        $userInformation = $cartData['userInformation'] ?? [];
        $storedAddress = $this->resolveStoredAddressById($addressId);
        $storedAddressArray = $storedAddress ? $this->transformStoredAddressRecord($storedAddress) : null;
        $fullName = trim((string) ($userInformation['name'] ?? $customer->name ?? ''));
        $addressParts = array_filter([
            $userInformation['address'] ?? ($storedAddressArray['address_line1'] ?? $customer->address1 ?? ''),
            $userInformation['apartment'] ?? ($storedAddressArray['address_line2'] ?? $customer->address2 ?? ''),
            $userInformation['city'] ?? ($storedAddressArray['city'] ?? $customer->city ?? ''),
            $userInformation['state'] ?? ($storedAddressArray['state'] ?? $customer->state ?? ''),
            $userInformation['zip'] ?? ($storedAddressArray['postal_code'] ?? $customer->postcode ?? ''),
            $userInformation['country'] ?? ($storedAddressArray['country'] ?? $customer->country ?? ''),
        ]);

        return [
            'name' => $fullName,
            'phone' => $userInformation['phone'] ?? ($storedAddressArray['phone'] ?? $customer->phone ?? ''),
            'email' => $userInformation['email'] ?? $customer->email ?? '',
            'address' => implode(', ', $addressParts),
            'address_type' => $userInformation['addressType'] ?? '',
            'delivery_time' => $userInformation['deliveryTime'] ?? '',
            'city' => $userInformation['city'] ?? ($storedAddressArray['city'] ?? $customer->city ?? ''),
            'state' => $userInformation['state'] ?? ($storedAddressArray['state'] ?? $customer->state ?? ''),
            'zip' => $userInformation['zip'] ?? ($storedAddressArray['postal_code'] ?? $customer->postcode ?? ''),
            'country' => $userInformation['country'] ?? ($storedAddressArray['country'] ?? $customer->country ?? ''),
            'saved_address_id' => $storedAddressArray['id'] ?? ($userInformation['savedAddressId'] ?? null),
        ];
    }

    private function resolveCheckoutAddress(array $paymentInformation): ?array
    {
        $addressId = isset($paymentInformation['address_id']) ? (int) $paymentInformation['address_id'] : 0;

        if ($addressId > 0) {
            $address = DB::table('user_addresses')
                ->where('id', $addressId)
                ->where('user_id', Auth::id())
                ->first();

            if (! $address) {
                throw new InvalidArgumentException('Selected shipping address could not be found.');
            }

            return $this->transformStoredAddressRecord($address);
        }

        if ((bool) ($paymentInformation['save_address'] ?? false)) {
            return $this->storeUserAddressFromCheckout(
                $paymentInformation['userInformation'] ?? [],
                (bool) ($paymentInformation['is_primary_address'] ?? false)
            );
        }

        return null;
    }

    private function storeUserAddressFromCheckout(array $userInformation, bool $isPrimaryRequested): array
    {
        $addressLine1 = trim((string) ($userInformation['address'] ?? ''));

        if ($addressLine1 === '') {
            throw new InvalidArgumentException('Shipping address is required to save a new address.');
        }

        $hasExistingAddresses = DB::table('user_addresses')
            ->where('user_id', Auth::id())
            ->exists();

        $isPrimary = $isPrimaryRequested || ! $hasExistingAddresses;

        if ($isPrimary) {
            DB::table('user_addresses')
                ->where('user_id', Auth::id())
                ->update([
                    'is_primary' => 0,
                    'updated_at' => now(),
                ]);
        }

        $addressId = DB::table('user_addresses')->insertGetId([
            'user_id' => Auth::id(),
            'address_line1' => $addressLine1,
            'address_line2' => $this->nullableTrim($userInformation['apartment'] ?? null),
            'city' => $this->nullableTrim($userInformation['city'] ?? null),
            'state' => $this->nullableTrim($userInformation['state'] ?? null),
            'postal_code' => $this->nullableTrim($userInformation['zip'] ?? null),
            'country' => $this->nullableTrim($userInformation['country'] ?? null),
            'phone' => $this->nullableTrim($userInformation['phone'] ?? null),
            'is_primary' => $isPrimary ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $address = DB::table('user_addresses')->where('id', $addressId)->first();

        return $this->transformStoredAddressRecord($address);
    }

    private function resolveStoredAddressById(?int $addressId)
    {
        if (empty($addressId)) {
            return null;
        }

        return DB::table('user_addresses')->where('id', $addressId)->first();
    }

    private function transformStoredAddressRecord($address): array
    {
        return [
            'id' => (int) $address->id,
            'address_line1' => $address->address_line1 ?? '',
            'address_line2' => $address->address_line2 ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'postal_code' => $address->postal_code ?? '',
            'country' => $address->country ?? '',
            'phone' => $address->phone ?? '',
            'is_primary' => (bool) ($address->is_primary ?? false),
        ];
    }

    private function nullableTrim($value)
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function buildOrderTimeline($order, $trackingEntries, $transaction): array
    {
        $trackingByStatus = $trackingEntries->groupBy('status');
        $paymentDate = $transaction->tran_date ?? $transaction->created_at ?? null;
        $processingDate = optional($trackingByStatus->get('processing'))->last()->updated_at ?? null;
        $shippedDate = optional($trackingByStatus->get('shipped'))->last()->updated_at ?? null;
        $deliveredDate = optional($trackingByStatus->get('delivered'))->last()->updated_at ?? null;
        $cancelledDate = optional($trackingByStatus->get('cancelled'))->last()->updated_at ?? null;

        $isPaid = $order->payment_status === 'paid';
        $status = $order->status;

        $steps = [
            [
                'key' => 'order_placed',
                'title' => 'Order Placed',
                'description' => 'Your order has been received successfully and is waiting for the next step.',
                'date' => $order->created_at,
                'state' => 'completed',
            ],
            [
                'key' => 'payment',
                'title' => 'Payment',
                'description' => $isPaid
                    ? 'Your payment was verified successfully and the order is now moving forward.'
                    : 'Your payment is still pending confirmation.',
                'date' => $isPaid ? $paymentDate : null,
                'state' => $isPaid ? 'completed' : ($status === 'cancelled' ? 'upcoming' : 'current'),
            ],
            [
                'key' => 'processing',
                'title' => 'Processing',
                'description' => 'We are reviewing the order and getting the requested items ready.',
                'date' => in_array($status, ['processing', 'shipped', 'delivered'], true) ? ($processingDate ?: $order->updated_at) : null,
                'state' => in_array($status, ['shipped', 'delivered'], true)
                    ? 'completed'
                    : ($status === 'processing' ? 'current' : 'upcoming'),
            ],
            [
                'key' => 'packing',
                'title' => 'Packing',
                'description' => 'The products are being packed carefully for shipment.',
                'date' => in_array($status, ['shipped', 'delivered'], true) ? ($shippedDate ?: $order->updated_at) : null,
                'state' => $status === 'delivered'
                    ? 'completed'
                    : ($status === 'shipped' ? 'current' : 'upcoming'),
            ],
            [
                'key' => 'delivering',
                'title' => 'Delivering',
                'description' => 'Your order is on the way to your shipping address.',
                'date' => in_array($status, ['shipped', 'delivered'], true) ? ($shippedDate ?: $order->updated_at) : null,
                'state' => $status === 'delivered'
                    ? 'completed'
                    : ($status === 'shipped' ? 'current' : 'upcoming'),
            ],
            [
                'key' => 'delivered',
                'title' => 'Delivered',
                'description' => 'The order has been delivered successfully.',
                'date' => $status === 'delivered' ? ($deliveredDate ?: $order->updated_at) : null,
                'state' => $status === 'delivered' ? 'completed' : 'upcoming',
            ],
        ];

        if ($status === 'cancelled') {
            $steps[] = [
                'key' => 'cancelled',
                'title' => 'Cancelled',
                'description' => 'This order was cancelled before completion.',
                'date' => $cancelledDate ?: $order->updated_at,
                'state' => 'current',
            ];
        }

        return $steps;
    }

    private function resolveSettledAmount($order, array $verifyData): float
    {
        if (isset($verifyData['store_amount']) && is_numeric($verifyData['store_amount'])) {
            return (float) $verifyData['store_amount'];
        }

        if (isset($verifyData['amount']) && is_numeric($verifyData['amount'])) {
            return (float) $verifyData['amount'];
        }

        return (float) ($order->total_amount ?? 0);
    }

    private function normalizeTransactionDate(?string $transactionDate): string
    {
        if (empty($transactionDate)) {
            return now()->toDateTimeString();
        }

        $timestamp = strtotime($transactionDate);

        return $timestamp === false
            ? now()->toDateTimeString()
            : date('Y-m-d H:i:s', $timestamp);
    }

    private function limitValue(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, $length);
    }
}
