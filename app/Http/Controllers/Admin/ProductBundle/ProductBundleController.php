<?php

namespace App\Http\Controllers\Admin\ProductBundle;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductBundleRequest;
use App\Repository\Services\Admin\ProductBundle\ProductBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductBundleController extends Controller
{
    public function __construct(private ProductBundleService $bundles) {}

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('perPage', 10), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        return $this->respond(true, 'Product bundles fetched successfully', $this->bundles->paginate($perPage, $page, (string) $request->query('search', '')));
    }

    public function show(int $id)
    {
        return $this->found($this->bundles->find($id));
    }

    public function store(ProductBundleRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Product bundle created successfully', $this->bundles->create($data), 201);
    }

    public function update(int $id, ProductBundleRequest $request)
    {
        if (! $this->bundles->find($id)) {
            return $this->respond(false, 'Product bundle not found', [], 404);
        }
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Product bundle updated successfully', $this->bundles->update($id, $data));
    }

    public function destroy(int $id)
    {
        return $this->bundles->delete($id) ? $this->respond(true, 'Product bundle deleted successfully', []) : $this->respond(false, 'Product bundle not found', [], 404);
    }

    private function validated(Request $request)
    {
        $validator = Validator::make($request->all(), ['name' => 'required|string|max:255', 'description' => 'nullable|string', 'price' => 'required|numeric|min:0', 'discount_price' => 'nullable|numeric|min:0|lte:price', 'is_active' => 'nullable|boolean']);
        if ($validator->fails()) {
            return $this->respond(false, 'Validation error', $validator->errors(), 422);
        }
        $data = $validator->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    private function found($bundle)
    {
        return $bundle ? $this->respond(true, 'Product bundle fetched successfully', $bundle) : $this->respond(false, 'Product bundle not found', [], 404);
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
