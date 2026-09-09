<?php

namespace App\Http\Requests;

class ReturnRequest extends ApiRequest
{
    public function rules()
    {
        return ['order_id' => 'required|integer|exists:orders,id', 'product_id' => 'required|integer|exists:products,id', 'reason' => 'nullable|string', 'status' => 'required|in:requested,approved,rejected,refunded', 'refund_amount' => 'nullable|numeric|min:0'];
    }
}
