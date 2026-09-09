<?php

namespace App\Http\Requests;

class CartUpdateRequest extends ApiRequest
{
    public function rules()
    {
        return ['id' => 'required|integer|exists:cart_items,id', 'quantity' => 'required|integer|min:1'];
    }
}
