<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SectionProductRequest extends ApiRequest
{
    public function rules()
    {
        return ['section_id' => 'required|integer|exists:sections,id', 'product_id' => ['required', 'integer', 'exists:products,id', Rule::unique('section_products', 'product_id')->ignore($this->route('id'))->where('section_id', $this->input('section_id'))], 'bundle_id' => 'nullable|integer|exists:product_bundles,id', 'display_order' => 'nullable|integer|min:1'];
    }
}
