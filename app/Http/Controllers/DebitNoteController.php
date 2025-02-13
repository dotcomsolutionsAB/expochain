<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\DebitNoteModel;
use App\Models\DebitNoteProductsModel;
use App\Models\SuppliersModel;
use Auth;

class DebitNoteController extends Controller
{
    //
    // create
    // public function add_debit_note(Request $request)
    // {
    //     $request->validate([
    //         'supplier_id' => 'required|integer',
    //         'name' => 'required|string',
    //         'debit_note_no' => 'required|string',
    //         'debit_note_date' => 'required|date',
    //         'remarks' => 'nullable|string',
    //         'cgst' => 'required|numeric',
    //         'sgst' => 'required|numeric',
    //         'igst' => 'required|numeric',
    //         'total' => 'required|numeric',
    //         'currency' => 'required|string',
    //         'template' => 'required|integer',
    //         'status' => 'required|integer',
    //         'products' => 'required|array', // Validating array of products
    //         'products.*.product_id' => 'required|integer',
    //         'products.*.product_name' => 'required|string',
    //         'products.*.description' => 'nullable|string',
    //         'products.*.brand' => 'required|string',
    //         'products.*.quantity' => 'required|integer',
    //         'products.*.unit' => 'required|string',
    //         'products.*.price' => 'required|numeric',
    //         'products.*.discount' => 'nullable|numeric',
    //         'products.*.hsn' => 'required|string',
    //         'products.*.tax' => 'required|numeric',
    //         'products.*.cgst' => 'required|numeric',
    //         'products.*.sgst' => 'required|numeric',
    //         'products.*.igst' => 'required|numeric',
    //         'products.*.godown' => 'required|integer'         
    //     ]);
    
    
    //     $register_debit_note = DebitNoteModel::create([
    //         'supplier_id' => $request->input('supplier_id'),
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $request->input('name'),
    //         'debit_note_no' => $request->input('debit_note_no'),
    //         'debit_note_date' => $request->input('debit_note_date'),
    //         'remarks' => $request->input('remarks'),
    //         'cgst' => $request->input('cgst'),
    //         'sgst' => $request->input('sgst'),
    //         'igst' => $request->input('igst'),
    //         'total' => $request->input('total'),
    //         'currency' => $request->input('currency'),
    //         'template' => $request->input('template'),
    //         'status' => $request->input('status'),
    //     ]);
        
    //     $products = $request->input('products');

    //     // Iterate over the products array and insert each contact
    //     foreach ($products as $product) 
    //     {
    //         DebitNoteProductsModel::create([
    //             'debit_note_number' => $register_debit_note['id'],
    //             'company_id' => Auth::user()->company_id,
    //             'product_id' => $product['product_id'],
    //             'product_name' => $product['product_name'],
    //             'description' => $product['description'],
    //             'brand' => $product['brand'],
    //             'quantity' => $product['quantity'],
    //             'brand' => $product['brand'],
    //             'unit' => $product['unit'],
    //             'price' => $product['price'],
    //             'discount' => $product['discount'],
    //             'hsn' => $product['hsn'],
    //             'tax' => $product['tax'],
    //             'cgst' => $product['cgst'],
    //             'sgst' => $product['sgst'],
    //             'igst' => $product['igst'],
    //         ]);
    //     }

    //     unset($register_debit_note['id'], $register_debit_note['created_at'], $register_debit_note['updated_at']);
    
    //     return isset($register_debit_note) && $register_debit_note !== null
    //     ? response()->json(['Debit Note registered successfully!', 'data' => $register_debit_note], 201)
    //     : response()->json(['Failed to register Debit Note record'], 400);
    // }

    public function add_debit_note(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'debit_note_no' => 'required|string',
            'debit_note_date' => 'required|date',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|integer',
            'products.*.discount' => 'nullable|numeric',
            'products.*.godown' => 'required|integer'         
        ]);
    
    
        $register_debit_note = DebitNoteModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'debit_note_no' => $request->input('debit_note_no'),
            'debit_note_date' => $request->input('debit_note_date'),
            'remarks' => $request->input('remarks'),
            'cgst' => 0,
            'sgst' => 0,
            'igst' => 0,
            'total' => 0,
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);
        
        $products = $request->input('products');
        $total_amount = 0;
        $total_cgst = 0;
        $total_sgst = 0;
        $total_igst = 0;
        $total_discount = 0;

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            $product_details = ProductsModel::where('id', $product['product_id'])
                                            ->where('company_id', Auth::user()->company_id)
                                            ->first();
            
            if ($product_details) {
                $quantity = $product['quantity'];
                $rate = $product_details->sale_price;
                $tax_rate = $product_details->tax;

               // Calculate the discount based on category or sub-category
               $sub_category_discount = DiscountModel::select('discount')
                                                    ->where('client', $request->input('supplier_id'))
                                                    ->where('sub_category', $product_details->sub_category)
                                                    ->first();

                $category_discount = DiscountModel::select('discount')
                                                    ->where('client', $request->input('supplier_id'))
                                                    ->where('category', $product_details->category)
                                                    ->first();

                $discount_rate = $sub_category_discount->discount ?? $category_discount->discount ?? 0;
                $discount_amount = $rate * $quantity * ($discount_rate / 100);
                $total_discount += $discount_amount;

                // Calculate the total for the product
                $product_total = $rate * $quantity - $discount_amount;
                $tax_amount = $product_total * ($tax_rate / 100);

                // Determine the tax distribution based on the client's state
                if (strtolower($client->state) === 'west bengal') {
                    $cgst = $tax_amount / 2;
                    $sgst = $tax_amount / 2;
                    $igst = 0;
                } else {
                    $cgst = 0;
                    $sgst = 0;
                    $igst = $tax_amount;
                }

                // Accumulate totals
                $total_amount += $product_total;
                $total_cgst += $cgst;
                $total_sgst += $sgst;
                $total_igst += $igst;

                DebitNoteProductsModel::create([
                    'debit_note_number' => $register_debit_note['id'],
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product['product_id'],
                    'product_name' => $product_details->name,
                    'description' => $product_details->description,
                    'brand' => $product_details->brand,
                    'quantity' => $quantity,
                    'unit' => $product_details->unit,
                    'price' => $rate,
                    'discount' => $discount_amount,
                    'hsn' => $product_details->hsn,
                    'tax' => $product_details->tax,
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'igst' => $igst,
                ]);
            }

            else{
                return response()->json(['message' => 'Sorry, Products not found'], 404);
            }
        }

        // Update the total amount and tax values in the sales invoice record
        $register_debit_note->update([
            'total' => $total_amount,
            'cgst' => $total_cgst,
            'sgst' => $total_sgst,
            'igst' => $total_igst,
        ]);

        unset($register_debit_note['id'], $register_debit_note['created_at'], $register_debit_note['updated_at']);
    
        return isset($register_debit_note) && $register_debit_note !== null
        ? response()->json(['code' => 201,'success' => true, 'Debit Note registered successfully!', 'data' => $register_debit_note], 201)
        : response()->json(['code' => 400,'success' => false, 'Failed to register Debit Note record'], 400);
    }
    
    // view
    // public function view_debit_note()
    // {
    //     $get_debit_notes = DebitNoteModel::with(['products' => function ($query) {
    //         $query->select('debit_note_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
    //     }])
    //     ->select('id', 'supplier_id', 'name', 'debit_note_no', 'debit_note_date', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
    //     ->where('company_id',Auth::user()->company_id)
    //     ->get();

    //     return isset($get_debit_notes) && $get_debit_notes->isNotEmpty()
    //         ? response()->json(['Debit Notes fetched successfully!', 'data' => $get_debit_notes], 200)
    //         : response()->json(['Failed to fetch Debit Notes data'], 404);
    // }

    public function view_debit_note(Request $request)
    {
        // Get filter inputs
        $supplierId = $request->input('supplier_id');
        $name = $request->input('name');
        $debitNoteNo = $request->input('debit_note_no');
        $debitNoteDate = $request->input('debit_note_date');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = DebitNoteModel::with(['products' => function ($query) {
            $query->select('debit_note_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'supplier_id', 'name', 'debit_note_no', 'debit_note_date', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($debitNoteNo) {
            $query->where('debit_note_no', 'LIKE', '%' . $debitNoteNo . '%');
        }
        if ($debitNoteDate) {
            $query->whereDate('debit_note_date', $debitNoteDate);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_debit_notes = $query->get();

        // Return response
        return $get_debit_notes->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Debit Notes fetched successfully!',
                'data' => $get_debit_notes,
                'count' => $get_debit_notes->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Debit Notes found!',
            ], 404);
    }

    // update
    public function edit_debit_note(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'debit_note_no' => 'required|string',
            'debit_note_date' => 'required|date',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.godown' => 'required|integer',
        ]);

        // Get the debit note record by ID
        $debitNote = DebitNoteModel::where('id', $id)->first();

        // Update debit note details
        $debitNoteUpdated = $debitNote->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'debit_note_no' => $request->input('debit_note_no'),
            'debit_note_date' => $request->input('debit_note_date'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        // Get the list of products from the request
        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            // Check if the product exists for this debit_note_number
            $existingProduct = DebitNoteProductsModel::where('debit_note_number', $productData['debit_note_number'])
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
                // Update the existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'brand' => $productData['brand'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                ]);
            } else {
                // Create new product if it does not exist
                DebitNoteProductsModel::create([
                    'debit_note_number' => $productData['debit_note_number'],
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'brand' => $productData['brand'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                ]);
            }
        }

        // Delete products that are not in the request but exist in the database for this debit_note_number
        $productsDeleted = DebitNoteProductsModel::where('debit_note_number', $id)
                                                ->where('product_id', $requestProductIDs)
                                                ->delete();

        // Remove timestamps from the response for neatness
        unset($debitNote['created_at'], $debitNote['updated_at']);

        return ($debitNoteUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Debit Note and products updated successfully!', 'data' => $debitNote], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_debit_note($id)
    {
        // Fetch the debit note by ID
        $get_debit_note_id = DebitNoteModel::select('id', 'company_id')
                                        ->where('id', $id)
                                        ->first();

        if ($get_debit_note_id && $get_debit_note_id->company_id === Auth::user()->company_id) {
            // Delete the debit note
            $delete_debit_note = DebitNoteModel::where('id', $id)->delete();

            // Delete associated products by debit_note_number
            $delete_debit_note_products = DebitNoteProductsModel::where('debit_note_number', $get_debit_note_id->id)->delete();

            // Return success response if deletion was successful
            return $delete_debit_note && $delete_debit_note_products
                ? response()->json(['code' => 200,'success' => true, 'message' => 'Debit Note and associated products deleted successfully!'], 200)
                : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Debit Note or products.'], 400);
        } else {
            // Return error response if the debit note is not found
            return response()->json(['code' => 404,'success' => false, 'message' => 'Debit Note not found.'], 404);
        }
    }

    public function importDebitNotes()
    {
        // Increase execution time for large data sets
        set_time_limit(300);

        // Clear existing records from related tables
        DebitNoteModel::truncate();
        DebitNoteProductsModel::truncate();

        // Define the external URL to fetch the data
        $url = 'https://expo.egsm.in/assets/custom/migrate/debit_note.php';

        try {
            // Fetch data from the URL
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];

        foreach ($data as $record) {
            // Parse JSON data for items and tax
            $itemsData = json_decode($record['items'], true);
            $taxData = json_decode($record['tax'], true);
            $addonsData = json_decode($record['addons'], true);

            // Validate JSON structure
            if (!is_array($itemsData) || !is_array($taxData) || !is_array($addonsData)) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in one of the fields.'];
                continue;
            }

            // Retrieve supplier based on the name
            $supplier = SuppliersModel::where('name', $record['supplier'])->first();

            if (!$supplier) {
                $errors[] = ['record' => $record, 'error' => 'Supplier not found: ' . $record['supplier']];
                continue;
            }

            // Prepare debit note data
            $debitNoteData = [
                'supplier_id' => $supplier->id,
                'name' => $record['supplier'],
                'debit_note_no' => !empty($record['dn_no']) ? $record['dn_no'] : 'Unknown',
                'debit_note_date' => $record['dn_date'] ?? now(),
                'remarks' => $record['remarks'] ?? '',
                'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? (float) $taxData['igst'] : 0,
                'total' => (float) $record['total'] ?? 0.0,
                'currency' => 'INR',
                'template' => 1, // Default template ID
                'status' => (int) $record['status'] ?? 0,
            ];

            // Validate debit note data
            $validator = Validator::make($debitNoteData, [
                'supplier_id' => 'required|integer',
                'name' => 'required|string',
                'debit_note_no' => 'required|string',
                'debit_note_date' => 'required|date',
                'remarks' => 'nullable|string',
                'cgst' => 'required|numeric',
                'sgst' => 'required|numeric',
                'igst' => 'required|numeric',
                'total' => 'required|numeric',
                'currency' => 'required|string',
                'template' => 'required|integer',
                'status' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                // Insert the debit note data
                $debitNote = DebitNoteModel::create($debitNoteData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert debit note: ' . $e->getMessage()];
                continue;
            }

            // Insert products related to the debit note
            if (!empty($itemsData['product']) && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {
                    if (empty($product)) continue; // Skip empty product entries

                    try {
                        DebitNoteProductsModel::create([
                            'debit_note_number' => $debitNote->id,
                            'product_id' => $index + 1, // This might need to be adjusted to match your actual product ID logic
                            'product_name' => $product,
                            'description' => $itemsData['desc'][$index] ?? 'No Description',
                            'brand' => 'Unknown', // Default as brand data is missing
                            'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
                            'unit' => $itemsData['unit'][$index] ?? '',
                            'price' => (float) $itemsData['price'][$index] ?? 0.0,
                            'discount' => (float) $itemsData['discount'][$index] ?? 0.0,
                            'hsn' => $itemsData['hsn'][$index] ?? '',
                            'tax' => (float) $itemsData['tax'][$index] ?? 0.0,
                            'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
                            'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
                            'igst' => isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0,
                        ]);
                    } catch (\Exception $e) {
                        $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
                    }
                }
            }
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Debit notes import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
