<?php

namespace App\Repository\Services\User\Order;


use App\Constants\CouponTypeConstant;
use App\Constants\ResponseConstants;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;


class OrderService
{
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

            if ($data['payment_method'] === 'ssl') {
                return $this->initSSLTransaction($data);
            }
            return ['status' => 'failed', 'message' => 'Invalid payment method'];
        } catch (Exception $ex) {
            Log::error("OrderService : order function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function initSSLTransaction($paymentInformation)
    {
        DB::beginTransaction();

        try {
            $tran_id = uniqid('SSL_');

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => Auth::id(),
                'address_id' => null,
                'total_amount' => $paymentInformation['totalAmount'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'cart_data' => json_encode($paymentInformation['products']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);


            foreach ($paymentInformation['products'] as $product) {
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
                'cus_name' => $paymentInformation['userInformation']['name'],
                'cus_email' => Auth::user()->email,
                'cus_add1' => $paymentInformation['userInformation']['address'] ?? 'N/A',
                'cus_city' => $paymentInformation['userInformation']['city'] ?? 'N/A',
                'cus_state' => $paymentInformation['userInformation']['state'] ?? 'N/A',
                'cus_postcode' => $paymentInformation['userInformation']['zip'] ?? '0000',
                'cus_country' => 'Bangladesh',
                'cus_phone' => $paymentInformation['userInformation']['phone'] ?? 'N/A',
                'shipping_method' => 'NO',
                'product_name' => 'Order #' . $orderId,
                'product_category' => 'Ecommerce',
                'product_profile' => 'general',
                'value_a' => $orderId, // Custom field to track order
            ];



            $url = env('IS_SANDBOX')
                ? 'https://uat-securepay.sslcommerz.com/gwprocess/v4/api.php'
                : 'https://uat-securepay.sslcommerz.com/gwprocess/v4/api.php';

            $response = Http::asForm()->post($url, $post_data);
            $sslResponse = $response->json();

            if (!empty($sslResponse['GatewayPageURL'])) {
                DB::commit();
                return [
                    'status' => 'success',
                    'url' => $sslResponse['GatewayPageURL'],
                    'tran_id' => $tran_id,
                    'message' => 'Redirect to SSLCommerz gateway'
                ];
            } else {
                DB::rollBack();
                return ['status' => 'failed', 'message' => 'SSLCommerz gateway initialization failed'];
            }
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error("PaymentService : initSSLTransaction() => " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }





    public function success(Request $request)
    {
        DB::beginTransaction();

        try {
            Log::info("Payment Success Callback: " . json_encode($request->all()));
            $tran_id = $request->tran_id;

            // Verify payment authenticity with SSLCommerz
            $verifyURL = env('IS_SANDBOX')
                ? "https://uat-securepay.sslcommerz.com/validator/api/validationserverAPI.php"
                : "https://uat-securepay.sslcommerz.com/validator/api/validationserverAPI.php";



            $verifyResponse = Http::get($verifyURL, [
                'val_id' => $request->val_id,
                'store_id' => env('STORE_ID'),
                'store_passwd' => env('STORE_PASSWORD'),
                'v' => 1,
                'format' => 'json'
            ]);

            $verifyData = $verifyResponse->json();


            if ($verifyData['status'] === 'VALID' || $verifyData['status'] === 'VALIDATED') {
                $paymentChannel = $this->resolvePaymentChannel($verifyData);
                $order = DB::table('orders')->where('id', $verifyData['value_a'])->first();

                if (! $order) {
                    DB::rollBack();
                    return response()->json(['status' => 'failed', 'message' => 'Order not found']);
                }

                // Update order & transaction
                DB::table('orders')->where('id', $verifyData['value_a'])->update([
                    'status' => 'processing',
                    'payment_status' => 'paid',
                    'tran_id' => $request->tran_id,
                    'updated_at' => now(),
                ]);


                DB::table('transactions')->where('order_id', $verifyData['value_a'])->update([
                    'status' => 'success',
                    'bank_ssl_id' => $verifyData['bank_tran_id'],
                    'tran_date' => $verifyData['tran_date'],
                    'currency' => $verifyData['currency'],
                    'store_amount' => $verifyData['store_amount'],
                    'card_no' => $verifyData['card_no'],
                    'risk_title' => $verifyData['risk_title'],
                    'settlement_status' => $verifyData['settlement_status'],
                    'bank_approval_id' => $verifyData['bank_approval_id'],
                    'cus_phone' => $verifyData['cus_phone'],
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

                // order information update

                DB::table('cart')->where('user_id', $order->user_id)->delete();

                DB::commit();

                return redirect($this->buildFrontendPaymentUrl('success', $request->tran_id));
            }

            DB::rollBack();
            return response()->json(['status' => 'failed', 'message' => 'Payment validation failed']);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error("PaymentService : paymentSuccess() => " . $ex->getMessage());
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage()]);
        }
    }

    public function fail(Request $request)
    {
        return $this->handlePaymentOutcome($request, 'failed', 'failed');
    }

    public function cancel(Request $request)
    {
        return $this->handlePaymentOutcome($request, 'cancelled', 'cancel');
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

    private function handlePaymentOutcome(Request $request, string $paymentStatus, string $frontendStatus)
    {
        DB::beginTransaction();

        try {
            Log::info(sprintf('Payment %s Callback: %s', ucfirst($paymentStatus), json_encode($request->all())));

            $order = $this->resolveOrderFromCallback($request);

            if (! $order) {
                DB::rollBack();
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order not found',
                ], 404);
            }

            DB::table('orders')->where('id', $order->id)->update([
                'status' => $paymentStatus,
                'payment_status' => $paymentStatus,
                'updated_at' => now(),
            ]);

            DB::table('transactions')->where('order_id', $order->id)->update([
                'status' => $paymentStatus,
            ]);

            DB::table('order_tracking')->insert([
                'order_id' => $order->id,
                'status' => $paymentStatus,
                'location' => $request->card_issuer_country_code ?? 'N/A',
            ]);

            DB::commit();

            return redirect($this->buildFrontendPaymentUrl($frontendStatus, $request->tran_id));
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error(sprintf('PaymentService : payment%s() => %s', ucfirst($paymentStatus), $ex->getMessage()));

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
        $baseUrl = rtrim((string) env('FRONTEND_URL'), '/');
        $query = http_build_query(array_filter([
            'tran_id' => $transactionId,
            'status' => $status,
        ]));

        return $baseUrl.'/payment/'.$status.($query !== '' ? '?'.$query : '');
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
