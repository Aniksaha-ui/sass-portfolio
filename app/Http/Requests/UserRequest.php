<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UserRequest extends ApiRequest
{
    public function rules()
    {
        $updating = $this->route('id') !== null;
        return ['name' => ($updating ? 'sometimes' : 'required').'|string|max:255', 'email' => [$updating ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('id'))], 'role' => ($updating ? 'sometimes' : 'required').'|string|max:100', 'password' => ($updating ? 'nullable' : 'required').'|string|min:8|max:255', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120'];
    }
}
