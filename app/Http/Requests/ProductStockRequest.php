<?php

namespace App\Http\Requests;

use App\Support\OperationsConstants;
use Illuminate\Validation\Rule;

class ProductStockRequest extends ApiRequest
{
    public function rules()
    {
        return ['product_id' => 'required|integer|exists:products,id', 'warehouse_location' => ['required', 'string', Rule::in(OperationsConstants::WAREHOUSES)], 'stock_quantity' => 'required|integer|min:0', 'reason' => 'nullable|string|max:255'];
    }
}
