<?php

namespace App\Http\Requests;

class ProductReviewRequest extends ApiRequest
{
    public function rules()
    {
        return ['user_id' => 'required|integer|exists:users,id', 'product_id' => 'required|integer|exists:products,id', 'rating' => 'required|integer|between:1,5', 'review' => 'required|string|max:5000'];
    }
}
