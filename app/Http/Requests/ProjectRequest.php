<?php

namespace App\Http\Requests;

class ProjectRequest extends ApiRequest
{
    public function rules()
    {
        $required = $this->route('id') === null ? 'required' : 'sometimes';
        return ['project_name' => $required.'|string|max:255', 'description' => $required.'|string|max:255', 'website_link' => $required.'|url|max:255', 'frontend_tech' => $required.'|string|max:255', 'backend_tech' => $required.'|string|max:255', 'database' => $required.'|string|max:255', 'github_link' => $required.'|url|max:255', 'image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:5120', 'no_of_developers' => $required.'|integer|min:1', 'developers_name' => $required.'|string|max:255', 'start_date' => 'sometimes|nullable|date', 'end_date' => 'sometimes|nullable|date|after_or_equal:start_date', 'isPublished' => 'sometimes|nullable|boolean', 'published_at' => 'sometimes|nullable|date'];
    }
}
