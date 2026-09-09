<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductDiscountRequest;
use App\Repository\Services\Admin\Product\ProductDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductDiscountController extends Controller
{
    private $discounts;

    public function __construct(ProductDiscountService $discounts)
    {
        $this->discounts = $discounts;
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('perPage', 10), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        return $this->respond(true, 'Product discounts fetched successfully', $this->discounts->paginate($perPage, $page, $request->query('search', '')));
    }

    public function options()
    {
        return $this->respond(true, 'Discount product options fetched successfully', $this->discounts->options());
    }

    public function show($id)
    {
        $discount = $this->discounts->find($id);
        return $discount ? $this->respond(true, 'Product discount fetched successfully', $discount) : $this->respond(false, 'Product discount not found', [], 404);
    }

    public function store(ProductDiscountRequest $request)
    {
        $data = $this->validated($request);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Product discount created successfully', $this->discounts->create($data), 201);
    }

    public function update($id, ProductDiscountRequest $request)
    {
        if (!$this->discounts->find($id)) return $this->respond(false, 'Product discount not found', [], 404);
        $data = $this->validated($request, $id);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Product discount updated successfully', $this->discounts->update($id, $data));
    }

    public function destroy($id)
    {
        return $this->discounts->delete($id) ? $this->respond(true, 'Product discount deleted successfully', []) : $this->respond(false, 'Product discount not found', [], 404);
    }

    private function validated(ProductDiscountRequest $request, $id = null)
    {
        return $request->validated();
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
