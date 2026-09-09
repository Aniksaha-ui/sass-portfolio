<?php

namespace App\Http\Requests;

class CompanyAccountRequest extends ApiRequest
{
    public function rules()
    {
        return ['account_name' => 'required|string|max:191', 'account_number' => 'required|string|max:191', 'amount' => 'required|numeric|min:0', 'type' => 'required|string|max:191'];
    }
}
