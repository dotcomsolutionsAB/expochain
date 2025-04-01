<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FabricationModel;
use Auth;

class FabricationController extends Controller
{
    //
    //create
    public function add_fabrication(Request $request)
    {
        $request->validate([
            'fabrication_date' => 'required|date',
            'product_id' => 'required|integer|exists:t_products,id',
            'product_name' => 'required|string|exists:t_products,name',
            'type' => 'required|in:wastage,sample',
            'quantity' => 'required|integer',
            'godown' => 'required|integer',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'log_user' => 'required|string'
        ]);

        $register_fabrication = FabricationModel::create([
            'fabrication_date' => $request->input('fabrication_date'),
            'company_id' => Auth::user()->company_id,
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'type' => $request->input('type'),
            'quantity' => $request->input('quantity'),
            'godown' => $request->input('godown'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'log_user' => $request->input('log_user')
        ]);
        
        unset($register_fabrication['id'], $register_fabrication['created_at'], $register_fabrication['updated_at']);

        return isset($register_fabrication) && $register_fabrication !== null
        ? response()->json(['code' => 201,'success' => true, 'Fabrication registered successfully!', 'data' => $register_fabrication], 201)
        : response()->json(['code' => 400,'success' => false, 'Failed to register Fabrication record'], 400);
    }

    public function view_fabrication(Request $request)
    {
        // Get filter inputs
        $fabricationDate = $request->input('fabrication_date');
        $productId = $request->input('product_id');
        $productName = $request->input('product_name');
        $type = $request->input('type');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = FabricationModel::select('id', 'fabrication_date', 'product_id', 'product_name', 'type', 'quantity', 'godown', 'rate', 'amount', 'description', 'log_user')
            ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($fabricationDate) {
            $query->whereDate('fabrication_date', $fabricationDate);
        }
        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($productName) {
            $query->where('product_name', 'LIKE', '%' . $productName . '%');
        }
        if ($type) {
            $query->where('type', $type);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_fabrication = $query->get();

        // Return response
        return $get_fabrication->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fabrication data fetched successfully!',
                'data' => $get_fabrication,
                'count' => $get_fabrication->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Fabrication data found!',
            ], 404);
    }

    // update
    public function edit_fabrication(Request $request, $id)
    {
        $request->validate([
            'fabrication_date' => 'required|date',
            'product_id' => 'required|integer|exists:t_products,id',
            'product_name' => 'required|string|exists:t_products,name',
            'type' => 'required|in:wastage,sample',
            'quantity' => 'required|integer',
            'godown' => 'required|integer',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
            'log_user' => 'required|string'
        ]);

        $update_fabrication = FabricationModel::where('id', $id)
        ->update([
            'fabrication_date' => $request->input('fabrication_date'),
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'type' => $request->input('type'),
            'quantity' => $request->input('quantity'),
            'godown' => $request->input('godown'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
            'log_user' => $request->input('log_user')
        ]);
        
        return $update_fabrication
        ? response()->json(['code' => 201,'success' => true, 'Fabrication updated successfully!', 'data' => $update_fabrication], 201)
        : response()->json(['code' => 204,'success' => false, 'No changes detected'], 204);
    }

    // delete
    public function delete_fabrication($id)
    {
        // Delete the fabrication
        $delete_fabrication = FabricationModel::where('id', $id)
                                                ->where('company_id', Auth::user()->company_id)
                                                ->delete();

        // Return success response if deletion was successful
        return $delete_fabrication
        ? response()->json(['code' => 204,'success' => true,'message' => 'Delete Fabrication successfully!'], 204)
        : response()->json(['code' => 400,'success' => false,'message' => 'Sorry, Fabrication not found'], 400);
    }
}
