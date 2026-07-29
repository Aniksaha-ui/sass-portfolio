<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Product\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    private $products;
    public function __construct(ProductService $products)
    {
        $this->products = $products;
    }
    public function index(Request $request)
    {
        return $this->respond(true, 'Products fetched successfully', $this->products->paginate(min(max((int) $request->query('perPage', 10), 1), 100), max((int) $request->query('page', 1), 1), $request->query('search', '')));
    }
    public function options()
    {
        return $this->respond(true, 'Product options fetched successfully', $this->products->options());
    }
    public function show($id)
    {
        $product = $this->products->find($id);
        return $product ? $this->respond(true, 'Product fetched successfully', $product) : $this->respond(false, 'Product not found', [], 404);
    }
    public function store(Request $request)
    {
        $data = $this->validated($request);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Product created successfully', $this->products->save($data), 201);
    }
    public function update($id, Request $request)
    {
        if (!$this->products->find($id)) return $this->respond(false, 'Product not found', [], 404);
        $data = $this->validated($request, $id);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Product updated successfully', $this->products->save($data, $id));
    }
    public function destroy($id)
    {
        return $this->products->delete($id) ? $this->respond(true, 'Product deleted successfully', []) : $this->respond(false, 'Product not found', [], 404);
    }
    private function validated(Request $request, $id = null)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50|unique:products,sku' . ($id ? ',' . $id : ''),
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'category_id' => 'required|integer|exists:categories,id',
            'subcategory_id' => 'required|integer|exists:subcategories,id',
            'stock_quantity' => 'required|integer|min:0',
            'warehouse_location' => 'nullable|string|max:100',
            'images' => 'nullable|array|max:10',
            'images.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
            'discount_type' => 'nullable|in:flat,percentage',
            'discount_value' => 'required_with:discount_type|nullable|numeric|min:0',
            'discount_start_date' => 'nullable|date',
            'discount_end_date' => 'nullable|date|after_or_equal:discount_start_date',
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'integer|distinct|exists:sections,id',
            'display_order' => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);
        $data = $validator->validated();
        if (!\Illuminate\Support\Facades\DB::table('subcategories')->where('id', $data['subcategory_id'])->where('category_id', $data['category_id'])->exists()) return $this->respond(false, 'The selected subcategory does not belong to the selected category.', ['subcategory_id' => ['Invalid category relationship.']], 422);
        $data['images'] = $request->file('images', []);
        $data['section_ids'] = $data['section_ids'] ?? [];
        $data['is_active'] = (bool) $data['is_active'];
        $data['display_order'] = $data['display_order'] ?? 1;
        return $data;
    }
    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
