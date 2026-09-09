<?php

namespace App\Http\Controllers\Admin\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Repository\Services\Admin\Category\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    private $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('perPage', 10), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $categories = $this->categoryService->getCategories($perPage, $page, $request->query('search', ''));

        return $this->respond(true, 'Categories fetched successfully', $categories);
    }

    public function store(CategoryRequest $request)
    {
        return $this->respond(true, 'Category created successfully', $this->categoryService->create($request->validated()), 201);
    }

    public function show($id)
    {
        $category = $this->categoryService->find($id);
        if (!$category) return $this->respond(false, 'Category not found', [], 404);

        return $this->respond(true, 'Category fetched successfully', $category);
    }

    public function update($id, CategoryRequest $request)
    {
        if (!$this->categoryService->find($id)) return $this->respond(false, 'Category not found', [], 404);
        return $this->respond(true, 'Category updated successfully', $this->categoryService->update($id, $request->validated()));
    }

    public function destroy($id)
    {
        if (!$this->categoryService->delete($id)) return $this->respond(false, 'Category not found', [], 404);

        return $this->respond(true, 'Category deleted successfully', []);
    }

    private function validateCategory(Request $request, $id = null)
    {
        $rules = [
            'name' => 'required|string|max:100|unique:categories,name' . ($id ? ',' . $id : ''),
            'description' => 'nullable|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);

        return $validator->validated();
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
