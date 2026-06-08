<?php

namespace App\Repository\Services\User\Products;

use App\Constants\ResponseConstants;
use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class ProductService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }



    public function getSectionWiseProducts($search)
    {
        try {
            $products = DB::table('sections as s')
                ->join('section_products as sp', 'sp.section_id', '=', 's.id')
                ->join('products as p', 'sp.product_id', '=', 'p.id')
                ->join('subcategories', 'p.subcategory_id', '=', 'subcategories.id')
                ->join('categories', 'subcategories.category_id', '=', 'categories.id')
                ->leftJoin('product_discounts as pd', 'pd.product_id', '=', 'p.id')
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('pi.product_id', '=', 'p.id')
                        ->where('pi.is_primary', '=', 1);
                })
                ->join('inventory as i', 'i.product_id', '=', 'p.id')
                ->select(
                    's.name as section_name',
                    'p.id as product_id',
                    'p.name as product_name',
                    'categories.name as category_name',
                    'subcategories.name as subcategory_name',
                    'p.description',
                    'p.price',
                    'pd.discount_type',
                    'pd.discount_value',
                    'pi.image_url as primary_image',
                    'i.stock_quantity',
                )
                ->where('s.is_active', 1)
                ->where('p.is_active', 1)
                ->where('i.stock_quantity', '>', 0)
                ->where(function ($query) use ($search) {
                    $query->where('p.name', 'like', '%' . $search . '%')
                        ->orWhere('subcategories.name', 'like', '%' . $search . '%')
                        ->orWhere('categories.name', 'like', '%' . $search . '%');
                })
                ->orderBy('s.display_order', 'asc')
                ->orderBy('sp.display_order', 'asc')
                ->take(100)
                ->get();

            if ($products->count() > 0) {

                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Product fetched successfully",
                    "data" => $products
                ];
            } else {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "No product found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProductService : getSectionWiseProducts function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getProductDetailsById($id)
    {
        try {
            $productDetails = DB::table('products')
                ->join('subcategories', 'products.subcategory_id', '=', 'subcategories.id')
                ->join('categories', 'subcategories.category_id', '=', 'categories.id')
                ->leftJoin('product_discounts as pd', 'pd.product_id', '=', 'products.id')
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('pi.product_id', '=', 'products.id')
                        ->where('pi.is_primary', '=', 1);
                })
                ->join('inventory as i', 'i.product_id', '=', 'products.id')
                ->select(
                    'products.id as product_id',
                    'products.name as productsroduct_name',
                    'categories.name as category_name',
                    'subcategories.name as subcategory_name',
                    'products.description',
                    'products.price',
                    'pd.discount_type',
                    'pd.discount_value',
                    'pi.image_url as primary_image',
                    'i.stock_quantity'
                )
                ->where('products.id', $id)->first();
            if ($productDetails) {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Product details fetched successfully",
                    "data" => $productDetails
                ];
            } else {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "No Product found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProductService :getProductDetailsById function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getAllCategories()
    {
        try {
            $categories = DB::table('categories')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            if ($categories->count() > 0) {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Categories fetched successfully",
                    "data" => $categories
                ];
            }

            return [
                "status" => ResponseConstants::SUCCESS,
                "message" => "No category found",
                "data" => []
            ];
        } catch (Exception $ex) {
            Log::error("ProductService : getAllCategories function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getCategoryWiseProducts($id)
    {
        try {
            $categoryWiseProducts = DB::table('products')
                ->join('subcategories', 'products.subcategory_id', '=', 'subcategories.id')
                ->join('categories', 'subcategories.category_id', '=', 'categories.id')
                ->leftJoin('product_discounts as pd', 'pd.product_id', '=', 'products.id')
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('pi.product_id', '=', 'products.id')
                        ->where('pi.is_primary', '=', 1);
                })
                ->join('inventory as i', 'i.product_id', '=', 'products.id')
                ->select(
                    'products.id as product_id',
                    'products.name as productsroduct_name',
                    'categories.name as category_name',
                    'subcategories.name as subcategory_name',
                    'products.description',
                    'products.price',
                    'pd.discount_type',
                    'pd.discount_value',
                    'pi.image_url as primary_image',
                    'i.stock_quantity'
                )
                ->where('categories.id', $id)
                ->get();


            if ($categoryWiseProducts->count() > 0) {

                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Product fetched successfully",
                    "data" => $categoryWiseProducts
                ];
            } else {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "No product found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("ProductService :getCategoryWiseProducts function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(ResponseConstants::FAILED, $ex->getMessage(), []);
        }
    }
}
