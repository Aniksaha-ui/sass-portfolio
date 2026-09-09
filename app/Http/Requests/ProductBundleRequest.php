<?php

namespace App\Http\Requests;

class ProductBundleRequest extends ApiRequest
{
    public function rules()
    {
        return ['name' => 'required|string|max:255', 'description' => 'nullable|string', 'price' => 'required|numeric|min:0', 'discount_price' => 'nullable|numeric|min:0|lte:price', 'is_active' => 'nullable|boolean'];
    }
}
