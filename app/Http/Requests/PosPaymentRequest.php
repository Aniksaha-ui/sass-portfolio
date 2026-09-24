<?php

namespace App\Http\Requests;

class PosPaymentRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'method' => 'required|in:cash,card,bank,mobile,cheque,gift_card,external,deposit,points,scan',
            'amount' => 'required|numeric|gt:0',
            'reference' => 'nullable|string|max:100',
        ];
    }
}
