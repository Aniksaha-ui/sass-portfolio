<?php

namespace App\Http\Requests;

class ProcurementOnHandRequest extends ApiRequest
{
    public function rules()
    {
        return ['warehouse_location' => 'required|string|max:100', 'payments' => 'required|array|min:1', 'payments.*.company_account_id' => 'required|integer|distinct|exists:company_accounts,id', 'payments.*.amount' => 'required|numeric|min:0.01'];
    }
}
