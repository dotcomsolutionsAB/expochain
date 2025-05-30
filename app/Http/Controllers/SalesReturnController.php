<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\SalesReturnModel;
use App\Models\SalesReturnProductsModel;
use App\Models\SalesInvoiceModel;
use App\Models\ClientsModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use App\Models\GodownModel;
use App\Models\ResetController;
use Carbon\Carbon;
use Auth;

class SalesReturnController extends Controller
{
    //
    // create
    public function add_sales_return(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'sales_return_no' => 'required|string',
            'sales_return_date' => 'required|date',
            'sales_invoice_id' => 'required|integer|exists:t_sales_invoice,id',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            'products' => 'required|array', // For validating array of products
            'products.*.sales_return_id' => 'required|integer',
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
            'products.*.godown' => 'required|integer|exists:t_godown,id'
        ]);
    
        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'sales_return',
            // 'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $sales_return_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $sales_return_no = $request->input('sales_return_no');
        }

        // \DB::enableQueryLog();
        $exists = DebitNoteModel::where('company_id', Auth::user()->company_id)
            ->where('sales_return_no', $sales_return_no)
            ->exists();
            // dd(\DB::getQueryLog());
            // dd($exists);

        if ($exists) {
            return response()->json([
                'code' => 422,
                'success' => true,
                'error' => 'The combination of company_id and sales_return_no must be unique.',
            ], 422);
        }
    
        $salesInvoiceId = $request->input('sales_invoice_id');
        $template = SalesInvoiceModel::where('id', $salesInvoiceId)->value('template') ?? null;

        $register_sales_return = SalesReturnModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'sales_return_no' => $sales_return_no,
            'sales_return_date' => $request->input('sales_return_date'),
            'sales_invoice_id' => $request->input('sales_invoice_id'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => "INR",
            'template' => $template,
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
        ]);
        
        $products = $request->input('products');
    
        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            SalesReturnProductsModel::create([
                'sales_return_id' => $register_sales_return['id'],
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
                'godown' => $product['godown'],
            ]);
        }

        // increment the `next_number` by 1
        CounterModel::where('name', 'sales_return')
        ->where('company_id', Auth::user()->company_id)
        ->increment('next_number');

        ResetController::updateReturnedQuantitiesForSalesInvoice($salesInvoiceId);

        unset($register_sales_return['id'], $register_sales_return['created_at'], $register_sales_return['updated_at']);
    
        return isset($register_sales_return) && $register_sales_return !== null
        ? response()->json(['Sales Retrun registered successfully!', 'data' => $register_sales_return], 201)
        : response()->json(['Failed to register Sales Return record'], 400);
    }

    public function view_sales_return(Request $request, $id = null)
    {
        try {
            // Get filter inputs
            $salesReturnId = $request->input('sales_return_id'); // Filter by sales return ID
            $productId = $request->input('product_id'); // Filter by product ID
            $productName = $request->input('product_name'); // Filter by product name
            $limit = $request->input('limit', 10); // Default limit to 10
            $offset = $request->input('offset', 0); // Default offset to 0

            // Build the query
            $query = SalesReturnModel::with(['products' => function ($query) use ($productId, $productName) {
                $query->select('sales_return_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
                
                // Apply product-related filters
                if ($productId) {
                    $query->where('product_id', $productId);
                }
                if ($productName) {
                    $query->where('product_name', 'LIKE', '%' . $productName . '%');
                }
            },
                'client' => function ($q) {
                        // Only select the key columns needed for the join (ID and customer_id)
                        $q->select('id', 'customer_id')
                        ->with(['addresses' => function ($query) {
                            // Only fetch the customer_id (for joining) and the state field
                            $query->select('customer_id', 'state');
                        }]);
                    }
            ])
            ->select('id', 'client_id', 'name', 'sales_return_no', 'sales_return_date', 'sales_invoice_id', 'remarks', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'gross', 'round_off')
            ->where('company_id', Auth::user()->company_id);

            // If an id is provided, fetch a single sales return record.
            if ($id) {
                $salesReturn = $query->find($id);
                if (!$salesReturn) {
                    return response()->json([
                        'code' => 404,
                        'success' => false,
                        'message' => 'Sales Return not found!',
                    ], 404);
                }

                // Transform supplier data: Only return state from addresses.
                if ($salesReturn->client) {
                    $state = optional($salesReturn->client->addresses->first())->state;
                    $salesReturn->client = ['state' => $state];
                } else {
                    $salesReturn->client = null;
                }

                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Sales Return fetched successfully!',
                    'data' => $salesReturn,
                ], 200);
            }

            // Apply sales_return_id filter
            if ($salesReturnId) {
                $query->where('id', $salesReturnId);
            }

            // Get total record count before applying limit
            $totalRecords = $query->count();
            // Apply limit and offset
            $query->offset($offset)->limit($limit);

            // Fetch the data
            $get_sales_returns = $query->get();

            // Transform each sales return's client: Only return state from addresses
            $get_sales_returns->transform(function ($salesReturn) {
                if ($salesReturn->client) {
                    $state = optional($salesReturn->client->addresses->first())->state;
                    $salesReturn->client = ['state' => $state];
                } else {
                    $salesReturn->client = null;
                }
                return $salesReturn;
            });

            // Return the response
            return $get_sales_returns->isNotEmpty()
                ? response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Sales Returns fetched successfully!',
                    'data' => $get_sales_returns,
                    'count' => $get_sales_returns->count(),
                    'total_records' => $totalRecords,
                ], 200)
                : response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No Sales Returns found!',
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
    public function edit_sales_return(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'sales_return_no' => 'required|string',
            'sales_return_date' => 'required|date',
            'sales_invoice_id' => 'required|integer|exists:t_sales_invoice,id',
            'remarks' => 'nullable|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            'products' => 'required|array',// Validating array of products
            'products.*.sales_return_id' => 'required|integer',
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
            'products.*.godown' => 'required|exists:t_godown,id'
        ]);

        $salesReturn = SalesReturnModel::where('id', $id)->first();

        $salesReturnUpdated = $salesReturn->update([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'sales_return_no' => $request->input('sales_return_no'),
            'sales_return_date' => $request->input('sales_return_date'),
            'sales_invoice_id' => $request->input('sales_invoice_id'),
            'remarks' => $request->input('remarks'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesReturnProductsModel::where('sales_return_id', $productData['sales_return_id'])
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
                    'godown' => $productData['godown'],
                ]);
            } else {
                // Create new product
                SalesReturnProductsModel::create([
                    'sales_return_id' => $productData['sales_return_id'],
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
                    'godown' => $productData['godown'],
                ]);
            }
        }

        // Delete products not included in the request
        $productsDeleted = SalesReturnProductsModel::where('sales_return_id', $id)
                                                ->whereNotIn('product_id', $requestProductIDs)
                                                ->delete();

        unset($salesReturn['created_at'], $salesReturn['updated_at']);

        $salesInvoiceId = $request->input('sales_invoice_id');
        ResetController::updateReturnedQuantitiesForSalesInvoice($salesInvoiceId);
        
        return ($salesReturnUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Return and products updated successfully!', 'data' => $salesReturn], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_sales_return($id)
    {
        $salesreturn = SalesReturnModel::where('id', $id)
                                                ->where('company_id', $company_id)
                                                ->get();
        $salesInvoiceId = $salesreturn->first()->sales_invoice_id ?? null;
        
        $delete_sales_return = SalesReturnModel::where('id', $id)
                                                ->where('company_id', $company_id)
                                                ->delete();

        $delete_sales_return_products = SalesReturnProductsModel::where('sales_return_id', $id)
                                                                ->where('company_id', $company_id)
                                                                ->delete();

        ResetController::updateReturnedQuantitiesForSalesInvoice($salesInvoiceId);

        return $delete_sales_return && $delete_sales_return_products
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Return and associated products deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Sales Return not found.'], 404);
    }

    // migration

    public function importSalesReturns()
    {
        // Increase execution time for large data sets
        set_time_limit(300);

        // Clear existing records before importing
        SalesReturnModel::truncate();
        SalesReturnProductsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/sales_return.php';

        // Fetch data from the external URL
        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source: ' . $e->getMessage()], 500);
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

        // ✅ Pre-fetch all required clients (Suppliers)
        $supplierNames = collect($data)->pluck('supplier')->unique();
        $suppliers = ClientsModel::whereIn('name', $supplierNames)->get()->keyBy('name');

        // ✅ Pre-fetch all Sales Invoices to map sales_invoice_no to sales_invoice_id
        $invoiceNumbers = collect($data)->pluck('reference_no')->unique();
        $salesInvoices = SalesInvoiceModel::whereIn('sales_invoice_no', $invoiceNumbers)
            ->pluck('id', 'sales_invoice_no'); // Key: sales_invoice_no, Value: id

        // ✅ Store Sales Return Data in a Batch Array
        $salesReturnDataBatch = [];
        $salesReturnIds = [];

        foreach ($data as $record) {
            // Decode JSON fields for items and tax
            $itemsData = json_decode($record['items'], true);
            $taxData = json_decode($record['tax'], true);

            if (!is_array($itemsData)) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure for items.'];
                continue;
            }

            // ✅ Get Supplier (Client)
            $supplier = $suppliers[$record['supplier']] ?? null;
            if (!$supplier) {
                $errors[] = ['record' => $record, 'error' => 'Supplier not found: ' . $record['supplier']];
                continue;
            }

            // ✅ Get Sales Invoice ID based on sales_invoice_no
            $salesInvoiceId = $salesInvoices[$record['reference_no']] ?? null; // If not found, store NULL

            // ✅ Prepare sales return data for batch insert
            $salesReturnDataBatch[] = [
                'company_id' => Auth::user()->company_id,
                'client_id' => $supplier->id,
                'name' => $record['supplier'],
                'sales_return_no' => $record['pi_no'] ?? 'Unknown',
                'sales_return_date' => $record['pi_date'] ?? now()->format('Y-m-d'),
                'sales_invoice_id' => $salesInvoiceId, // ✅ Store correct sales_invoice_id
                'remarks' => $record['remarks'] ?? null,
                'cgst' => !empty($taxData['cgst']) ? (float)$taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? (float)$taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? (float)$taxData['igst'] : 0,
                'total' => (float)$record['total'] ?? 0,
                'currency' => 'INR',
                'template' => optional(json_decode($record['pdf_template'], true))['id'] ?? null, // ✅ Use optional() helper
                'gross' => 0,
                'round_off' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // ✅ Batch Insert Sales Returns (Insert in Chunks)
        foreach (array_chunk($salesReturnDataBatch, 100) as $batch) {
            SalesReturnModel::insert($batch);
        }

        // ✅ Fetch inserted Sales Return IDs
        $salesReturns = SalesReturnModel::select('id', 'sales_return_no')
            ->whereIn('sales_return_no', array_column($salesReturnDataBatch, 'sales_return_no'))
            ->get();
        $salesReturnIds = $salesReturns->pluck('id', 'sales_return_no')->toArray();

        // ✅ Fetch all products in one query to prevent duplicate lookups
        $productNames = collect($data)
            ->pluck('items')
            ->map(fn($items) => json_decode($items, true)['product'] ?? [])
            ->flatten()
            ->unique();
        $products = ProductsModel::whereIn('name', $productNames)->get()->keyBy('name');

        $salesReturnProductsBatch = [];

        foreach ($data as $record) {
            $itemsData = json_decode($record['items'], true);
            if (!isset($salesReturnIds[$record['pi_no']]) || !is_array($itemsData)) continue;

            $salesReturnId = $salesReturnIds[$record['pi_no']];

            foreach ($itemsData['product'] as $i => $productName) {
                $product = $products[$productName] ?? null;
                if (!$product) {
                    $errors[] = ['record' => $record, 'error' => "Product not found: {$productName}"];
                    continue;
                }

                // Fetch godown_id for this item using the product's place value from itemsData
                $godownName = $itemsData['place'][$i] ?? 'Default Godown';
                $godown = GodownModel::where('name', $godownName)
                    ->where('company_id', Auth::user()->company_id)
                    ->first();
                $godownId = $godown ? $godown->id : null; // Use default if necessary

                // ✅ Prepare Sales Return Products for batch insert
                $salesReturnProductsBatch[] = [
                    'sales_return_id' => $salesReturnId,
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product->id,
                    'product_name' => $productName,
                    'description' => !empty($itemsData['desc'][$i]) ? $itemsData['desc'][$i] : 'No Desc',
                    'quantity' => (int)$itemsData['quantity'][$i] ?? 0,
                    'unit' => $itemsData['unit'][$i] ?? '',
                    'price' => (float)$itemsData['price'][$i] ?? 0,
                    'discount' => (float)$itemsData['discount'][$i] ?? 0,
                    'discount_type' => "percentage",
                    'hsn' => $itemsData['hsn'][$i] ?? '',
                    'tax' => (float)$itemsData['tax'][$i] ?? 0,
                    'cgst' => (float)($itemsData['cgst'][$i] ?? 0),
                    'sgst' => (float)($itemsData['sgst'][$i] ?? 0),
                    'igst' => 0,
                    'godown' => $godownId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // ✅ Batch Insert Sales Return Products (Insert in Chunks)
        foreach (array_chunk($salesReturnProductsBatch, 100) as $batch) {
            SalesReturnProductsModel::insert($batch);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
