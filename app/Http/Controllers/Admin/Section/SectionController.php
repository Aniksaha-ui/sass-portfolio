<?php

namespace App\Http\Controllers\Admin\Section;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Section\SectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SectionController extends Controller
{
    public function __construct(private SectionService $sections) {}

    public function index(Request $request)
    {
        return $this->respond(true, 'Sections fetched successfully', $this->sections->paginate(min(max((int) $request->query('perPage', 10), 1), 100), max((int) $request->query('page', 1), 1), (string) $request->query('search', '')));
    }

    public function show(int $id)
    {
        return $this->found($this->sections->find($id));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Section created successfully', $this->sections->create($data), 201);
    }

    public function update(int $id, Request $request)
    {
        if (! $this->sections->find($id)) {
            return $this->respond(false, 'Section not found', [], 404);
        } $data = $this->validated($request, $id);

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Section updated successfully', $this->sections->update($id, $data));
    }

    public function destroy(int $id)
    {
        return $this->sections->delete($id) ? $this->respond(true, 'Section deleted successfully', []) : $this->respond(false, 'Section not found', [], 404);
    }

    private function validated(Request $request, ?int $id = null)
    {
        $validator = Validator::make($request->all(), ['name' => 'required|string|max:100|unique:sections,name'.($id ? ','.$id : ''), 'display_order' => 'nullable|integer|min:1', 'is_active' => 'nullable|boolean']);
        if ($validator->fails()) {
            return $this->respond(false, 'Validation error', $validator->errors(), 422);
        } $data = $validator->validated();
        $data['display_order'] = $data['display_order'] ?? 1;
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    private function found($section)
    {
        return $section ? $this->respond(true, 'Section fetched successfully', $section) : $this->respond(false, 'Section not found', [], 404);
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
