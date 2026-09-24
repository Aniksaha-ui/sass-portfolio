<?php

namespace App\Http\Requests;

class PosHoldRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'customer_id' => 'nullable|integer|exists:users,id',
            'walk_in_name' => 'nullable|string|max:255',
            'walk_in_email' => 'nullable|email|max:255',
            'walk_in_phone' => 'nullable|string|max:30',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.image_url' => 'nullable|string|max:500',
            'items.*.stock_quantity' => 'nullable|integer|min:0',
            'items.*.category_name' => 'nullable|string|max:255',
            'discount_amount' => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:50',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'rounding_amount' => 'nullable|numeric|between:-100,100',
            'payments' => 'nullable|array',
        ];
    }
}
