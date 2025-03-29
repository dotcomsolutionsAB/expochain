<?php

namespace App\Http\Controllers;
use App\Models\LotModel;
use Illuminate\Support\Facades\Auth;
use App\Models\CounterModel;
use Illuminate\Http\Request;

class LotController extends Controller
{
    //create
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'lr_no' => 'nullable|string|max:255',
                'date' => 'nullable|date',
                'shipping_by' => 'nullable|string|max:255',
                'freight' => 'nullable|numeric',
                'invoice' => 'nullable|string|max:255',
                'receiving_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $lot = LotModel::create([
                'company_id' => Auth::user()->company_id,
                'name' => $request->input('name'),
                'lr_no' => $request->input('lr_no'),
                'date' => $request->input('date'),
                'shipping_by' => $request->input('shipping_by'),
                'freight' => $request->input('freight'),
                'invoice' => $request->input('invoice'),
                'receiving_date' => $request->input('receiving_date'),
            ]);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Lot created successfully.',
                'data' => $lot
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while creating the lot.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // fetch
    public function retrieve(Request $request, $id = null)
    {
        try {
            // Build base query
            $query = LotModel::query();

            // If a filter for lr_no is provided, add a where clause (partial match)
            if ($request->filled('lr_no')) {
                $lrNo = $request->input('lr_no');
                $query->where('lr_no', 'LIKE', '%' . $lrNo . '%');
            }

            // If an ID is provided, fetch that single record
            if (!is_null($id)) {
                $lot = $query->where('id', $id)->first();

                if (!$lot) {
                    return response()->json([
                        'code'    => 404,
                        'success' => false,
                        'message' => 'Lot not found.'
                    ], 404);
                }

                // Transform the invoice field into an array of invoice objects
                $lot->invoices = $this->transformInvoiceColumn($lot->invoice);

                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'Lot fetched successfully.',
                    'data'    => $lot
                ]);
            }

            // Otherwise, fetch all lots (optionally filtered by lr_no)
            $lots = $query->orderBy('id', 'desc')->get();

            // Transform the invoice field for each record
            $lots->each(function ($lot) {
                $lot->invoices = $this->transformInvoiceColumn($lot->invoice);
            });

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Lots fetched successfully.',
                'data'    => $lots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Error fetching lot records.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    // helper 
    private function transformInvoiceColumn($invoiceString)
    {
        if (empty($invoiceString)) {
            return [];
        }
        // Split the string by commas and trim each element
        $invoiceIds = array_filter(array_map('trim', explode(',', $invoiceString)));

        $invoices = [];
        foreach ($invoiceIds as $id) {
            // Lookup the purchase invoice record by id
            $purchaseInvoice = PurchaseInvoiceModel::find($id);
            $invoices[] = [
                'id'   => $id,
                'name' => $purchaseInvoice ? $purchaseInvoice->purchase_invoice_no : 'Unknown'
            ];
        }
        return $invoices;
    }

    // update
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'lr_no' => 'nullable|string|max:255',
                'date' => 'nullable|date',
                'shipping_by' => 'nullable|string|max:255',
                'freight' => 'nullable|numeric',
                'invoice' => 'nullable|string|max:255',
                'receiving_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $lot = LotModel::find($id);
            if (!$lot) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Lot not found.'
                ]);
            }

            $lot->update([
                'name' => $request->input('name'),
                'lr_no' => $request->input('lr_no'),
                'date' => $request->input('date'),
                'shipping_by' => $request->input('shipping_by'),
                'freight' => $request->input('freight'),
                'invoice' => $request->input('invoice'),
                'receiving_date' => $request->input('receiving_date'),
            ]);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Lot updated successfully.',
                'data' => $lot
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while updating the lot.',
                'error' => $e->getMessage()
            ]);
        }
    }
    // delete
    public function destroy(Request $request, $id)
    {
        try {
            // Find the lot record by ID
            $lot = LotModel::find($id);
            if (!$lot) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Lot record not found.'
                ], 404);
            }

            // Optionally, you can add a check to ensure the record belongs to the authenticated company
            if ($lot->company_id !== Auth::user()->company_id) {
                return response()->json([
                    'code'    => 403,
                    'success' => false,
                    'message' => 'You are not authorized to delete this record.'
                ], 403);
            }

            // Delete the record
            $lot->delete();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Lot record deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while deleting the lot record.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
