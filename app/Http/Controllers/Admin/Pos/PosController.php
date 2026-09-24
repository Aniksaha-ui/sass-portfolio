<?php

namespace App\Http\Controllers\Admin\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosHoldRequest;
use App\Http\Requests\PosPaymentRequest;
use App\Http\Requests\PosReturnRequest;
use App\Http\Requests\PosSaleRequest;
use App\Repository\Services\Admin\Pos\PosService;
use App\Repository\Services\Admin\Pos\PosReportService;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(private PosService $pos, private PosReportService $reports) {}

    public function catalog(Request $request)
    {
        return $this->ok($this->pos->catalog((string) $request->query('search', ''), ((int) $request->query('category_id', 0)) ?: null, (bool) $request->query('featured', false)));
    }

    public function store(PosSaleRequest $request)
    {
        try { return $this->ok($this->pos->checkout($request->validated()), 'POS order placed successfully.', 201); }
        catch (\InvalidArgumentException $e) { return $this->error($e); }
    }

    public function order(int $id)
    {
        $order = $this->pos->order($id);
        return $order ? $this->ok($order) : response()->json(['isExecuted' => false, 'message' => 'POS order not found.'], 404);
    }

    public function orders(Request $request)
    {
        return $this->ok($this->pos->orders((string) $request->query('search', '')));
    }

    public function today()
    {
        return $this->ok($this->reports->today());
    }

    public function holds()
    {
        return $this->ok($this->pos->holds());
    }

    public function hold(PosHoldRequest $request)
    {
        return $this->ok($this->pos->hold($request->validated()), 'Transaction held.', 201);
    }

    public function releaseHold(int $id)
    {
        return $this->pos->releaseHold($id) ? $this->ok([], 'Held transaction removed.') : response()->json(['isExecuted' => false, 'message' => 'Held transaction not found.'], 404);
    }

    public function collectPayment(int $id, PosPaymentRequest $request)
    {
        try { return $this->ok($this->pos->collectPayment($id, $request->validated()), 'Payment collected successfully.'); }
        catch (\InvalidArgumentException $e) { return $this->error($e); }
    }

    public function returnItems(int $id, PosReturnRequest $request)
    {
        try { return $this->ok($this->pos->returnItems($id, $request->validated()), 'Items returned successfully.'); }
        catch (\InvalidArgumentException $e) { return $this->error($e); }
    }

    private function ok($data, string $message = 'POS data fetched successfully.', int $status = 200)
    {
        return response()->json(['isExecuted' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function error(\InvalidArgumentException $e)
    {
        return response()->json(['isExecuted' => false, 'message' => $e->getMessage(), 'data' => []], 422);
    }
}
