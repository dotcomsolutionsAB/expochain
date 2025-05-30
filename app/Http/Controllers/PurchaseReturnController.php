<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\PurchaseReturnModel;
use App\Models\PurchaseReturnProductsModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use App\Models\GodownModel;
use Carbon\Carbon;
use Auth;

class PurchaseReturnController extends Controller
{
    //
    // create
    public function add_purchase_return(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:t_suppliers,id', // Ensure supplier exists
            'name' => 'required|string|max:255',
            'purchase_return_no' => 'required|string',
            'purchase_return_date' => 'required|date',
            'purchase_invoice_id' => 'required|integer|exists:t_purchase_invoice,id', // Ensure invoice exists
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric|min:0',
            'sgst' => 'required|numeric|min:0',
            'igst' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'gross' => 'nullable|numeric|min:0',
            'round_off' => 'nullable|numeric',

            'products' => 'required|array', // Validating array of products
            'products.*.purchase_return_id' => 'required|integer|exists:t_purchase_return,id', // Ensure purchase return exists
            'products.*.product_id' => 'required|integer|exists:t_products,id', // Ensure product exists
            'products.*.product_name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer|min:1', // Must be at least 1
            'products.*.unit' => 'required|string|max:50',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value', // Restrict to known discount types
            'products.*.hsn' => 'nullable|string|max:50',
            'products.*.tax' => 'required|numeric|min:0',
            'products.*.cgst' => 'required|numeric|min:0',
            'products.*.sgst' => 'required|numeric|min:0',
            'products.*.igst' => 'required|numeric|min:0',
            'products.*.godown' => 'required|integer|exists:t_godown,id', // Ensure valid godown is provided
        ]);

       // Handle quotation number logic
       $counterController = new CounterController();
       $sendRequest = Request::create('/counter', 'GET', [
           'name' => 'purchase_return',
           // 'company_id' => Auth::user()->company_id,
       ]);

       $response = $counterController->view_counter($sendRequest);
       $decodedResponse = json_decode($response->getContent(), true);

       if ($decodedResponse['code'] === 200) {
           $data = $decodedResponse['data'];
           $get_customer_type = $data[0]['type'];
       }

       if ($get_customer_type == "auto") {
           $purchase_return_no = $decodedResponse['data'][0]['prefix'] .
               str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
               $decodedResponse['data'][0]['postfix'];
       } else {
           $purchase_return_no = $request->input('purchase_return_no');
       }

       // \DB::enableQueryLog();
       $exists = PurchaseReturnModel::where('company_id', Auth::user()->company_id)
           ->where('purchase_return_no', $purchase_return_no)
           ->exists();
           // dd(\DB::getQueryLog());
           // dd($exists);

       if ($exists) {
           return response()->json([
               'code' => 422,
               'success' => false,
               'error' => 'The combination of company_id and purchase_return_no must be unique.',
           ], 422);
       }
   
       $purchaseInvoiceId = $request->input('purchase_invoice_id');
       $template = PurchaseInvoiceModel::where('id', $purchaseInvoiceId)->value('template') ?? null;
    
        $register_purchase_return = PurchaseReturnModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' =>  $supplier->name,
            'purchase_return_no' => $purchase_return_no,
            'purchase_return_date' => $currentDate,
            'purchase_invoice_id' => $request->input('purchase_invoice_id'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $template,
            'status' => $request->input('status'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            PurchaseReturnProductsModel::create([
                'purchase_return_number' => $register_purchase_return['id'],
                'product_id' => $product['product_id'],
                'company_id' => Auth::user()->company_id,
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
                'godown' => $product['godown'],
            ]);
        }

        // increment the `next_number` by 1
        CounterModel::where('name', 'purchase_return')
        ->where('company_id', Auth::user()->company_id)
        ->increment('next_number');

        unset($register_purchase_return['id'], $register_purchase_return['created_at'], $register_purchase_return['updated_at']);
    
        return isset($register_purchase_return) && $register_purchase_return !== null
        ? response()->json(['Purchase Return registered successfully!', 'data' => $register_purchase_return, 'total_cgst' => $total_cgst, 'total_sgst' => $total_sgst, 'total_igst' => $total_igst, 'total_discount' => $total_discount, 'total_amount' => $total_amount], 201)
        : response()->json(['Failed to register Purchase Return record'], 400);
    }

    // view
    public function view_purchase_return(Request $request, $id = null)
    {
        try {
            // Get filter inputs
            $supplierId = $request->input('supplier_id');
            $name = $request->input('name');
            $purchaseReturnNo = $request->input('purchase_return_no');
            $purchaseReturnDate = $request->input('purchase_return_date');
            $purchaseInvoiceId = $request->input('purchase_invoice_id');
            $limit = $request->input('limit', 10); // Default limit to 10
            $offset = $request->input('offset', 0); // Default offset to 0

            // Build the query
            $query = PurchaseReturnModel::with(['products' => function ($query) {
                $query->select('purchase_return_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
            },
                'supplier' => function ($q) {
                    // Select key supplier columns and include addresses
                    $q->select('id', 'supplier_id')
                    ->with(['addresses' => function ($query) {
                        $query->select('supplier_id', 'state');
                    }]);
                }
            ])
            ->select('id', 'supplier_id', 'name', 'purchase_return_no', 'purchase_return_date', 'purchase_invoice_id', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'gross', 'round_off')
            ->where('company_id', Auth::user()->company_id);

            // If an id is provided, fetch a single purchase return
        if ($id) {
            $purchaseReturn = $query->find($id);
            if (!$purchaseReturn) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Purchase Return not found!',
                ], 404);
            }

            // Transform supplier: Only return state from addresses
            if ($purchaseReturn->supplier) {
                $state = optional($purchaseReturn->supplier->addresses->first())->state;
                $purchaseReturn->supplier = ['state' => $state];
            } else {
                $purchaseReturn->supplier = null;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Purchase Return fetched successfully!',
                'data' => $purchaseReturn,
            ], 200);
        }


            // Apply filters
            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }
            if ($name) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            }
            if ($purchaseReturnNo) {
                $query->where('purchase_return_no', 'LIKE', '%' . $purchaseReturnNo . '%');
            }
            if ($purchaseReturnDate) {
                $query->whereDate('purchase_return_date', $purchaseReturnDate);
            }
            if ($purchaseInvoiceId) {
                $query->where('purchase_invoice_id', 'LIKE', '%' . $purchaseInvoiceId . '%');
            }

            // Get total record count before applying limit
            $totalRecords = $query->count();
            // Apply limit and offset
            $query->offset($offset)->limit($limit);

            // Fetch data
            $get_purchase_returns = $query->get();

            // Transform data: For each purchase return, transform supplier data to include only state from addresses
            $get_purchase_returns->transform(function ($purchaseReturn) {
                if ($purchaseReturn->supplier) {
                    $state = optional($purchaseReturn->supplier->addresses->first())->state;
                    $purchaseReturn->supplier = ['state' => $state];
                } else {
                    $purchaseReturn->supplier = null;
                }
                return $purchaseReturn;
            });

            // Return response
            return $get_purchase_returns->isNotEmpty()
                ? response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Purchase Returns fetched successfully!',
                    'data' => $get_purchase_returns,
                    'count' => $get_purchase_returns->count(),
                    'total_records' => $totalRecords,
                ], 200)
                : response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No Purchase Returns found!',
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
    public function edit_purchase_return(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'purchase_return_no' => 'required|string',
            'purchase_return_date' => 'required|date',
            'purchase_invoice_id' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.purchase_return_number' => 'required|integer',
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

        $purchaseReturn = PurchaseReturnModel::where('id', $id)->first();

        $purchaseReturnUpdated = $purchaseReturn->update([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'purchase_return_no' => $request->input('purchase_return_no'),
            'purchase_return_date' => $request->input('purchase_return_date'),
            'purchase_invoice_id' => $request->input('purchase_invoice_id'),
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

            $existingProduct = PurchaseReturnProductsModel::where('purchase_return_number', $productData['purchase_return_number'])
                                                        ->where('product_id', $productData['product_id'])
                                                        ->first();

            if ($existingProduct) {
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
                    'godown' => $productData['godown'],
                ]);
            } else {
                PurchaseReturnProductsModel::create([
                    'purchase_return_number' => $productData['purchase_return_number'],
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
                    'godown' => $productData['godown'],
                ]);
            }
        }

        $productsDeleted = PurchaseReturnProductsModel::where('purchase_return_number', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        unset($purchaseReturn['created_at'], $purchaseReturn['updated_at']);

        return ($purchaseReturnUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Return and products updated successfully!', 'data' => $purchaseReturn], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_purchase_return($id)
    {
        $get_purchase_return_id = PurchaseReturnModel::select('id', 'company_id')->where('id', $id)->first();

        if ($get_purchase_return_id && $get_purchase_return_id->company_id === Auth::user()->company_id) {
            $delete_purchase_return = PurchaseReturnModel::where('id', $id)->delete();

            $delete_purchase_return_products = PurchaseReturnProductsModel::where('purchase_return_number', $get_purchase_return_id->id)->delete();

            return $delete_purchase_return && $delete_purchase_return_products
                ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Return and associated products deleted successfully!'], 200)
                : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Purchase Return or products.'], 400);
        } else {
            return response()->json(['code' => 404,'success' => false, 'message' => 'Purchase Return not found.'], 404);
        }
    }

    // migration
    public function importPurchaseReturns()
    {
        set_time_limit(300); // Extend execution time for large data sets

        // Clear previous data
        PurchaseReturnModel::truncate();
        PurchaseReturnProductsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_return.php'; // External data source

        // Fetch data
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
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
        $chunkSize = 50; // Process in batches of 50

        collect($data)->chunk($chunkSize)->each(function ($chunk) use (&$successfulInserts, &$errors) {
            $purchaseReturnsBatch = [];
            $purchaseReturnProductsBatch = [];

            foreach ($chunk as $record) {
                $itemsData = json_decode($record['items'] ?? '{}', true);
                $taxData = json_decode($record['tax'] ?? '{}', true);

                // Find supplier
                $supplier = SuppliersModel::where('name', $record['client'])->first();

                if (!$supplier) {
                    $errors[] = ['record' => $record, 'error' => "Supplier '{$record['client']}' not found."];
                    continue;
                }

                // 🔍 Fetch `purchase_invoice_id` from `t_purchase_invoice`
                $purchaseInvoice = PurchaseInvoiceModel::where('purchase_invoice_no', 'LIKE', $record['so_no'])->first();
                if (!$purchaseInvoice) {
                    $errors[] = ['record' => $record, 'error' => "Purchase Invoice '{$record['so_no']}' not found."];
                    continue;
                }

                $purchaseReturnsBatch[] = [
                    'company_id' => Auth::user()->company_id,
                    'supplier_id' => $supplier->id,
                    'name' => $record['client'],
                    'purchase_return_no' => $record['si_no'] ?? 'Unknown',
                    'purchase_return_date' => $record['si_date'] ?? now(),
                    'purchase_invoice_id' => $purchaseInvoice->id, // Storing the fetched purchase invoice ID
                    // 'purchase_invoice_id' => $record['so_no'] ?? 'Unknown',
                    'remarks' => $record['remarks'] ?? null,
                    'cgst' => $taxData['cgst'] ?? 0,
                    'sgst' => $taxData['sgst'] ?? 0,
                    'igst' => $taxData['igst'] ?? 0,
                    'total' => $record['total'] ?? 0,
                    'currency' => 'INR',
                    'template' => json_decode($record['pdf_template'], true)['id'] ?? 0,
                    'gross' => 0,
                    'round_off' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // ✅ Bulk insert Purchase Returns
            if (!empty($purchaseReturnsBatch)) {
                PurchaseReturnModel::insert($purchaseReturnsBatch);
                $successfulInserts += count($purchaseReturnsBatch);
            }

            // Process products in a separate loop after inserting Purchase Returns
            foreach ($chunk as $record) {
                $itemsData = json_decode($record['items'] ?? '{}', true);
                
                if (!$itemsData || !isset($itemsData['product']) || !is_array($itemsData['product'])) {
                    continue;
                }

                // Fetch inserted purchase return
                $purchaseReturn = PurchaseReturnModel::where('purchase_return_no', $record['si_no'])->first();
                if (!$purchaseReturn) {
                    $errors[] = ['record' => $record, 'error' => "Purchase Return '{$record['si_no']}' not found after insert."];
                    continue;
                }

                foreach ($itemsData['product'] as $index => $productName) {
                    $product = ProductsModel::where('name', $productName)->first();

                    if (!$product) {
                        $errors[] = ['record' => $record, 'error' => "Product '{$productName}' not found."];
                        continue;
                    }

                    // Fetch `godown_id` from `GodownModel` using `company_id` and `name`
                    $godownName = $itemsData['place'][$index] ?? 'Default Godown';
                    $godown = GodownModel::where('name', $godownName)
                                        ->where('company_id', Auth::user()->company_id) // Ensure correct company
                                        ->first();

                    // Use `godown_id` if found, otherwise set a default ID (e.g., `1` or `NULL`)
                    $godownId = $godown ? $godown->id : null; // Change `null` to your actual default ID

                    $purchaseReturnProductsBatch[] = [
                        'purchase_return_id' => $purchaseReturn->id,
                        'company_id' => Auth::user()->company_id,
                        'product_id' => $product->id,
                        'product_name' => $productName,
                        'description' => $itemsData['desc'][$index] ?? 'null',
                        'quantity' => (int) ($itemsData['quantity'][$index] ?? 0),
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => (float) ($itemsData['price'][$index] ?? 0),
                        'discount' => (float) ($itemsData['discount'][$index] ?? 0),
                        'discount_type' => "percentage",
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => (float) ($itemsData['tax'][$index] ?? 0),
                        'cgst' => (float) ($itemsData['cgst'][$index] ?? 0),
                        'sgst' => (float) ($itemsData['sgst'][$index] ?? 0),
                        'igst' => 0,
                        'godown' => $godownId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // ✅ Bulk insert Purchase Return Products
            if (!empty($purchaseReturnProductsBatch)) {
                PurchaseReturnProductsModel::insert($purchaseReturnProductsBatch);
            }
        });

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Purchase returns import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }
}
