<?php

namespace App\Http\Controllers;
use App\Models\PurchaseBackModel;
use Illuminate\Http\Request;

class PurchaseBackController extends Controller
{
    //
    //create
    public function add_purchase_back(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'product' => 'required|integer|exists:t_products,id', // Ensure product ID exists in `t_products`
                'quantity' => 'required|integer|min:1', // Must be at least 1
                'date' => 'required|date', // Ensures proper date format
                'log_user' => 'required|string|max:255', // Limits max characters
            ]);

            // Insert data into `t_purchase_back` table
            $register_purchase_back = PurchaseBackModel::create([
                'product' => $request->input('product'),
                'company_id' => Auth::user()->company_id,
                'quantity' => $request->input('quantity'),
                'date' => $request->input('date'),
                'log_user' => $request->input('log_user'),
            ]);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Product-back registered successfully!',
                'data' => $register_purchase_back->makeHidden(['id', 'created_at', 'updated_at']) // Hide unwanted fields
            ], 200);

        } catch (\Exception $e) {
            // Handle errors
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function fetch_purchase_back()
    {
        try {
            // Fetch purchase-back records filtered by company_id
            $records = PurchaseBackModel::select('id', 'product', 'quantity', 'date', 'log_user')
                ->where('company_id', Auth::user()->company_id)
                ->get();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Purchase-back records fetched successfully!',
                'data' => $records
            ], 200);

        } catch (\Exception $e) {
            // Handle any errors and return a 500 response
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }


}
