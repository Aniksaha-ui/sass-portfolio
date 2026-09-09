<?php

namespace App\Http\Requests;

class AddressRequest extends ApiRequest
{
    public function rules()
    {
        return ['address_line1' => 'required|string|max:255', 'address_line2' => 'nullable|string|max:255', 'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:20', 'country' => 'nullable|string|max:50', 'phone' => 'nullable|string|max:20', 'is_primary' => 'nullable|boolean'];
    }
}
