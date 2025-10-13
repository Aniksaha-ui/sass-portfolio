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

            DB::beginTransaction();
            $post_data = [
                'store_id' => env('STORE_ID'),
                'store_passwd' => env('STORE_PASSWORD'),
                'total_amount' => 10,
                'currency' => 'BDT',
                'tran_id' => uniqid(),

                'success_url' => route('payment.success'),
                'fail_url' => route('payment.fail'),
                'cancel_url' => route('payment.cancel'),

                'emi_option' => 0,

                // ✅ Customer information (required)
                'cus_name' => 'Test User',
                'cus_email' => 'test@gmail.com',
                'cus_add1' => 'House 1, Road 2',
                'cus_add2' => 'Uttara',
                'cus_city' => 'Dhaka',              // required
                'cus_state' => 'Dhaka',             // optional but good to include
                'cus_postcode' => '1230',           // optional
                'cus_country' => 'Bangladesh',      // required
                'cus_phone' => '01628781323',
                'cus_fax' => '',

                // ✅ Shipping info (even if not used)
                'shipping_method' => 'NO',
                'ship_name' => 'Test User',
                'ship_add1' => 'Dhaka',
                'ship_city' => 'Dhaka',
                'ship_state' => 'Dhaka',
                'ship_postcode' => '1230',
                'ship_country' => 'Bangladesh',

                // ✅ Product details
                'product_name' => 'test',
                'product_category' => 'test',
                'product_profile' => 'general',
            ];


            $url = env('IS_SANDBOX')
                ? 'https://dev-securepay.sslcommerz.com/gwprocess/v4/api.php'
                : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';



            $response = Http::asForm()->post($url, $post_data);

            $sslcommerzResponse = $response->json();

            if (isset($sslcommerzResponse['GatewayPageURL']) && $sslcommerzResponse['GatewayPageURL'] != "") {
                return [

                    'status' => 'success',
                    'data' => $sslcommerzResponse['GatewayPageURL'],
                    'message' => 'Order placed successfully'
                ];
            } else {
                return [
                    'status' => 'failed',
                    'data' => [],
                    'message' => 'Order placed failed'
                ];
            }
        } catch (Exception $ex) {
            Log::error("OrderService : order function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function success(Request $request)
    {
        // Here you can verify payment and update your DB
        return response()->json([
            'status' => 'success',
            'data' => $request->all()
        ]);
    }
}
