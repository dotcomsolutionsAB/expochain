<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\AdjustmentModel;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    //create
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
                'data'    => $adjustment->makeHidden(['created_at', 'updated_at'])
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

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            $company_id = Auth::user()->company_id;
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $productName = $request->input('product_name');

            // Eager load product and godown
            $query = AdjustmentModel::with([
                'productRelation:id,name,hsn,unit',
                'godownRelation:id,name'
            ])->where('company_id', $company_id);

            // Fetch single by id
            if ($id) {
                $adjustment = $query->where('id', $id)->first();

                if (!$adjustment) {
                    return response()->json([
                        'code' => 404,
                        'success' => false,
                        'message' => 'Adjustment record not found.'
                    ], 404);
                }

                // Replace godown_id with godown_name
                $adjustment->godown_name = $adjustment->godownRelation->name ?? null;
                unset($adjustment->godown_id, $adjustment->godownRelation);

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Adjustment record fetched successfully.',
                    'data' => $adjustment
                ], 200);
            }

            // Filter by product name if provided
            if ($productName) {
                $query->whereHas('productRelation', function($q) use ($productName) {
                    $q->where('name', 'like', '%' . $productName . '%');
                });
            }

            // Paginate
            $total = $query->count();
            $adjustments = $query->offset($offset)->limit($limit)->orderBy('adjustment_date', 'desc')->get();

            // Map result to replace godown_id with godown_name
            $adjustments->transform(function($adj) {
                $adj->godown_name = $adj->godownRelation->name ?? null;
                unset(
                    $adj->godown_id,
                    $adj->godownRelation,
                    $adj->created_at,   // Remove timestamps
                    $adj->updated_at
                );
                return $adj;
            });

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Adjustment records fetched successfully.',
                'data' => $adjustments,
                'count' => $adjustments->count(),
                'total_records' => $total
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch adjustment records.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // update
    public function update(Request $request, $id)
    {
        try {
            $company_id = Auth::user()->company_id;

            // Find the adjustment record for this company and id
            $adjustment = AdjustmentModel::where('company_id', $company_id)->where('id', $id)->first();

            if (!$adjustment) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Adjustment record not found.'
                ], 404);
            }

            // Validate incoming data (all fields optional for PATCH-like update)
            $validated = $request->validate([
                'adjustment_date'  => 'required|date',
                'product_id'       => 'required|integer|exists:t_products,id',
                'quantity'         => 'required|integer',
                'godown_id'        => 'required|integer|exists:t_godown,id',
                'type'             => 'required|in:loss,extra',
            ]);

            // Only fill provided fields (column-based update)
            $adjustment->fill($validated);
            $adjustment->save();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Adjustment record updated successfully!',
                'data'    => $adjustment->makeHidden(['created_at', 'updated_at'])
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to update adjustment record.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // delete
    public function delete(Request $request, $id)
    {
        try {
            $company_id = Auth::user()->company_id;

            $adjustment = AdjustmentModel::where('company_id', $company_id)->where('id', $id)->first();

            if (!$adjustment) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Adjustment record not found.'
                ], 404);
            }

            $adjustment->delete();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Adjustment record deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to delete adjustment record.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
