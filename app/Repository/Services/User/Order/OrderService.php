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
                ? 'https://dev-securepay.sslcommerz.com/gwprocess/v4/api.php'
                : 'https://dev-securepay.sslcommerz.com/gwprocess/v4/api.php';

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
            $tran_id = $request->tran_id;

            // Verify payment authenticity with SSLCommerz
            $verifyURL = env('IS_SANDBOX')
                ? "https://dev-securepay.sslcommerz.com/validator/api/validationserverAPI.php"
                : "https://dev-securepay.sslcommerz.com/validator/api/validationserverAPI.php";



            $verifyResponse = Http::get($verifyURL, [
                'val_id' => $request->val_id,
                'store_id' => env('STORE_ID'),
                'store_passwd' => env('STORE_PASSWORD'),
                'v' => 1,
                'format' => 'json'
            ]);

            $verifyData = $verifyResponse->json();


            if ($verifyData['status'] === 'VALID' || $verifyData['status'] === 'VALIDATED') {
                // Update order & transaction
                $orderPaymentStatus =  DB::table('orders')->where('id', $verifyData['value_a'])->update([
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
                ]);

                // order information update

                $cart = DB::table('cart')->where('user_id', Auth::id())->delete();

                DB::commit();

                $frontendUrl = env('FRONTEND_URL') . '/payment/success?tran_id=' . $request->tran_id . '&status=success';
                return redirect($frontendUrl);
            }

            DB::rollBack();
            return response()->json(['status' => 'failed', 'message' => 'Payment validation failed']);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error("PaymentService : paymentSuccess() => " . $ex->getMessage());
            return response()->json(['status' => 'failed', 'message' => $ex->getMessage()]);
        }
    }
}
