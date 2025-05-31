<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FabricationModel;
use App\Models\FabricationProductsModel;
use Auth;

class FabricationController extends Controller
{
    //
    //create
    public function add_fabrication(Request $request)
    {
        $request->validate([
            'fb_date'                  => 'required|date',
            'vandor_id'                => 'nullable|integer|exists:t_vendors,id', // Can be null
            'invoice_no'               => 'nullable|string|max:255',
            'remarks'                  => 'nullable|string',
            'fb_amount'                => 'nullable|numeric',
            // Products validation
            'products'                 => 'required|array|min:1',
            'products.*.product_id'    => 'required|integer|exists:t_products,id',
            'products.*.quantity'      => 'required|numeric|min:0',
            'products.*.rate'          => 'required|numeric|min:0',
            'products.*.amount'        => 'required|numeric|min:0',
            'products.*.godown_id'     => 'required|integer|exists:t_godown,id',
            'products.*.remarks'       => 'nullable|string',
            'products.*.type'          => 'required|in:raw,finished',
            'products.*.wastage'       => 'nullable|numeric|min:0',
        ]);

        try {
            \DB::beginTransaction();

            $fabrication = FabricationModel::create([
                'company_id'   => Auth::user()->company_id,
                'vandor_id'    => $request->input('vandor_id'),
                'fb_date'      => $request->input('fb_date'),
                'invoice_no'   => $request->input('invoice_no'),
                'remarks'      => $request->input('remarks'),
                'fb_amount'    => $request->input('fb_amount')
            ]);

            foreach ($request->input('products') as $product) {
                FabricationProductsModel::create([
                    'company_id'  => Auth::user()->company_id,
                    'fb_id'       => $fabrication->id,
                    'product_id'  => $product['product_id'],
                    'quantity'    => $product['quantity'],
                    'rate'        => $product['rate'],
                    'amount'      => $product['amount'],
                    'godown_id'   => $product['godown_id'],
                    'remarks'     => $product['remarks'] ?? null,
                    'type'        => $product['type'],
                    'wastage'     => $product['wastage'] ?? null
                ]);
            }

            \DB::commit();

            return response()->json([
                'code'    => 201,
                'success' => true,
                'message' => 'Fabrication registered successfully!',
                'data'    => $fabrication
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to register Fabrication record',
                'error'   => $e->getMessage()
            ], 500);
        }
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
        ? response()->json(['code' => 201,'success' => true, 'message' => 'Fabrication updated successfully!', 'data' => $update_fabrication], 201)
        : response()->json(['code' => 204,'success' => false, 'message' => 'No changes detected'], 204);
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
        ? response()->json(['code' => 204,'success' => true, 'message' => 'Delete Fabrication successfully!'], 204)
        : response()->json(['code' => 400,'success' => false, 'message' => 'Sorry, Fabrication not found'], 400);
    }
}
