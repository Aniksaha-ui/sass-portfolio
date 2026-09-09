<?php

namespace App\Http\Requests;

class CartAddRequest extends ApiRequest
{
    public function rules()
    {
        return ['*' => 'required|array', '*.product_id' => 'required|integer|exists:products,id', '*.quantity' => 'required|integer|min:1'];
    }
}
