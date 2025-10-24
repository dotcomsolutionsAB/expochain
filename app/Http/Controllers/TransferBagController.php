<?php

namespace App\Http\Controllers;

use App\Models\TransferBag;
use App\Models\ProductsModel;
use App\Models\GodownModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransferBagController extends Controller
{
    // GET /api/transfer-bags
    public function index(Request $request)
    {
        $rows = TransferBag::with(['productRelation', 'godownFromRelation', 'godownToRelation'])
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->get();  // Fetch all rows without pagination

        return response()->json([
            'code'    => 200,
            'status'  => true,
            'message' => 'OK',
            'data'    => $rows,
            'total_records' => $rows->count(),
            'count'   => $rows->count(),
        ], 200);
    }

    // GET /api/transfer-bags/{id}
    public function show($id)
    {
        $row = TransferBag::with(['productRelation', 'godownFromRelation', 'godownToRelation'])
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json([
                'code'=>404,
                'status'=>false,
                'message'=>'Not found',
                'data' => [],  // Empty array for data
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        return response()->json([
            'code'=>200,
            'status'=>true,
            'message'=>'OK',
            'data'=>$row,
            'total_records'=>1,
            'count'=>1
        ], 200);
    }

    // POST /api/transfer-bags
    public function store(Request $request)
    {
        $request->validate([
            'product_id'    => 'required|exists:t_products,id',  // Product must exist
            'quantity'      => 'required|numeric',               // Quantity is required
            'tb_date'       => 'required|date',                  // Transfer date is required
            'godown_from'   => 'required|exists:t_godown,id',      // Godown from must exist
            'godown_to'     => 'required|exists:t_godown,id',      // Godown to must exist
        ]);

        // Create a new TransferBag entry
        $row = new TransferBag($request->only([
            'product_id', 'quantity', 'tb_date', 'godown_from', 'godown_to', 'log_user', 'log_date'
        ]));

        if ($user = Auth::user()) {
            $row->company_id = $user->company_id;
            $row->log_user = $user->name ?? $user->username ?? (string)$user->id;
        }

        $row->save();

        return response()->json([
            'code'=>201,
            'status'=>true,
            'message'=>'Created',
            'data'=>$row,
            'total_records'=>1,
            'count'=>1
        ], 201);
    }

    // PUT/PATCH /api/transfer-bags/{id}
    public function update(Request $request, $id)
    {
        $row = TransferBag::with(['productRelation', 'godownFromRelation', 'godownToRelation'])
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json([
                'code'=>404,
                'status'=>false,
                'message'=>'Not found',
                'data' => [],  // Return empty array for data
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        $request->validate([
            'product_id'    => 'sometimes|required|exists:t_products,id', 
            'quantity'      => 'sometimes|nullable|numeric',
            'tb_date'       => 'sometimes|nullable|date',
            'godown_from'   => 'sometimes|nullable|exists:godown,id',
            'godown_to'     => 'sometimes|nullable|exists:godown,id',
        ]);

        // Update the record
        $row->fill($request->only([
            'product_id', 'quantity', 'tb_date', 'godown_from', 'godown_to', 'log_user', 'log_date'
        ]));
        $row->save();

        return response()->json([
            'code'=>200,
            'status'=>true,
            'message'=>'Updated',
            'data'=>$row,
            'total_records'=>1,
            'count'=>1
        ], 200);
    }

    // DELETE /api/transfer-bags/{id}
    public function destroy($id)
    {
        $row = TransferBag::when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json([
                'code'=>404,
                'status'=>false,
                'message'=>'Not found',
                'data' => [],  // Return empty array for data
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        $row->delete();

        return response()->json([
            'code'=>200,
            'status'=>true,
            'message'=>'Deleted',
            'data'=>[],  // Empty array for deleted item
            'total_records'=>0,
            'count'=>0
        ], 200);
    }
}

?>