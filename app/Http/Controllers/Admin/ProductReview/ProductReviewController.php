<?php

namespace App\Http\Controllers\Admin\ProductReview;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductReviewRequest;
use App\Repository\Services\Admin\ProductReview\ProductReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function index(Request $request)
    {
        return $this->respond(true, 'Product reviews fetched successfully', $this->reviews->paginate(min(max((int) $request->query('perPage', 10), 1), 100), max((int) $request->query('page', 1), 1), (string) $request->query('search', '')));
    }

    public function options()
    {
        return $this->respond(true, 'Product review options fetched successfully', $this->reviews->options());
    }

    public function show(int $id)
    {
        return $this->found($this->reviews->find($id));
    }

    public function store(ProductReviewRequest $request)
    {
        $data = $request->validated();

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Product review created successfully', $this->reviews->create($data), 201);
    }

    public function update(int $id, ProductReviewRequest $request)
    {
        if (! $this->reviews->find($id)) {
            return $this->respond(false, 'Product review not found', [], 404);
        } $data = $request->validated();

        return $data instanceof JsonResponse ? $data : $this->respond(true, 'Product review updated successfully', $this->reviews->update($id, $data));
    }

    public function destroy(int $id)
    {
        return $this->reviews->delete($id) ? $this->respond(true, 'Product review deleted successfully', []) : $this->respond(false, 'Product review not found', [], 404);
    }

    private function validated(Request $request)
    {
        $validator = Validator::make($request->all(), ['user_id' => 'required|integer|exists:users,id', 'product_id' => 'required|integer|exists:products,id', 'rating' => 'required|integer|between:1,5', 'review' => 'required|string|max:5000']);

        return $validator->fails() ? $this->respond(false, 'Validation error', $validator->errors(), 422) : $validator->validated();
    }

    private function found($review)
    {
        return $review ? $this->respond(true, 'Product review fetched successfully', $review) : $this->respond(false, 'Product review not found', [], 404);
    }

    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
