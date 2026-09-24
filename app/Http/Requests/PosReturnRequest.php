<?php

namespace App\Http\Requests;

class PosReturnRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|distinct|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'method' => 'required|in:cash,card,bank,mobile,cheque,gift_card,external,deposit,points,scan',
            'reference' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
        ];
    }
}
