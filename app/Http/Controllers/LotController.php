<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use App\Models\LotModel;
use App\Models\PurchaseInvoiceModel;
use Illuminate\Support\Facades\Auth;
use App\Models\CounterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

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
                'data' => $lot->makeHidden(['id', 'created_at','updated_at'])
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
            // Pagination defaults
            $offset = $request->input('offset', 0);
            $limit = $request->input('limit', 10);
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // Build base query with purchase invoices relationship
            $query = LotModel::with(['purchaseInvoices' => function ($query) {
                $query->select('id', 'supplier_id', 'name', 'purchase_invoice_no', 'purchase_invoice_date', 'oa_no', 'ref_no', 'cgst', 'sgst', 'igst', 'total', 'gross', 'round_off', 'lot_id');
            }]);

            // If a filter for lr_no is provided, add a where clause (partial match)
            if ($request->filled('lr_no')) {
                $lrNo = $request->input('lr_no');
                $query->where('lr_no', 'LIKE', '%' . $lrNo . '%');
            }

            // Date range filtering
            if ($dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('date', '<=', $dateTo);
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

                // Add purchase invoices from relationship
                $lot->purchase_invoices = $lot->purchaseInvoices ?? collect([]);

                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'Lot fetched successfully.',
                    'data'    => $lot->makeHidden(['invoice', 'created_at', 'updated_at', 'purchaseInvoices']),
                ]);
            }

                // For list view
                $totalRecords = $query->count();

                $lots = $query->orderBy('date', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                // Add purchase invoices from relationship for each lot
                $lots->each(function ($lot) {
                    $lot->purchase_invoices = $lot->purchaseInvoices ?? collect([]);
                });

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Lots fetched successfully.',
                'data'    => $lots->makeHidden(['invoice', 'created_at','updated_at']),
                'total_records' => $totalRecords,
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
                'name' => $purchaseInvoice ? $purchaseInvoice->name : 'Unknown',
                'purchase_invoice_number' => $purchaseInvoice ? $purchaseInvoice->purchase_invoice_no : 'Unknown'
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
                'data' => $lot->makeHidden(['id', 'created_at','updated_at'])
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
    public function destroy($id)
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

    // import
    public function importLotInfo()
    {
        set_time_limit(300);

        // Clear old data - disable foreign key checks temporarily to allow truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        LotModel::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // Also clear lot_id from purchase invoices
        PurchaseInvoiceModel::whereNotNull('lot_id')->update(['lot_id' => null]);

        // Source URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/lot_info.php';

        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'error'   => 'Failed to fetch data: ' . $e->getMessage()
            ], 500);
        }

        if ($response->failed()) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'error'   => 'Failed to fetch data from source.'
            ], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'No lot data found!'
            ], 404);
        }

        $userCompanyId = Auth::user()->company_id;
        $batchData = [];
        $recordMap = []; // Map to track which record corresponds to which batch item
        $successful = 0;
        $errors = [];

        foreach ($data as $index => $record) {
            try {
                // Format date properly
                $lrDate = null;
                if (!empty($record['lr_date']) && $record['lr_date'] !== '0000-00-00') {
                    $lrDate = date('Y-m-d', strtotime($record['lr_date']));
                    if ($lrDate === '1970-01-01') {
                        $lrDate = null; // Invalid date
                    }
                }

                // Format receiving_date properly
                $receivingDate = null;
                if (!empty($record['lr_receiving_date']) && $record['lr_receiving_date'] !== '0000-00-00') {
                    $receivingDate = date('Y-m-d', strtotime($record['lr_receiving_date']));
                    if ($receivingDate === '1970-01-01') {
                        $receivingDate = null; // Invalid date
                    }
                }

                // Process invoice field
                $invoice = null;
                if (isset($record['lr_invoice']) && !empty($record['lr_invoice'])) {
                    $invoice = is_string($record['lr_invoice']) 
                        ? str_replace(['["', '"]', '","'], ['', '', ','], $record['lr_invoice'])
                        : $record['lr_invoice'];
                }

                $lrNo = $record['lr_no'] ?? null;
                $batchData[] = [
                    'company_id'     => Auth::user()->company_id,
                    'name'           => $record['lr_name'] ?? null,
                    'lr_no'          => $lrNo,
                    'date'           => $lrDate,
                    'shipping_by'    => $record['lr_shipping'] ?? null,
                    'freight'        => is_numeric($record['lr_freight']) ? (float)$record['lr_freight'] : null,
                    'invoice'        => $invoice,
                    'receiving_date' => $receivingDate,
                    'created_at'     => now(),
                    'updated_at'     => now()
                ];
                
                // Store mapping: lr_no -> original record (for invoice processing)
                if ($lrNo) {
                    $recordMap[$lrNo] = $record;
                }
                
                $successful++;
            } catch (\Exception $e) {
                $errors[] = [
                    'record' => $record,
                    'error'  => 'Failed to parse record: ' . $e->getMessage()
                ];
            }
        }

        // Perform batch insert in chunks
        $insertedLots = [];
        if (!empty($batchData)) {
            try {
                foreach (array_chunk($batchData, 100) as $chunk) {
                    LotModel::insert($chunk);
                }
                
                // Get inserted lot IDs by matching on unique fields (lr_no, company_id)
                // Since we can't get IDs from insert(), we need to query them back
                $lrNos = array_filter(array_column($batchData, 'lr_no'));
                if (!empty($lrNos)) {
                    $insertedLots = LotModel::where('company_id', Auth::user()->company_id)
                        ->whereIn('lr_no', $lrNos)
                        ->get()
                        ->keyBy('lr_no');
                }
            } catch (\Exception $e) {
                return response()->json([
                    'code'    => 500,
                    'success' => false,
                    'message' => 'Failed to insert lot data.',
                    'error'   => $e->getMessage(),
                    'errors'  => $errors
                ], 500);
            }
        }

        // Update purchase invoices with lot_id
        $updatedInvoices = 0;
        if (!empty($insertedLots) && !empty($recordMap)) {
            foreach ($insertedLots as $lrNo => $lot) {
                try {
                    if (!isset($recordMap[$lrNo])) {
                        continue;
                    }
                    
                    $record = $recordMap[$lrNo];
                    $lotId = $lot->id;
                    
                    // Parse invoice numbers from lr_invoice field
                    $invoiceString = $record['lr_invoice'] ?? null;
                    if (empty($invoiceString)) {
                        continue;
                    }
                    
                    // Clean and split invoice numbers
                    $invoiceString = is_string($invoiceString) 
                        ? str_replace(['["', '"]', '","'], ['', '', ','], $invoiceString)
                        : $invoiceString;
                    
                    $invoiceNumbers = array_filter(array_map('trim', explode(',', $invoiceString)));
                    
                    if (!empty($invoiceNumbers)) {
                        // Update purchase invoices by purchase_invoice_no
                        $updated = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
                            ->whereIn('purchase_invoice_no', $invoiceNumbers)
                            ->update(['lot_id' => $lotId]);
                        
                        $updatedInvoices += $updated;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'record' => $record,
                        'error'  => 'Failed to update purchase invoices: ' . $e->getMessage()
                    ];
                }
            }
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => "Lot import completed with $successful successful records. Updated $updatedInvoices purchase invoices.",
            'errors'  => $errors
        ]);
    }

}
