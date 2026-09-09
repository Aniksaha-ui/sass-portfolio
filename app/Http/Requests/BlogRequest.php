<?php

namespace App\Http\Requests;

class BlogRequest extends ApiRequest
{
    public function rules()
    {
        $required = $this->route('id') === null ? 'required' : 'sometimes';
        return ['title' => $required.'|string|max:255', 'content' => $required.'|string', 'short_description' => $required.'|string', 'category' => $required.'|string|max:255', 'fa_icon' => $required.'|string|max:255', 'view_count' => 'sometimes|nullable|integer|min:0', 'author_name' => $required.'|string|max:255', 'published_date' => $required.'|date', 'isPublished' => 'sometimes|nullable|boolean'];
    }
}
