<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\CreditNoteModel;
use App\Models\CreditNoteProductsModel;
use App\Models\ClientsModel;
use App\Models\DiscountModel;
use App\Models\ProductsModel;
use Carbon\Carbon;
use Auth;

class CreditNoteController extends Controller
{
    //
    // create
    // public function add_credit_note(Request $request)
    // {
    //     $request->validate([
    //         'client_id' => 'required|integer',
    //         'name' => 'required|string',
    //         'credit_note_no' => 'required|string',
    //         'credit_note_date' => 'required|date',
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
    //         'products.*.unit' => 'required|integer',
    //         'products.*.price' => 'required|numeric',
    //         'products.*.discount' => 'nullable|numeric',
    //         'products.*.hsn' => 'required|string',
    //         'products.*.tax' => 'required|numeric',
    //         'products.*.cgst' => 'required|numeric',
    //         'products.*.sgst' => 'required|numeric',
    //         'products.*.igst' => 'required|numeric'
    //     ]);
    
    
    //     $register_credit_note = CreditNoteModel::create([
    //         'client_id' => $request->input('client_id'),
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $request->input('name'),
    //         'credit_note_no' => $request->input('credit_note_no'),
    //         'credit_note_date' => $request->input('credit_note_date'),
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
    //         CreditNoteProductsModel::create([
    //             'credit_note_id' => $register_credit_note['id'],
    //             'company_id' => Auth::user()->company_id,
    //             'product_id' => $product['product_id'],
    //             'product_name' => $product['product_name'],
    //             'description' => $product['description'],
    //             'brand' => $product['brand'],
    //             'quantity' => $product['quantity'],
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

    //     unset($register_credit_note['id'], $register_credit_note['created_at'], $register_credit_note['updated_at']);
    
    //     return isset($register_credit_note) && $register_credit_note !== null
    //     ? response()->json(['Credit Note registered successfully!', 'data' => $register_credit_note], 201)
    //     : response()->json(['Failed to register Credit Note record'], 400);
    // }

    public function add_credit_note(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'credit_note_no' => 'required|string',
            'remarks' => 'nullable|string',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|integer',
        ]);

        // Fetch supplier details using supplier_id
        $client = ClientsModel::find($request->input('client_id'));
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
    
        $currentDate = Carbon::now()->toDateString();
    
        $register_credit_note = CreditNoteModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'credit_note_no' => $request->input('credit_note_no'),
            'credit_note_date' => $currentDate,
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
            
            if ($product_details) 
            {
                $quantity = $product['quantity'];
                $rate = $product_details->sale_price;
                $tax_rate = $product_details->tax;

               // Calculate the discount based on category or sub-category
               $sub_category_discount = DiscountModel::select('discount')
                                                    ->where('client', $request->input('client_id'))
                                                    ->where('sub_category', $product_details->sub_category)
                                                    ->first();

                $category_discount = DiscountModel::select('discount')
                                                    ->where('client', $request->input('client_id'))
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

                CreditNoteProductsModel::create([
                    'credit_note_id' => $register_credit_note['id'],
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product['product_id'],
                    'product_name' => $product_details->name,
                    'description' => $product_details->description,
                    'brand' => $product_details->brand,
                    'quantity' => $product['quantity'],
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

            // Update the total amount and tax values in the sales invoice record
            $register_credit_note->update([
                'total' => $total_amount,
                'cgst' => $total_cgst,
                'sgst' => $total_sgst,
                'igst' => $total_igst,
            ]);
        }

        unset($register_credit_note['id'], $register_credit_note['created_at'], $register_credit_note['updated_at']);
    
        return isset($register_credit_note) && $register_credit_note !== null
        ? response()->json(['Credit Note registered successfully!', 'data' => $register_credit_note, 'total_cgst' => $total_cgst, 'total_sgst' => $total_sgst, 'total_igst' => $total_igst, 'total_discount' => $total_discount, 'total_amount' => $total_amount], 201)
        : response()->json(['Failed to register Credit Note record'], 400);
    }

    // view
    // public function view_credit_note()
    // {
    //     $get_credit_notes = CreditNoteModel::with(['products' => function ($query) {
    //         $query->select('credit_note_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
    //     }])
    //     ->select('id', 'client_id', 'name', 'credit_note_no', 'credit_note_date', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
    //     ->where('company_id',Auth::user()->company_id)
    //     ->get();

    //     return isset($get_credit_notes) && $get_credit_notes !== null
    //         ? response()->json(['Credit Notes fetched successfully!', 'data' => $get_credit_notes], 200)
    //         : response()->json(['Failed to fetch Credit Note data'], 404);
    // }

    public function view_credit_note(Request $request)
    {
        // Get filter inputs
        $clientId = $request->input('client_id');
        $name = $request->input('name');
        $creditNoteNo = $request->input('credit_note_no');
        $creditNoteDate = $request->input('credit_note_date');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = CreditNoteModel::with(['products' => function ($query) {
            $query->select('credit_note_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'client_id', 'name', 'credit_note_no', 'credit_note_date', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($creditNoteNo) {
            $query->where('credit_note_no', 'LIKE', '%' . $creditNoteNo . '%');
        }
        if ($creditNoteDate) {
            $query->whereDate('credit_note_date', $creditNoteDate);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_credit_notes = $query->get();

        // Return response
        return $get_credit_notes->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Credit Notes fetched successfully!',
                'data' => $get_credit_notes,
                'count' => $get_credit_notes->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Credit Notes found!',
            ], 404);
    }


    // update
    public function edit_credit_note(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'credit_note_no' => 'required|string',
            'credit_note_date' => 'required|date',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.credit_note_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|integer',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
        ]);

        $creditNote = CreditNoteModel::where('id', $id)->first();

        $creditNoteUpdated = $creditNote->update([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'credit_note_no' => $request->input('credit_note_no'),
            'credit_note_date' => $request->input('credit_note_date'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = CreditNoteProductsModel::where('credit_note_id', $productData['credit_note_id'])
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
                // Update existing product
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
                // Create new product
                CreditNoteProductsModel::create([
                    'credit_note_id' => $productData['credit_note_id'],
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

        // Delete products not included in the request
        $productsDeleted = CreditNoteProductsModel::where('credit_note_id', $id)
                                                ->where('product_id', $requestProductIDs)
                                                ->delete();

        unset($creditNote['created_at'], $creditNote['updated_at']);

        return ($creditNoteUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Credit Note and products updated successfully!', 'data' => $creditNote], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // Delete Credit Note
    public function delete_credit_note($id)
    {
        $delete_credit_note = CreditNoteModel::where('id', $id)
                                                ->where('company_id', $Auth::user()->company_id)
                                                ->delete();

        $delete_credit_note_products = CreditNoteProductsModel::where('credit_note_id', $id)
                                                                ->where('company_id', Auth::user()->company_id)
                                                                ->delete();

        return $delete_credit_note && $delete_credit_note_products
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Credit Note and associated products deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Credit Note not found.'], 404);
    }

    public function importCreditNotes()
    {
        // Increase execution time for large data sets
        set_time_limit(300);

        // Clear existing records from related tables
        CreditNoteModel::truncate();
        CreditNoteProductsModel::truncate();

        // Define the external URL to fetch the data
        $url = 'https://expo.egsm.in/assets/custom/migrate/credit_note.php';

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
            // Parse JSON data for items, tax, and addons
            $itemsData = json_decode($record['items'], true);
            $taxData = json_decode($record['tax'], true);
            $addonsData = json_decode($record['addons'], true);

            // Validate JSON structure
            if (!is_array($itemsData) || !is_array($taxData) || !is_array($addonsData)) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in one of the fields.'];
                continue;
            }

            // Retrieve client based on the name
            $client = ClientsModel::where('name', $record['client'])->first();

            if (!$client) {
                $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
                continue;
            }

            // Prepare credit note data
            $creditNoteData = [
                'client_id' => $client->id,
                'name' => $record['client'],
                'credit_note_no' => !empty($record['cgst']) ? $record['cn_no'] : 'Unknown',
                'credit_note_date' => $record['cn_date'] ?? now(),
                'remarks' => $record['remarks'] ?? '',
                'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? (float) $taxData['igst'] : 0,
                'total' => (float) $record['total'] ?? 0.0,
                'currency' => 'INR',
                'template' => 1, // Default template ID
                'status' => (int) $record['status'] ?? 0,
            ];

            // Validate credit note data
            $validator = Validator::make($creditNoteData, [
                'client_id' => 'required|integer',
                'name' => 'required|string',
                'credit_note_no' => 'required|string',
                'credit_note_date' => 'required|date',
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
                // Insert the credit note data
                $creditNote = CreditNoteModel::create($creditNoteData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert credit note: ' . $e->getMessage()];
                continue;
            }

            // Insert products related to the credit note
            if (!empty($itemsData['product']) && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {
                    if (empty($product)) continue; // Skip empty product entries

                    try {
                        CreditNoteProductsModel::create([
                            'credit_note_id' => $creditNote->id,
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
            'message' => "Credit notes import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
