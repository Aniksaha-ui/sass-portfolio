<?php

namespace App\Http\Requests;

class StockAdjustmentRequest extends ApiRequest
{
    public function rules()
    {
        return ['stock_quantity' => 'required|integer|min:0', 'reason' => 'nullable|string|max:255'];
    }
}
