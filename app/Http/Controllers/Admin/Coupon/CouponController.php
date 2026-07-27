<?php

namespace App\Http\Controllers\Admin\Coupon;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Coupon\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    private $coupons;

    public function __construct(CouponService $coupons)
    {
        $this->coupons = $coupons;
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('perPage', 10), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        return $this->respond(true, 'Coupons fetched successfully', $this->coupons->paginate($perPage, $page, $request->query('search', '')));
    }

    public function show($id)
    {
        $coupon = $this->coupons->find($id);
        return $coupon ? $this->respond(true, 'Coupon fetched successfully', $coupon) : $this->respond(false, 'Coupon not found', [], 404);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Coupon created successfully', $this->coupons->create($data), 201);
    }

    public function update($id, Request $request)
    {
        if (!$this->coupons->find($id)) return $this->respond(false, 'Coupon not found', [], 404);
        $data = $this->validated($request, $id);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Coupon updated successfully', $this->coupons->update($id, $data));
    }

    public function destroy($id)
    {
        return $this->coupons->delete($id) ? $this->respond(true, 'Coupon deleted successfully', []) : $this->respond(false, 'Coupon not found', [], 404);
    }

    private function validated(Request $request, $id = null)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:coupons,code' . ($id ? ',' . $id : ''),
            'discount_type' => 'required|in:flat,percentage',
            'discount_value' => 'required|numeric|gt:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_usage' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);

        $data = $validator->validated();
        if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) return $this->respond(false, 'Validation error', ['discount_value' => ['Percentage discounts cannot exceed 100.']], 422);
        $data['code'] = strtoupper(trim($data['code']));
        $data['max_usage'] = $data['max_usage'] ?? 0;
        return $data;
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
