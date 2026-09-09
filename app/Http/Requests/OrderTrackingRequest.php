<?php

namespace App\Http\Requests;

class OrderTrackingRequest extends ApiRequest
{
    public function rules()
    {
        return ['status' => 'required|in:pending,processing,shipped,delivered,cancelled', 'location' => 'nullable|string|max:255'];
    }
}
