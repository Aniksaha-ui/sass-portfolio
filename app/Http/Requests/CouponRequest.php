<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CouponRequest extends ApiRequest
{
    public function rules()
    {
        return ['code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($this->route('id'))], 'discount_type' => 'required|in:flat,percentage', 'discount_value' => 'required|numeric|gt:0', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'max_usage' => 'nullable|integer|min:0'];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('discount_type') === 'percentage' && (float) $this->input('discount_value') > 100) $validator->errors()->add('discount_value', 'Percentage discounts cannot exceed 100.');
        });
    }
}
