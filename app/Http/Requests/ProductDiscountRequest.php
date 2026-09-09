<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProductDiscountRequest extends ApiRequest
{
    public function rules()
    {
        return ['product_id' => ['required', 'integer', 'exists:products,id', Rule::unique('product_discounts', 'product_id')->ignore($this->route('id'))], 'discount_type' => 'required|in:flat,percentage', 'discount_value' => 'required|numeric|gt:0', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date'];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('discount_type') === 'percentage' && (float) $this->input('discount_value') > 100) $validator->errors()->add('discount_value', 'Percentage discounts cannot exceed 100.');
        });
    }
}
