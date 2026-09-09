<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SubcategoryRequest extends ApiRequest
{
    public function rules()
    {
        return ['category_id' => 'required|integer|exists:categories,id', 'name' => ['required', 'string', 'max:100', Rule::unique('subcategories', 'name')->ignore($this->route('id'))], 'description' => 'nullable|string'];
    }
}
