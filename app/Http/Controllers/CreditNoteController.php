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
use App\Models\CounterModel;
use NumberFormatter;
use Carbon\Carbon;
use DB;
use Auth;

class CreditNoteController extends Controller
{
    //
    // create
    public function add_credit_note(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'credit_note_no' => 'required|string',
            'credit_note_date' => 'required|date',
            // 'si_no' => 'required|string|exists:t_sales_invoice,sales_invoice_no',
            'si_no' => 'nullable|string',
            'effective_date' => 'required|date',
            'type' => 'required|string',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            //'template' => 'required|integer|exists:t_pdf_template,id',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric'
        ]);
    
        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'credit_note',
            // 'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $credit_note_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $credit_note_no = $request->input('credit_note_no');
        }

        // \DB::enableQueryLog();
        $exists = CreditNoteModel::where('company_id', Auth::user()->company_id)
            ->where('credit_note_no', $credit_note_no)
            ->exists();
            // dd(\DB::getQueryLog());
            // dd($exists);

        if ($exists) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'error' => 'The combination of company_id and credit_note_no must be unique.',
            ], 422);
        }

        $register_credit_note = CreditNoteModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'credit_note_no' => $credit_note_no,
            'credit_note_date' => $request->input('credit_note_date'),
            'si_no' => $request->input('si_no'),
            'effective_date' => $request->input('effective_date'),
            'type' => $request->input('type'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => "INR",
            //'template' => $request->input('template'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            CreditNoteProductsModel::create([
                'credit_note_id' => $register_credit_note['id'],
                'company_id' => Auth::user()->company_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'quantity' => $product['quantity'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'discount' => $product['discount'],
                'discount_type' => $product['discount_type'],
                'hsn' => $product['hsn'],
                'tax' => $product['tax'],
                'cgst' => $product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
            ]);
        }

        // increment the `next_number` by 1
        CounterModel::where('name', 'credit_note')
        ->where('company_id', Auth::user()->company_id)
        ->increment('next_number');

        unset($register_credit_note['id'], $register_credit_note['created_at'], $register_credit_note['updated_at']);
    
        return isset($register_credit_note) && $register_credit_note !== null
        ? response()->json(['code' => 200, 'success' => true, 'message' =>'Credit Note registered successfully!', 'data' => $register_credit_note], 201)
        : response()->json(['code' => 400, 'success' => false, 'message' => 'Failed to register Credit Note record'], 400);
    }

    // view
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }
    public function view_credit_note(Request $request, $id = null)
    {
        try {
            // If an id is provided, fetch a single credit note.
            if ($id) {
                $creditNote = CreditNoteModel::with([
                    'products' => function ($query) {
                        $query->select(
                            'credit_note_id',
                            'product_id',
                            'product_name',
                            'description',
                            'quantity',
                            'unit',
                            'price',
                            'discount',
                            'discount_type',
                            'hsn',
                            'tax',
                            'cgst',
                            'sgst',
                            'igst',
                            DB::raw('(quantity * price) as amount'),
                            DB::raw('(tax / 2) as cgst_rate'),
                            DB::raw('(tax / 2) as sgst_rate'),
                            DB::raw('tax as igst_rate')
                        );
                    },
                    'client' => function ($q) {
                        // Select key client columns and include addresses
                        $q->select('id', 'customer_id')
                        ->with(['addresses' => function ($query) {
                            $query->select('customer_id', 'state');
                        }]);
                    }
                ])
                ->select('id', 'client_id', 'name', 'credit_note_no', 'credit_note_date', 'si_no', 'effective_date', 'type', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'gross', 'round_off')
                ->where('company_id', Auth::user()->company_id)
                ->find($id);

                if (!$creditNote) {
                    return response()->json([
                        'code' => 404,
                        'success' => false,
                        'message' => 'Credit Note not found!',
                    ], 404);
                }
                
                // Transform client: Only return state from addresses
                if ($creditNote->client) {
                    $state = optional($creditNote->client->addresses->first())->state;
                    $creditNote->client = ['state' => $state];

                    $creditNote->amount_in_words = $this->convertNumberToWords($creditNote->total);
                    $creditNote->total = is_numeric($creditNote->total) ? number_format((float)$creditNote->total, 2) : $creditNote->total;
                } else {
                    $creditNote->client = null;
                }

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Credit Note fetched successfully!',
                    'data' => $creditNote,
                ], 200);
            }

            // Get filter inputs
            $clientId = $request->input('client_id');
            $name = $request->input('name');
            $creditNoteNo = $request->input('credit_note_no');
            $creditNoteDate = $request->input('credit_note_date');
            $limit = $request->input('limit', 10); // Default limit to 10
            $offset = $request->input('offset', 0); // Default offset to 0

            // Build the query
            $query = CreditNoteModel::with(['products' => function ($query) {
                $query->select('credit_note_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
            },
            'client' => function ($q) {
                    // Select key client columns and include addresses
                    $q->select('id', 'customer_id')
                    ->with(['addresses' => function ($query) {
                        $query->select('customer_id', 'state');
                    }]);
                }
            ])
            ->select('id', 'client_id', 'name', 'credit_note_no', 'credit_note_date', 'si_no', 'effective_date', 'type', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'gross', 'round_off')
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

            // Get total record count before applying limit
            $totalRecords = $query->count();
            // Apply limit and offset
            $query->offset($offset)->limit($limit);

            // Fetch data
            $get_credit_notes = $query->get();

            // Transform data
            $get_credit_notes->transform(function ($creditNote) {
                // Transform client: Only return state from addresses
                if ($creditNote->client) {
                    $state = optional($creditNote->client->addresses->first())->state;
                    $creditNote->client = ['state' => $state];

                    $creditNote->amount_in_words = $this->convertNumberToWords($creditNote->total);
                    $creditNote->total = is_numeric($creditNote->total) ? number_format((float)$creditNote->total, 2) : $creditNote->total;
                } else {
                    $creditNote->client = null;
                }

                return $creditNote;
            });

            // Return response
            return $get_credit_notes->isNotEmpty()
                ? response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Credit Notes fetched successfully!',
                    'data' => $get_credit_notes,
                    'count' => $get_credit_notes->count(),
                    'total_records' => $totalRecords,
                ], 200)
                : response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No Credit Notes found!',
                ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // update
    public function edit_credit_note(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'credit_note_no' => 'required|string',
            'credit_note_date' => 'required|date',
            'si_no' => 'required|integer|exists:t_sales_invoice,sales_invoice_no',
            'effective_date' => 'required|date',
            'type' => 'required|string',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            //'template' => 'required|integer|exists:t_pdf_template,id',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            'products' => 'required|array',// Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|integer',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.discount_type' => 'required|in:percentage,value',
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
            'si_no' => $request->input('si_no'),
            'effective_date' => $request->input('effective_date'),
            'type' => $request->input('type'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => "INR",
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = CreditNoteProductsModel::where('credit_note_id', $id)
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
                // Update existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'discount_type' => $productData['discount_type'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                ]);
            } else {
                // Create new product
                CreditNoteProductsModel::create([
                    'credit_note_id' => $id,
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'discount_type' => $productData['discount_type'],
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
                                                ->whereNotIn('product_id', $requestProductIDs)
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
                                                ->where('company_id', Auth::user()->company_id)
                                                ->delete();

        $delete_credit_note_products = CreditNoteProductsModel::where('credit_note_id', $id)
                                                                ->where('company_id', Auth::user()->company_id)
                                                                ->delete();

        return $delete_credit_note && $delete_credit_note_products
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Credit Note and associated products deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Credit Note not found.'], 404);
    }

    // import
    // public function importCreditNotes()
    // {
    //     // Increase execution time for large data sets
    //     set_time_limit(300);

    //     // Clear existing records from related tables
    //     CreditNoteModel::truncate();
    //     CreditNoteProductsModel::truncate();

    //     // Define the external URL to fetch the data
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/credit_note.php';

    //     try {
    //         // Fetch data from the URL
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $successfulInserts = 0;
    //     $errors = [];

    //     foreach ($data as $record) {
    //         // Parse JSON data for items, tax, and addons
    //         $itemsData = json_decode($record['items'], true);
    //         $taxData = json_decode($record['tax'], true);
    //         $addonsData = json_decode($record['addons'], true);

    //         // Validate JSON structure
    //         if (!is_array($itemsData) || !is_array($taxData) || !is_array($addonsData)) {
    //             $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in one of the fields.'];
    //             continue;
    //         }

    //         // Retrieve client based on the name
    //         $client = ClientsModel::where('name', $record['client'])->first();

    //         if (!$client) {
    //             $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
    //             continue;
    //         }

    //         // Prepare credit note data
    //         $creditNoteData = [
    //             'company_id' => Auth::user()->company_id,
    //             'client_id' => $client->id,
    //             'name' => $record['client'],
    //             'credit_note_no' => !empty($record['cn_no']) ? $record['cn_no'] : 'Unknown',
    //             'credit_note_date' => $record['cn_date'] ?? now(),
    //             'remarks' => $record['remarks'] ?? '',
    //             'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
    //             'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
    //             'igst' => !empty($taxData['igst']) ? (float) $taxData['igst'] : 0,
    //             'total' => (float) $record['total'] ?? 0.0,
    //             'currency' => 'INR',
    //             'template' => 1, // Default template ID
    //             'gross' => 0,
    //             'round_off' => 0,
    //         ];

    //         // Validate credit note data
    //         $validator = Validator::make($creditNoteData, [
    //             'client_id' => 'required|integer',
    //             'name' => 'required|string',
    //             'credit_note_no' => 'required|string',
    //             'credit_note_date' => 'required|date',
    //             'remarks' => 'nullable|string',
    //             'cgst' => 'required|numeric',
    //             'sgst' => 'required|numeric',
    //             'igst' => 'required|numeric',
    //             'total' => 'required|numeric',
    //             'currency' => 'required|string',
    //             'template' => 'required|integer',
    //         ]);

    //         if ($validator->fails()) {
    //             $errors[] = ['record' => $record, 'errors' => $validator->errors()];
    //             continue;
    //         }

    //         try {
    //             // Insert the credit note data
    //             $creditNote = CreditNoteModel::create($creditNoteData);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert credit note: ' . $e->getMessage()];
    //             continue;
    //         }

    //         // Insert products related to the credit note
    //         if (!empty($itemsData['product']) && is_array($itemsData['product'])) {
    //             foreach ($itemsData['product'] as $index => $product) {
    //                 if (empty($product)) continue; // Skip empty product entries

    //                 try {
    //                     CreditNoteProductsModel::create([
    //                         'credit_note_id' => $creditNote->id,
    //                         'company_id' => Auth::user()->company_id,
    //                         'product_id' => $index + 1, // This might need to be adjusted to match your actual product ID logic
    //                         'product_name' => $product,
    //                         'description' => $itemsData['desc'][$index] ?? 'No Description',
    //                         'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
    //                         'unit' => $itemsData['unit'][$index] ?? '',
    //                         'price' => (float) $itemsData['price'][$index] ?? 0.0,
    //                         'discount' => (float) $itemsData['discount'][$index] ?? 0.0,
    //                         'discount_type' => "percentage",
    //                         'hsn' => $itemsData['hsn'][$index] ?? '',
    //                         'tax' => (float) $itemsData['tax'][$index] ?? 0.0,
    //                         'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
    //                         'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
    //                         'igst' => isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0,
    //                     ]);
    //                 } catch (\Exception $e) {
    //                     $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
    //                 }
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Credit notes import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }
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
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }

        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];
        
        // **Chunk Data for Better Performance**
        $batchSize = 50; // Process in batches of 50
        $dataChunks = array_chunk($data, $batchSize);

        foreach ($dataChunks as $batch) {
            $creditNotesToInsert = [];
            $creditNotesProductsToInsert = [];

            foreach ($batch as $record) {
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

                // Prepare credit note data for batch insert
                $creditNotesToInsert[] = [
                    'company_id' => Auth::user()->company_id,
                    'client_id' => $client->id,
                    'name' => $record['client'],
                    'credit_note_no' => !empty($record['cn_no']) ? $record['cn_no'] : 'Unknown',
                    'credit_note_date' => $record['cn_date'] ?? now(),
                    'si_no' => $record['sales_invoice'],
                    'effective_date' => $record['effective_date'],
                    'type' => $record['type'],
                    'remarks' => $record['remarks'] ?? '',
                    'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
                    'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
                    'igst' => !empty($taxData['igst']) ? (float) $taxData['igst'] : 0,
                    'total' => (float) $record['total'] ?? 0.0,
                    'currency' => 'INR',
                    //'template' => null, // Default template ID
                    'gross' => 0,
                    'round_off' => 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // ✅ Bulk Insert Credit Notes
            if (!empty($creditNotesToInsert)) {
                try {
                    CreditNoteModel::insert($creditNotesToInsert);
                    $successfulInserts += count($creditNotesToInsert);
                } catch (\Exception $e) {
                    $errors[] = ['batch' => $creditNotesToInsert, 'error' => 'Failed to insert credit notes: ' . $e->getMessage()];
                    continue;
                }
            }

            // Fetch inserted Credit Note IDs
            $latestCreditNotes = CreditNoteModel::orderBy('id', 'desc')->limit(count($creditNotesToInsert))->get();

            foreach ($latestCreditNotes as $index => $creditNote) {
                $record = $batch[$index];
                $itemsData = json_decode($record['items'], true);
                $taxData = json_decode($record['tax'], true);

                // Insert products related to the credit note
                if (!empty($itemsData['product']) && is_array($itemsData['product'])) {
                    foreach ($itemsData['product'] as $idx => $product) {
                        if (empty($product)) continue; // Skip empty product entries

                        $creditNotesProductsToInsert[] = [
                            'credit_note_id' => $creditNote->id,
                            'company_id' => Auth::user()->company_id,
                            'product_id' => $idx + 1, // This might need to be adjusted to match your actual product ID logic
                            'product_name' => $product,
                            'description' => $itemsData['desc'][$idx] ?? 'No Description',
                            'quantity' => (int) $itemsData['quantity'][$idx] ?? 0,
                            'unit' => $itemsData['unit'][$idx] ?? '',
                            'price' => (float) $itemsData['price'][$idx] ?? 0.0,
                            'discount' => (float) $itemsData['discount'][$idx] ?? 0.0,
                            'discount_type' => "percentage",
                            'hsn' => $itemsData['hsn'][$idx] ?? '',
                            'tax' => (float) $itemsData['tax'][$idx] ?? 0.0,
                            'cgst' => !empty($taxData['cgst']) ? (float) $taxData['cgst'] : 0,
                            'sgst' => !empty($taxData['sgst']) ? (float) $taxData['sgst'] : 0,
                            'igst' => isset($itemsData['igst'][$idx]) ? (float) $itemsData['igst'][$idx] : 0,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }
            }

            // ✅ Bulk Insert Credit Note Products
            if (!empty($creditNotesProductsToInsert)) {
                try {
                    CreditNoteProductsModel::insert($creditNotesProductsToInsert);
                } catch (\Exception $e) {
                    $errors[] = ['batch' => $creditNotesProductsToInsert, 'error' => 'Failed to insert products: ' . $e->getMessage()];
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
