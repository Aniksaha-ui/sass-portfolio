<?php

namespace App\Http\Requests;

class RequisitionRequest extends ApiRequest
{
    public function rules()
    {
        return ['requested_by' => 'required|string|max:255', 'department' => 'nullable|string|max:100', 'priority' => 'nullable|in:low,normal,high,urgent', 'required_by' => 'nullable|date', 'supplier_name' => 'nullable|string|max:255', 'reference_no' => 'nullable|string|max:100', 'notes' => 'nullable|string', 'items' => 'required|array|min:1', 'items.*.product_id' => 'required|integer|distinct|exists:products,id', 'items.*.quantity' => 'required|integer|min:1', 'items.*.unit_cost' => 'nullable|numeric|min:0'];
    }
}
