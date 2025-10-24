<?php

// app/Http/Controllers/PurchaseBagController.php
namespace App\Http\Controllers;

use App\Models\PurchaseBag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseBagController extends Controller
{
    // GET /api/purchase-bags
    public function index(Request $request)
    {
        $perPage = (int) ($request->integer('per_page') ?: 10);
        $page    = (int) ($request->integer('page') ?: 1);

        $q = PurchaseBag::query()
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->when($request->filled('product'),      fn($qb) => $qb->where('product', 'like', '%'.$request->product.'%'))
            ->when($request->filled('group'),        fn($qb) => $qb->where('group', $request->group))
            ->when($request->filled('category'),     fn($qb) => $qb->where('category', (int)$request->category))
            ->when($request->filled('sub_category'), fn($qb) => $qb->where('sub_category', (int)$request->sub_category))
            ->when($request->filled('pb_date_from'), fn($qb) => $qb->whereDate('pb_date', '>=', $request->pb_date_from))
            ->when($request->filled('pb_date_to'),   fn($qb) => $qb->whereDate('pb_date', '<=', $request->pb_date_to))
            ->when($request->filled('temp'),         fn($qb) => $qb->where('temp', (int)$request->temp))
            ->orderByDesc('id');

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'code'    => 200,
            'status'  => true,
            'message' => 'OK',
            'data'    => $paginator->items(),
            // top-level totals per your convention
            'total_records'   => $paginator->total(),     // total matching rows
            'count'   => count($paginator->items()), // items in this page
        ], 200);
    }

    // GET /api/purchase-bags/{id}
    public function show($id)
    {
        $row = PurchaseBag::when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json(['code'=>404,'status'=>false,'message'=>'Not found', 'data'=>[]], 404);
        }

        return response()->json([
            'code'=>200,'status'=>true,'message'=>'OK','data'=>$row,
            'total_records'=>1,'count'=>1,'has_next_page'=>false,'has_prev_page'=>false
        ], 200);
    }

    // POST /api/purchase-bags
    public function store(Request $request)
    {
        $request->validate([
            'product'       => 'required|string|max:255',
            'group'         => 'nullable|string|max:100',
            'category'      => 'nullable|integer',
            'sub_category'  => 'nullable|integer',
            'quantity'      => 'nullable|numeric',
            'pb_date'       => 'nullable|date',
            'temp'          => 'nullable|integer|min:0|max:1',
            'log_user'      => 'nullable|string|max:191',
        ]);

        $row = new PurchaseBag($request->only([
            'product','group','category','sub_category','quantity','pb_date','temp','log_user'
        ]));

        if ($user = Auth::user()) {
            $row->company_id = $user->company_id;
            if (! $row->log_user) $row->log_user = $user->name ?? $user->username ?? (string)$user->id;
        }

        $row->save();

        return response()->json([
            'code'=>201,'status'=>true,'message'=>'Created','data'=>$row,
            'total_records'=>1,'count'=>1
        ], 201);
    }

    // PUT/PATCH /api/purchase-bags/{id}
    public function update(Request $request, $id)
    {
        $row = PurchaseBag::when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json(['code'=>404,'status'=>false,'message'=>'Not found', 'data'=>[]], 404);
        }

        $request->validate([
            'product'       => 'sometimes|required|string|max:255',
            'group'         => 'sometimes|nullable|string|max:100',
            'category'      => 'sometimes|nullable|integer',
            'sub_category'  => 'sometimes|nullable|integer',
            'quantity'      => 'sometimes|nullable|numeric',
            'pb_date'       => 'sometimes|nullable|date',
            'temp'          => 'sometimes|nullable|integer|min:0|max:1',
            'log_user'      => 'sometimes|nullable|string|max:191',
        ]);

        $row->fill($request->only([
            'product','group','category','sub_category','quantity','pb_date','temp','log_user'
        ]));
        $row->save();

        return response()->json([
            'code'=>200,'status'=>true,'message'=>'Updated','data'=>$row,
            'total_records'=>1,'count'=>1
        ], 200);
    }

    // DELETE /api/purchase-bags/{id}
    public function destroy($id)
    {
        $row = PurchaseBag::when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json(['code'=>404,'status'=>false,'message'=>'Not found'], 404);
        }

        $row->delete();

        return response()->json([
            'code'=>200,'status'=>true,'message'=>'Deleted',
            'data'=>[],'total_records'=>0,'count'=>0
        ], 200);
    }
}

?>