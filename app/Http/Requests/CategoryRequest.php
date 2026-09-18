<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CategoryRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->route('id'))],
            'description' => 'nullable|string',
            'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
