<?php

namespace App\Http\Requests;

class ProcurementReceiveRequest extends ApiRequest
{
    public function rules()
    {
        return ['warehouse_location' => 'required|string|max:100', 'items' => 'required|array|min:1', 'items.*.requisition_product_id' => 'required|integer|distinct', 'items.*.quantity_received' => 'required|integer|min:1'];
    }
}
