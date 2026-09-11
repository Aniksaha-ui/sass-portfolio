<?php

namespace App\Http\Requests;

use App\Support\OperationsConstants;
use Illuminate\Validation\Rule;

class ProcurementReceiveRequest extends ApiRequest
{
    public function rules()
    {
        return ['warehouse_location' => ['required', 'string', Rule::in(OperationsConstants::WAREHOUSES)], 'items' => 'required|array|min:1', 'items.*.requisition_product_id' => 'required|integer|distinct', 'items.*.quantity_received' => 'required|integer|min:1'];
    }
}
