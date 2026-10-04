<?php

namespace App\Repository\Services\User\Products;

use App\Constants\ResponseConstants;
use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use App\Helpers\CommonLogger as Log;
use DB;


class ProductService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }



    public function getSectionWiseProducts($search, $perPage = 10, $page = 1)
    {
        try {
            $reviews = DB::table('product_reviews')
                ->select('product_id', DB::raw('AVG(rating) as average_rating'), DB::raw('COUNT(*) as review_count'))
                ->groupBy('product_id');

            $products = DB::table('sections as s')
                ->join('section_products as sp', 'sp.section_id', '=', 's.id')
                ->join('products as p', 'sp.product_id', '=', 'p.id')
                ->join('subcategories', 'p.subcategory_id', '=', 'subcategories.id')
                ->join('categories', 'subcategories.category_id', '=', 'categories.id')
                ->leftJoin('product_discounts as pd', function ($join) {
                    $join->on('pd.product_id', '=', 'p.id')
                        ->where(function ($query) {
                            $query->whereNull('pd.start_date')->orWhereDate('pd.start_date', '<=', now());
                        })
                        ->where(function ($query) {
                            $query->whereNull('pd.end_date')->orWhereDate('pd.end_date', '>=', now());
                        });
                })
                ->leftJoinSub($reviews, 'reviews', fn ($join) => $join->on('reviews.product_id', '=', 'p.id'))
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('pi.product_id', '=', 'p.id')
                        ->where('pi.is_primary', '=', 1);
                })
                ->join('inventory as i', 'i.product_id', '=', 'p.id')
                ->select(
                    's.id as section_id',
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
                    DB::raw('COALESCE(reviews.average_rating, 0) as average_rating'),
                    DB::raw('COALESCE(reviews.review_count, 0) as review_count'),
                )
                ->where('s.is_active', 1)
                ->where('p.is_active', 1)
                ->where('i.stock_quantity', '>', 0)
                ->where(function ($query) use ($search) {
                    $query->where('p.name', 'like', '%' . $search . '%')
                        ->orWhere('subcategories.name', 'like', '%' . $search . '%')
                        ->orWhere('categories.name', 'like', '%' . $search . '%')
                        ->orWhere('price', 'like', '%' . $search . '%');
                })
                ->orderBy('s.display_order', 'asc')
                ->orderBy('sp.display_order', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);

            if ($products->total() > 0) {

                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "Product fetched successfully",
                    "data" => $products->items(),
                    "pagination" => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                    ],
                ];
            } else {
                return [
                    "status" => ResponseConstants::SUCCESS,
                    "message" => "No product found",
                    "data" => [],
                    "pagination" => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => 0,
                    ],
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
            $reviews = DB::table('product_reviews')
                ->select('product_id', DB::raw('AVG(rating) as average_rating'), DB::raw('COUNT(*) as review_count'))
                ->groupBy('product_id');

            $productDetails = DB::table('products')
                ->join('subcategories', 'products.subcategory_id', '=', 'subcategories.id')
                ->join('categories', 'subcategories.category_id', '=', 'categories.id')
                ->leftJoin('product_discounts as pd', function ($join) {
                    $join->on('pd.product_id', '=', 'products.id')
                        ->where(function ($query) {
                            $query->whereNull('pd.start_date')->orWhereDate('pd.start_date', '<=', now());
                        })
                        ->where(function ($query) {
                            $query->whereNull('pd.end_date')->orWhereDate('pd.end_date', '>=', now());
                        });
                })
                ->leftJoinSub($reviews, 'reviews', fn ($join) => $join->on('reviews.product_id', '=', 'products.id'))
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('pi.product_id', '=', 'products.id')
                        ->where('pi.is_primary', '=', 1);
                })
                ->join('inventory as i', 'i.product_id', '=', 'products.id')
                ->select(
                    'products.id as product_id',
                    'products.name as product_name',
                    'products.sku as product_sku',
                    'categories.name as category_name',
                    'subcategories.name as subcategory_name',
                    'products.description',
                    'products.price',
                    'pd.discount_type',
                    'pd.discount_value',
                    'pi.image_url as primary_image',
                    'i.stock_quantity',
                    DB::raw('COALESCE(reviews.average_rating, 0) as average_rating'),
                    DB::raw('COALESCE(reviews.review_count, 0) as review_count')
                )
                ->where('products.id', $id)
                ->where('products.is_active', 1)
                ->first();
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
            $categories = DB::table('categories as c')
                ->leftJoin('subcategories as sc', 'sc.category_id', '=', 'c.id')
                ->leftJoin('products as p', function ($join) {
                    $join->on('p.subcategory_id', '=', 'sc.id')
                        ->where('p.is_active', '=', 1);
                })
                ->select(
                    'c.id',
                    'c.name',
                    'c.image',
                    DB::raw('COUNT(DISTINCT p.id) as product_count')
                )
                ->groupBy('c.id', 'c.name', 'c.image')
                ->orderBy('c.name')
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
                    'products.name as product_name',
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
