<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProcurementPaymentRequest extends ApiRequest
{
    public function rules()
    {
        return ['procurement_id' => 'required|integer|exists:procurements,id', 'company_account_id' => 'required|integer|exists:company_accounts,id', 'amount' => 'required|numeric|gt:0', 'payment_reference' => ['required', 'string', 'max:100', Rule::unique('procurement_payments', 'payment_reference')->ignore($this->route('id'))], 'paid_at' => 'required|date'];
    }
}
