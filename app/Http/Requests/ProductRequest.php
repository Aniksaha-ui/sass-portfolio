<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProductRequest extends ApiRequest
{
    public function rules()
    {
        return ['name' => 'required|string|max:255', 'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($this->route('id'))], 'description' => 'nullable|string', 'price' => 'required|numeric|min:0', 'is_active' => 'required|boolean', 'category_id' => 'required|integer|exists:categories,id', 'subcategory_id' => 'required|integer|exists:subcategories,id', 'stock_quantity' => 'required|integer|min:0', 'warehouse_location' => 'nullable|string|max:100', 'images' => 'nullable|array|max:10', 'images.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120', 'discount_type' => 'nullable|in:flat,percentage', 'discount_value' => 'required_with:discount_type|nullable|numeric|min:0', 'discount_start_date' => 'nullable|date', 'discount_end_date' => 'nullable|date|after_or_equal:discount_start_date', 'section_ids' => 'nullable|array', 'section_ids.*' => 'integer|distinct|exists:sections,id', 'display_order' => 'nullable|integer|min:1'];
    }
}
