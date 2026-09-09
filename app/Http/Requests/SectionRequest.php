<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SectionRequest extends ApiRequest
{
    public function rules()
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('sections', 'name')->ignore($this->route('id'))], 'display_order' => 'nullable|integer|min:1', 'is_active' => 'nullable|boolean'];
    }
}
