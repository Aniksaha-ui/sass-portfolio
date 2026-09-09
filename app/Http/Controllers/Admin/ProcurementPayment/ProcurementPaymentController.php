<?php

namespace App\Http\Controllers\Admin\ProcurementPayment;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcurementPaymentRequest;
use App\Repository\Services\Admin\ProcurementPayment\ProcurementPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProcurementPaymentController extends Controller
{
    public function __construct(private ProcurementPaymentService $payments) {}

    public function index(Request $r)
    {
        return $this->ok('Payments fetched successfully', $this->payments->paginate(min(max((int) $r->query('perPage', 10), 1), 100), max((int) $r->query('page', 1), 1), (string) $r->query('search', '')));
    }

    public function options()
    {
        return $this->ok('Payment options fetched successfully', $this->payments->options());
    }

    public function show($id)
    {
        return $this->found($this->payments->find($id));
    }

    public function store(ProcurementPaymentRequest $r)
    {
        return response()->json(['isExecuted' => true, 'message' => 'Payment created successfully', 'data' => $this->payments->create($r->validated())], 201);
    }

    public function update($id, ProcurementPaymentRequest $r)
    {
        if (! $this->payments->find($id)) {
            return $this->no();
        }
        return $this->ok('Payment updated successfully', $this->payments->update($id, $r->validated()));
    }

    public function destroy($id)
    {
        return $this->payments->delete($id) ? $this->ok('Payment deleted successfully', []) : $this->no();
    }

    private function valid($r, $id = null)
    {
        $v = Validator::make($r->all(), ['procurement_id' => 'required|integer|exists:procurements,id', 'company_account_id' => 'required|integer|exists:company_accounts,id', 'amount' => 'required|numeric|gt:0', 'payment_reference' => 'required|string|max:100|unique:procurement_payments,payment_reference' . ($id ? ',' . $id : ''), 'paid_at' => 'required|date']);

        return $v->fails() ? response()->json(['isExecuted' => false, 'message' => 'Validation error', 'data' => $v->errors()], 422) : $v->validated();
    }

    private function found($x)
    {
        return $x ? $this->ok('Payment fetched successfully', $x) : $this->no();
    }

    private function no()
    {
        return response()->json(['isExecuted' => false, 'message' => 'Payment not found', 'data' => []], 404);
    }

    private function ok($m, $d)
    {
        return response()->json(['isExecuted' => true, 'message' => $m, 'data' => $d]);
    }
}
