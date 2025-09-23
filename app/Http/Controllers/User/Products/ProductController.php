<?php

namespace App\Http\Controllers\User\Products;

use App\Http\Controllers\Controller;
use App\Repository\Services\User\Products\ProductService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    private $productService;
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function homePageProducts(Request $request)
    {
        try {
            $search = $request->query('search') ?? '';
            $sectionWiseProduct = $this->productService->getSectionWiseProducts($search);

            return response()->json([
                "isExecuted" => $sectionWiseProduct['status'],
                "message" => $sectionWiseProduct['message'],
                "data" => $sectionWiseProduct['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("ProductController : homePageProducts function error: " . $ex->getMessage());
        }
    }


    public function productDetails($id){
        try{

            $validator = Validator::make(['id' => $id], [
                'id' => 'required|gt:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "isExecuted" => false,
                    "message" => "Validation Error",
                    "errors" => $validator->errors()
                ], 422);
            }

            $sectionWiseProduct = $this->productService->getProductDetailsById($id);
            return response()->json([
                "isExecuted" => $sectionWiseProduct['status'],
                "message" => $sectionWiseProduct['message'],
                "data" => $sectionWiseProduct['data']
            ], 200);
        }catch(Exception $ex){
            Log::info("ProductController : productDetails function error: " . $ex->getMessage());

        }
    }


    public function categoryWiseProducts($id){
        try{

            $validator = Validator::make(['id' => $id], [
                'id' => 'required|gt:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "isExecuted" => false,
                    "message" => "Validation Error",
                    "errors" => $validator->errors()
                ], 422);
            }

            $sectionWiseProduct = $this->productService->getCategoryWiseProducts($id);
            return response()->json([
                "isExecuted" => $sectionWiseProduct['status'],
                "message" => $sectionWiseProduct['message'],
                "data" => $sectionWiseProduct['data']
            ], 200);
        }catch(Exception $ex){
            Log::info("ProductController : categoryWiseProducts function error: " . $ex->getMessage());

        }
    }


}
