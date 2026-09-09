<?php

namespace App\Http\Requests;

class ProductStockRequest extends ApiRequest
{
    public function rules()
    {
        return ['product_id' => 'required|integer|exists:products,id', 'warehouse_location' => 'required|string|max:100', 'stock_quantity' => 'required|integer|min:0', 'reason' => 'nullable|string|max:255'];
    }
}
