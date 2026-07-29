<?php

namespace App\Http\Controllers\Admin\SectionProduct;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\SectionProduct\SectionProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SectionProductController extends Controller
{
    public function __construct(private SectionProductService $sectionProducts) {}

    public function index(Request $request)
    {
        return $this->respond(true, 'Section products fetched successfully', $this->sectionProducts->paginate(min(max((int) $request->query('perPage', 10), 1), 100), max((int) $request->query('page', 1), 1), (string) $request->query('search', '')));
    }

    public function options()
    {
        return $this->respond(true, 'Section product options fetched successfully', $this->sectionProducts->options());
    }

    public function show(int $id)
    {
        return $this->found($this->sectionProducts->find($id));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Product added to section successfully', $this->sectionProducts->create($data), 201);
    }

    public function update(int $id, Request $request)
    {
        if (! $this->sectionProducts->find($id)) {
            return $this->respond(false, 'Section product not found', [], 404);
        } $data = $this->validated($request, $id);

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Section product updated successfully', $this->sectionProducts->update($id, $data));
    }

    public function destroy(int $id)
    {
        return $this->sectionProducts->delete($id) ? $this->respond(true, 'Product removed from section successfully', []) : $this->respond(false, 'Section product not found', [], 404);
    }

    private function validated(Request $request, ?int $id = null)
    {
        $unique = 'unique:section_products,product_id'.($id ? ','.$id : '').',id,section_id,'.$request->input('section_id');
        $validator = Validator::make($request->all(), ['section_id' => 'required|integer|exists:sections,id', 'product_id' => ['required', 'integer', 'exists:products,id', $unique], 'bundle_id' => 'nullable|integer|exists:product_bundles,id', 'display_order' => 'nullable|integer|min:1']);
        if ($validator->fails()) {
            return $this->respond(false, 'Validation error', $validator->errors(), 422);
        } $data = $validator->validated();
        $data['bundle_id'] = $data['bundle_id'] ?? null;
        $data['display_order'] = $data['display_order'] ?? 1;

        return $data;
    }

    private function found($record)
    {
        return $record ? $this->respond(true, 'Section product fetched successfully', $record) : $this->respond(false, 'Section product not found', [], 404);
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
