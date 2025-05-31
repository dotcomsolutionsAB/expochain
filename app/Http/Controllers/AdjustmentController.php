<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\AdjustmentModel;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    //
    public function store(Request $request)
    {
        $validated = $request->validate([
            'adjustment_date'  => 'required|date',
            'product_id'       => 'required|integer|exists:t_products,id', // Adjust table name as needed
            'quantity'         => 'required|integer',
            'godown_id'        => 'required|integer|exists:t_godown,id', // Adjust table name as needed
            'type'             => 'required|in:loss,extra',
        ]);

        try {
            $adjustment = AdjustmentModel::create([
                'company_id'      => Auth::user()->company_id,
                'company_id'      => $validated['company_id'],
                'adjustment_date' => $validated['adjustment_date'],
                'product_id'      => $validated['product_id'],
                'quantity'        => $validated['quantity'],
                'godown_id'       => $validated['godown_id'],
                'type'            => $validated['type'],
            ]);

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Adjustment record created successfully!',
                'data'    => $adjustment
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to create adjustment record.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
