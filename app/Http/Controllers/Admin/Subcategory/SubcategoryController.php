<?php

namespace App\Http\Controllers\Admin\Subcategory;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Subcategory\SubcategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubcategoryController extends Controller
{
    public function __construct(private SubcategoryService $subcategories) {}
    public function index(Request $request) { return $this->respond(true, 'Subcategories fetched successfully', $this->subcategories->paginate(min(max((int) $request->query('perPage', 10), 1), 100), max((int) $request->query('page', 1), 1), (string) $request->query('search', ''))); }
    public function options() { return $this->respond(true, 'Category options fetched successfully', $this->subcategories->options()); }
    public function show(int $id) { return $this->found($this->subcategories->find($id)); }
    public function store(Request $request) { $data = $this->validated($request); return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Subcategory created successfully', $this->subcategories->create($data), 201); }
    public function update(int $id, Request $request) { if (!$this->subcategories->find($id)) return $this->respond(false, 'Subcategory not found', [], 404); $data = $this->validated($request, $id); return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Subcategory updated successfully', $this->subcategories->update($id, $data)); }
    public function destroy(int $id) { return $this->subcategories->delete($id) ? $this->respond(true, 'Subcategory deleted successfully', []) : $this->respond(false, 'Subcategory not found', [], 404); }
    private function validated(Request $request, ?int $id = null) { $validator = Validator::make($request->all(), ['category_id' => 'required|integer|exists:categories,id', 'name' => 'required|string|max:100|unique:subcategories,name' . ($id ? ',' . $id : ''), 'description' => 'nullable|string']); return $validator->fails() ? $this->respond(false, 'Validation error', $validator->errors(), 422) : $validator->validated(); }
    private function found($subcategory) { return $subcategory ? $this->respond(true, 'Subcategory fetched successfully', $subcategory) : $this->respond(false, 'Subcategory not found', [], 404); }
    private function respond($status, $message, $data, $code = 200) { return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code); }
}
