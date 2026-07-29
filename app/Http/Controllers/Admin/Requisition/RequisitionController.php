<?php

namespace App\Http\Controllers\Admin\Requisition;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Requisition\RequisitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RequisitionController extends Controller
{
    public function __construct(private RequisitionService $service) {}
    public function options()
    {
        return $this->respond(true, 'Products fetched successfully', $this->service->options());
    }
    public function index(Request $request)
    {
        return $this->respond(true, 'Requisitions fetched successfully', $this->service->requisitions($this->perPage($request), $this->page($request), $request->query('search', '')));
    }
    public function store(Request $request)
    {
        $data = $this->validateRequisition($request);
        return $data instanceof \Illuminate\Http\JsonResponse ? $data : $this->respond(true, 'Requisition created successfully', $this->service->create($data), 201);
    }
    public function accept($id, Request $request)
    {
        $record = $this->service->accept($id, $request->user()?->id);
        return $record ? $this->respond(true, 'Requisition accepted and procurement created.', $record) : $this->respond(false, 'Only a pending requisition can be accepted.', [], 422);
    }
    public function procurements(Request $request)
    {
        return $this->respond(true, 'Procurements fetched successfully', $this->service->procurements($this->perPage($request), $this->page($request), $request->query('search', '')));
    }
    public function receive($id, Request $request)
    {
        return $this->receiveStock((int) $id, $request);
    }
    public function storeStockReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), ['procurement_id' => 'required|integer|exists:procurements,id']);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);
        return $this->receiveStock((int) $validator->validated()['procurement_id'], $request);
    }
    public function markOnHand($id, Request $request)
    {
        $validator = Validator::make($request->all(), ['warehouse_location' => 'required|string|max:100', 'payments' => 'required|array|min:1', 'payments.*.company_account_id' => 'required|integer|distinct|exists:company_accounts,id', 'payments.*.amount' => 'required|numeric|min:0.01']);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);
        try {
            $data = $validator->validated();
            $record = $this->service->markOnHand($id, $data['warehouse_location'], $data['payments'], $request->user()?->id, $request->ip());
            return $record ? $this->respond(true, 'Procurement is on hand, inventory updated, and payment recorded.', $record) : $this->respond(false, 'Procurement is not available to mark on hand.', [], 422);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage(), [], 422);
        }
    }
    public function stocks(Request $request)
    {
        return $this->respond(true, 'Stock receipts fetched successfully', $this->service->stocks($this->perPage($request), $this->page($request), $request->query('search', ''), $request->query('procurement_id'), $request->query('product_id'), $request->query('warehouse_location')));
    }
    public function showStockReceipt($id)
    {
        $receipt = $this->service->stockReceipt((int) $id);
        return $receipt ? $this->respond(true, 'Stock receipt fetched successfully', $receipt) : $this->respond(false, 'Stock receipt not found', [], 404);
    }
    public function productStockOptions()
    {
        return $this->respond(true, 'Stock options fetched successfully', $this->service->stockOptions());
    }
    public function productStocks(Request $request)
    {
        return $this->respond(true, 'Current stock fetched successfully', $this->service->productStocks($this->perPage($request), $this->page($request), $request->query('search', '')));
    }
    public function inventoryAdjustments(Request $request)
    {
        return $this->respond(true, 'Inventory adjustments fetched successfully', $this->service->inventoryAdjustments($this->perPage($request), $this->page($request), $request->query('search', '')));
    }
    public function showInventoryAdjustment($id)
    {
        $adjustment = $this->service->inventoryAdjustment((int) $id);
        return $adjustment ? $this->respond(true, 'Inventory adjustment fetched successfully', $adjustment) : $this->respond(false, 'Inventory adjustment not found', [], 404);
    }
    public function saveProductStock(Request $request)
    {
        $validator = Validator::make($request->all(), ['product_id' => 'required|integer|exists:products,id', 'warehouse_location' => 'required|string|max:100', 'stock_quantity' => 'required|integer|min:0', 'reason' => 'nullable|string|max:255']);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);
        return $this->respond(true, 'Stock saved successfully', $this->service->saveProductStock($validator->validated(), $request->user()?->id));
    }
    public function adjustProductStock($id, Request $request)
    {
        $validator = Validator::make($request->all(), ['stock_quantity' => 'required|integer|min:0', 'reason' => 'nullable|string|max:255']);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);
        $record = $this->service->adjustProductStock($id, $validator->validated(), $request->user()?->id);
        return $record ? $this->respond(true, 'Stock updated successfully', $record) : $this->respond(false, 'Stock record not found', [], 404);
    }
    private function validateRequisition(Request $request)
    {
        $validator = Validator::make($request->all(), ['requested_by' => 'required|string|max:255', 'department' => 'nullable|string|max:100', 'priority' => 'nullable|in:low,normal,high,urgent', 'required_by' => 'nullable|date', 'supplier_name' => 'nullable|string|max:255', 'reference_no' => 'nullable|string|max:100', 'notes' => 'nullable|string', 'items' => 'required|array|min:1', 'items.*.product_id' => 'required|integer|distinct|exists:products,id', 'items.*.quantity' => 'required|integer|min:1', 'items.*.unit_cost' => 'nullable|numeric|min:0']);
        return $validator->fails() ? $this->respond(false, 'Validation error', $validator->errors(), 422) : $validator->validated();
    }
    private function receiveStock(int $procurementId, Request $request)
    {
        $validator = Validator::make($request->all(), ['warehouse_location' => 'required|string|max:100', 'items' => 'required|array|min:1', 'items.*.requisition_product_id' => 'required|integer|distinct', 'items.*.quantity_received' => 'required|integer|min:1']);
        if ($validator->fails()) return $this->respond(false, 'Validation error', $validator->errors(), 422);
        try {
            $data = $validator->validated();
            $record = $this->service->receive($procurementId, $data['items'], $data['warehouse_location'], $request->user()?->id);
            return $record ? $this->respond(true, 'Stock receipt created and inventory updated successfully.', $record, 201) : $this->respond(false, 'Procurement is not available for receiving.', [], 422);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage(), [], 422);
        }
    }
    private function perPage(Request $request)
    {
        return min(max((int) $request->query('perPage', 10), 1), 100);
    }
    private function page(Request $request)
    {
        return max((int) $request->query('page', 1), 1);
    }
    private function respond($status, $message, $data, $code = 200)
    {
        return response()->json(['isExecuted' => $status, 'message' => $message, 'data' => $data], $code);
    }
}
