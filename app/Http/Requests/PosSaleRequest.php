<?php

namespace App\Http\Requests;

class PosSaleRequest extends ApiRequest
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
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,bank,mobile,cheque,gift_card,external,pay_later,deposit,points,scan',
            'payments.*.amount' => 'required|numeric|gt:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'coupon_code' => 'nullable|string|max:50',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'rounding_amount' => 'nullable|numeric|between:-100,100',
            'note' => 'nullable|string|max:500',
        ];
    }
}
