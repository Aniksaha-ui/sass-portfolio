<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\ReturnManagement\ReturnDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    public function __construct(private ReturnDetailService $details) {}

    public function index(Request $request)
    {
        $search = (string) $request->query('search', '');
        $rows = DB::table('refunds as rf')->join('returns as r', 'r.id', '=', 'rf.return_id')->join('products as p', 'p.id', '=', 'r.product_id')->join('users as u', 'u.id', '=', 'rf.user_id')->where(fn ($query) => $query->where('rf.refund_reference', 'like', "%{$search}%")->orWhere('p.name', 'like', "%{$search}%")->orWhere('u.name', 'like', "%{$search}%"))->orderByDesc('rf.id')->paginate(min(max((int) $request->query('perPage', 10), 1), 100), ['rf.*', 'r.reason', 'p.name as product_name', 'u.name as customer_name', 'u.email as customer_email'], 'page', max((int) $request->query('page', 1), 1));

        return response()->json(['isExecuted' => true, 'message' => 'Refunds fetched successfully', 'data' => $rows]);
    }

    public function show(int $id)
    {
        $detail = $this->details->findRefund($id);

        return $detail
            ? response()->json(['isExecuted' => true, 'message' => 'Refund details fetched successfully', 'data' => $detail])
            : response()->json(['isExecuted' => false, 'message' => 'Refund not found', 'data' => []], 404);
    }
}
