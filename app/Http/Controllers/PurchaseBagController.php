<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBag;
use App\Models\ProductsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseBagController extends Controller
{
    // GET /api/purchase-bags
    public function index(Request $request)
    {
        $rows = PurchaseBag::with(['productRelation', 'groupRelation', 'categoryRelation', 'subCategoryRelation'])
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->get();  // No pagination, fetch all rows

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'OK',
            'data'    => $rows,
            'total_records' => $rows->count(),  // Total count of records
            'count'   => $rows->count(),       // Count of items in the response
        ], 200);
    }

    // GET /api/purchase-bags/{id}
    public function show($id)
    {
        $row = PurchaseBag::with(['productRelation', 'groupRelation', 'categoryRelation', 'subCategoryRelation'])
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json([
                'code'=>404,
                'success'=>false,
                'message'=>'Not found',
                'data' => [],  // Return empty array for data
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'OK',
            'data'=>$row,
            'total_records'=>1,  // Single item
            'count'=>1
        ], 200);
    }

    // POST /api/purchase-bags
    public function store(Request $request)
    {
        $request->validate([
            'product_id'    => 'required|exists:t_products,id',  // Product must exist
            'quantity'      => 'required|numeric',               // Quantity is required
        ]);

        // Fetch the product to get group, category, and sub_category
        $product = ProductsModel::find($request->product_id);

        if (!$product) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Product not found',
                'data' => [],
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        // Create a new PurchaseBag entry
        $row = new PurchaseBag([
            'product_id' => $request->product_id,
            'quantity'   => $request->quantity,
            'group'      => $product->group,         // Set group based on the product
            'category'   => $product->category,      // Set category based on the product
            'sub_category' => $product->sub_category, // Set sub_category based on the product
        ]);

        if ($user = Auth::user()) {
            $row->company_id = $user->company_id;
            $row->log_user = $user->name ?? $user->username ?? (string)$user->id;
        }

        $row->save();

        return response()->json([
            'code'=>201,
            'success'=>true,
            'message'=>'Created',
            'data'=>$row,
            'total_records'=>1,
            'count'=>1
        ], 201);
    }

    // PUT/PATCH /api/purchase-bags/{id}
    public function update(Request $request, $id)
    {
        $row = PurchaseBag::with(['productRelation', 'groupRelation', 'categoryRelation', 'subCategoryRelation'])
            ->when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json([
                'code'=>404,
                'success'=>false,
                'message'=>'Not found',
                'data' => [],  // Return empty array for data
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        $request->validate([
            'product_id'    => 'sometimes|required|exists:t_products,id',  // Product must exist
            'quantity'      => 'sometimes|nullable|numeric',
            'group'         => 'sometimes|nullable|exists:t_groups,id',
            'category'      => 'sometimes|nullable|exists:t_categories,id',
            'sub_category'  => 'sometimes|nullable|exists:t_sub_categories,id',
            'temp'          => 'sometimes|nullable|integer|min:0|max:1',
            'log_user'      => 'sometimes|nullable|string|max:191',
        ]);

        // Update product details if necessary (based on incoming data)
        if ($request->has('product_id')) {
            $product = ProductsModel::find($request->product_id);
            if ($product) {
                $row->group = $product->group;
                $row->category = $product->category;
                $row->sub_category = $product->sub_category;
            }
        }

        $row->fill($request->only([
            'product_id', 'quantity', 'group', 'category', 'sub_category', 'temp', 'log_user'
        ]));
        $row->save();

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'Updated',
            'data'=>$row,
            'total_records'=>1,
            'count'=>1
        ], 200);
    }

    // DELETE /api/purchase-bags/{id}
    public function destroy($id)
    {
        $row = PurchaseBag::when(Auth::user()?->company_id, fn($qb, $cid) => $qb->where('company_id', $cid))
            ->find($id);

        if (! $row) {
            return response()->json([
                'code'=>404,
                'success'=>false,
                'message'=>'Not found',
                'data' => [],  // Return empty array for data
                'total_records' => 0,
                'count' => 0
            ], 404);
        }

        $row->delete();

        return response()->json([
            'code'=>200,
            'success'=>true,
            'message'=>'Deleted',
            'data'=>[],  // Empty array for deleted item
            'total_records'=>0,
            'count'=>0
        ], 200);
    }
}

?>