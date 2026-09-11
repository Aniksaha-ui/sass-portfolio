<?php

namespace App\Http\Requests;

class OrderRequest extends ApiRequest
{
    public function rules()
    {
        return ['payment_method' => 'required|string|max:100', 'totalAmount' => 'required|numeric|min:0', 'products' => 'required|array|min:1', 'products.*.product_id' => 'required|integer|exists:products,id', 'products.*.quantity' => 'required|integer|min:1', 'products.*.price' => 'required|numeric|min:0', 'address_id' => 'nullable|integer|exists:user_addresses,id', 'save_address' => 'nullable|boolean', 'is_primary_address' => 'nullable|boolean', 'userInformation' => 'required|array', 'userInformation.name' => 'required|string|max:255', 'userInformation.address' => 'required_without:address_id|string|max:255', 'userInformation.apartment' => 'nullable|string|max:255', 'userInformation.city' => 'required_without:address_id|string|max:100', 'userInformation.state' => 'required_without:address_id|string|max:100', 'userInformation.zip' => 'required_without:address_id|string|max:20', 'userInformation.phone' => 'required_without:address_id|string|max:20', 'userInformation.country' => 'required_without:address_id|string|max:50', 'userInformation.email' => 'nullable|email|max:255'];
    }
}
