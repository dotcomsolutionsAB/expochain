<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\SalesInvoiceModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\SalesInvoiceAddonsModel;
use App\Models\ClientsModel;
use App\Models\ClientsContactsModel;
use App\Models\ClientAddressModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use App\Http\Controllers\ResetController;
use Carbon\Carbon;
use Auth;
use DB;
use NumberFormatter;

class SalesInvoiceController extends Controller
{
    //
    // create
    public function add_sales_invoice(Request $request)
    {
        // Validate the request data
        $request->validate([
            // Sales Invoice
            'client_id' => 'required|integer|exists:t_clients,id',
            'sales_invoice_no' => 'required|string',
            'sales_invoice_date' => 'required|date_format:Y-m-d',
            'sales_order_id' => 'required|string|exists:t_sales_order,id',
            'sales_order_date' => 'required|date_format:Y-m-d',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            'cash' => 'required|in:0,1',
            'cgst' => 'required|numeric|min:0',
            'sgst' => 'required|numeric|min:0',
            'igst' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            
            // Products Array Validation
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer|min:0',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric|min:0',
            'products.*.cgst' => 'required|numeric|min:0',
            'products.*.sgst' => 'required|numeric|min:0',
            'products.*.igst' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',

            // Addons Array Validation
            'addons' => 'required|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|numeric',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric'
        ]);  

        // Fetch the client details using client_id
        $client = ClientsModel::find($request->input('client_id'));

        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'Sales Invoice',
            'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $quotation_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $sales_invoice_no = $request->input('sales_invoice_no');
        }

        $exists = SalesInvoiceModel::where('company_id', Auth::user()->company_id)
            ->where('sales_invoice_no', $sales_invoice_no)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and sales_invoice_no must be unique.',
            ], 422);
        }

        // Register the sales invoice
        $register_sales_invoice = SalesInvoiceModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'sales_invoice_date' => $currentDate,
            'sales_order_id' => $request->input('sales_order_id'),
            'sales_order_date' => $request->input('sales_order_date'),
            'template' => $request->input('template'),
            'contact_person' => $request->input('contact_person'),
            'cash' => $request->input('cash'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
        ]);

        // Process and insert products
        $products = $request->input('products');
        foreach ($products as $product) {
            // Create a record for the product
            SalesInvoiceProductsModel::create([
                'sales_invoice_id' => $register_sales_invoice->id,
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
                'amount' => $product['amount'],
                'channel' => $product['channel'],
            ]);
        }

        // Process and insert addons
        $addons = $request->input('addons');
        foreach ($addons as $addon) {
            SalesInvoiceAddonsModel::create([
                'sales_invoice_id' => $register_sales_invoice->id,
                'company_id' => Auth::user()->company_id,
                'name' => $addon['name'],
                'amount' => $addon['amount'],
                'tax' => $addon['tax'],
                'hsn' => $addon['hsn'],
                'cgst' => $addon['cgst'],
                'sgst' => $addon['sgst'],
                'igst' => $addon['igst'],
            ]);
        }

        unset($register_sales_invoice['id'], $register_sales_invoice['created_at'], $register_sales_invoice['updated_at']);

        $productIds = array_column($request->input('products'), 'product_id');

        // add to `reset table`
        foreach ($productIds as $reset_product) {
            $get_reset_product = new ResetController();

            $resetRequest = new \Illuminate\Http\Request([
                'product_id' => $reset_product,
            ]);

            $reset_response = ($get_reset_product->make_reset_queue($resetRequest))->getData()->message;

            // call `reset-controller` for `reset-calculation`
            $stockCalculationResponse  = $get_reset_product->stock_calculation($reset_product);
        }

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Sales Invoice registered successfully!',
            'data' => $register_sales_invoice,
            'total_cgst' => $total_cgst,
            'total_sgst' => $total_sgst,
            'total_igst' => $total_igst,
            'total_discount' => $total_discount,
            'total_amount' => $total_amount
        ], 201);
    }


    // View Sales Invoices
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }
    public function view_sales_invoice(Request $request)
    {
        // Get filter inputs
        $clientId = $request->input('client_id');
        $clientContactId = $request->input('client_contact_id');
        $name = $request->input('name');
        $salesInvoiceNo = $request->input('sales_invoice_no');
        $salesInvoiceDate = $request->input('sales_invoice_date');
        $salesOrderNo = $request->input('sales_order_no');
        $product = $request->input('product');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $user = $request->input('user');
        $productIds = $request->input('product_ids'); 
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Get total count of records in `t_sales_invoice`
        $total_sales_invoice = SalesInvoiceModel::count(); 

         // Build the query
        $query = SalesInvoiceModel::with([
            'products' => function ($query) {
                $query->select('sales_invoice_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', DB::raw('(tax / 2) as cgst_rate'), DB::raw('(tax / 2) as sgst_rate'), DB::raw('(tax) as igst_rate'), 'anount');
            },
            'addons' => function ($query) {
                $query->select('sales_invoice_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            },
            'get_user' => function ($query) { // Fetch user name and ID
                $query->select('id', 'name');
            }
        ])
        ->select('id', 'client_id', 'name', 'user', 'sales_invoice_no', 'sales_invoice_date', 'sales_order_id', 'sales_order_date', 'template', 'contact_person', 'cash', 'user', 'cgst', 'sgst', 'igst', 'total')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($clientId) {
            $query->where('client_id', $clientId);
        }
        if ($clientContactId) {
            $query->where('client_contact_id', $clientContactId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($salesInvoiceNo) {
            $query->where('sales_invoice_no', 'LIKE', '%' . $salesInvoiceNo . '%');
        }
        if ($salesInvoiceDate) {
            $query->whereDate('sales_invoice_date', $salesInvoiceDate);
        }
        if ($salesOrderNo) {
            $query->where('sales_order_no', 'LIKE', '%' . $salesOrderNo . '%');
        }

        // ✅ **Filter by comma-separated product IDs**
        if (!empty($productIds)) {
            $productIdArray = explode(',', $productIds); // Convert CSV to array
            $query->whereHas('products', function ($query) use ($productIdArray) {
                $query->whereIn('product_id', $productIdArray);
            });
        }      

        if ($dateFrom && $dateTo) {
            $query->whereBetween('sales_invoice_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->whereDate('sales_invoice_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('sales_invoice_date', '<=', $dateTo);
        }

        // 🔹 **Filter by Product Name or Product ID**
        if ($product) {
            $query->whereHas('products', function ($q) use ($product) {
                $q->where('product_name', 'LIKE', '%' . $product . '%')
                ->orWhere('product_id', $product);
            });
        }

        if ($user) {
            $query->where('user', $user);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_sales_invoices = $query->get();

        // Transform Data
        $get_sales_invoices->transform(function ($invoice) {

            // Convert total to words
            $invoice->amount_in_words = $this->convertNumberToWords($invoice->total);

            // ✅ Format total with comma-separated values
            $invoice->total = is_numeric($invoice->total) ? number_format((float) $invoice->total, 2) : $invoice->total;

            // Replace user ID with corresponding contact_person object
            $invoice->contact_person = isset($invoice->get_user) ? [
                'id' => $invoice->get_user->id,
                'name' => $invoice->get_user->name
            ] : ['id' => null, 'name' => 'Unknown'];

            // Convert user ID into an object with `id` and `name`
            $invoice->user = isset($invoice->get_user) ? [
                'id' => $invoice->get_user->id,
                'name' => $invoice->get_user->name
            ] : ['id' => null, 'name' => 'Unknown'];

            unset($invoice->get_user); // Remove original relationship data

            return $invoice;
        });

        // Return response
        return $get_sales_invoices->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Sales Invoices fetched successfully!',
                'data' => $get_sales_invoices,
                'count' => $get_sales_invoices->count(),
                'total_records' => $total_sales_invoice,
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Invoices found!',
            ], 404);
    }

    // Update Sales Invoice
    public function edit_sales_invoice(Request $request, $id)
    {
        $request->validate([
            // Sales Invoice
            'client_id' => 'required|integer|exists:t_clients,id',
            'sales_invoice_no' => 'required|string',
            'sales_invoice_date' => 'required|date_format:Y-m-d',
            'sales_order_id' => 'required|string|exists:t_sales_order,id',
            'sales_order_date' => 'required|date_format:Y-m-d',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            'cash' => 'required|in:0,1',
            'cgst' => 'required|numeric|min:0',
            'sgst' => 'required|numeric|min:0',
            'igst' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            
            // Products Array Validation
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer|min:0',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric|min:0',
            'products.*.cgst' => 'required|numeric|min:0',
            'products.*.sgst' => 'required|numeric|min:0',
            'products.*.igst' => 'required|numeric|min:0',
            'products.*.amount' => 'required|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',

            // Addons Array Validation
            'addons' => 'required|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|numeric',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric'
        ]);

        $salesInvoice = SalesInvoiceModel::where('id', $id)->first();

        $salesInvoiceUpdated = $salesInvoice->update([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'sales_invoice_date' => $currentDate,
            'sales_order_id' => $request->input('sales_order_id'),
            'sales_order_date' => $request->input('sales_order_date'),
            'template' => $request->input('template'),
            'contact_person' => $request->input('contact_person'),
            'cash' => $request->input('cash'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
        ]);

        // Handle Products
        $products = $request->input('products');
        $existingProductIDs = SalesInvoiceProductsModel::where('sales_invoice_id', $id)->pluck('product_id')->toArray();
        $requestProductIDs = [];

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesInvoiceProductsModel::where('sales_invoice_id', $productData['sales_invoice_id'])
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
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
                    'amount' => $productData['amount'],
                    'channel' => $productData['channel'],
                ]);
            } else {
                SalesInvoiceProductsModel::create([
                    'sales_invoice_id' => $productData['sales_invoice_id'],
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
                    'amount' => $productData['amount'],
                    'channel' => $productData['channel'],
                ]);
            }
        }

        // Process addons
        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = SalesInvoiceAddonsModel::where('sales_invoice_id', $addonData['sales_invoice_id'])
                                                    ->where('name', $addonData['name'])
                                                    ->first();

            if ($existingAddon) {
                $existingAddon->update([
                    'amount' => $addonData['amount'],
                    'tax' => $addonData['tax'],
                    'hsn' => $addonData['hsn'],
                    'cgst' => $addonData['cgst'],
                    'sgst' => $addonData['sgst'],
                    'igst' => $addonData['igst'],
                ]);
            } else {
                SalesInvoiceAddonsModel::create([
                    'sales_invoice_id' => $addonData['sales_invoice_id'],
                    'name' => $addonData['name'],
                    'amount' => $addonData['amount'],
                    'tax' => $addonData['tax'],
                    'hsn' => $addonData['hsn'],
                    'cgst' => $addonData['cgst'],
                    'sgst' => $addonData['sgst'],
                    'igst' => $addonData['igst'],
                ]);
            }
        }

        // Delete products not included in the request
        $productsDeleted = SalesInvoiceProductsModel::where('sales_invoice_id', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesInvoiceAddonsModel::where('sales_invoice_id', $id)
                                                ->where('name', $requestAddonIDs)
                                                ->delete();

        unset($salesInvoice['created_at'], $salesInvoice['updated_at']);

        return ($salesInvoiceUpdated || $productsDeleted || $addonsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Invoice, products, and addons updated successfully!', 'data' => $salesInvoice], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // Delete Sales Invoice
    public function delete_sales_invoice($id)
    {
        $delete_sales_invoice = SalesInvoiceModel::where('id', $id)
                                                    ->where('company_id', $company_id)
                                                    ->delete();

        $delete_sales_invoice_products = SalesInvoiceProductsModel::where('sales_invoice_id', $id)
                                                                    ->where('company_id', $company_id)
                                                                    ->delete();
                                                                    
        $delete_sales_invoice_addons = SalesInvoiceAddonsModel::where('sales_invoice_id', $id)
                                                                ->where('company_id', $company_id)
                                                                ->delete();

        return $delete_sales_invoice && $delete_sales_invoice_products && $delete_sales_invoice_addons
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Invoice and associated products/addons deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Sales Invoice not found.'], 404);
    }

    // import   
    public function importSalesInvoices()
    {
        set_time_limit(1200);

        // Clear existing data from SalesInvoice and related tables
        SalesInvoiceModel::truncate();
        SalesInvoiceProductsModel::truncate();
        SalesInvoiceAddonsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/sells_invoice.php';

        // Fetch data from the external URL
        try {
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
        $batchSize = 500; // Define batch size for optimal insertion

        // Chunk the data for batch processing
        foreach (array_chunk($data, $batchSize) as $chunk) {
            $salesInvoicesBatch = [];
            $productsBatch = [];
            $addonsBatch = [];

            foreach ($chunk as $record) {
                // Decode JSON fields for items, tax, and addons
                $itemsData = json_decode($record['items'] ?? '{}', true);
                $taxData = json_decode($record['tax'] ?? '{}', true);
                $addonsData = json_decode($record['addons'] ?? '{}', true);

                // Retrieve client and client contact IDs
                $client = ClientsModel::where('name', $record['client'])->first();
                if (!$client) {
                    $errors[] = [
                        'record' => $record,
                        'error' => 'Client not found for the provided name: ' . $record['client']
                    ];
                    continue;
                }

                $client_address_record = ClientAddressModel::select('address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country')
                    ->where('customer_id', $client->customer_id)
                    ->where('type', 'billing')
                    ->first();

                // Prepare sales invoice data for batch insert
                $salesInvoicesBatch[] = [
                    'company_id' => Auth::user()->company_id,
                    'client_id' => $client->id ?? null,
                    'name' => $record['client'] ?? 'Unnamed Client',
                    'user' => Auth::user()->id,
                    'sales_invoice_no' => !empty($record['si_no']) ? trim($record['si_no']) : null,
                    'sales_invoice_date' => $record['so_date'] ?? now(),
                    'sales_order_id' => !empty($record['so_no']) ? (int) $record['so_no'] : 0,
                    'cgst' => $taxData['cgst'] ?? 0,
                    'sgst' => $taxData['sgst'] ?? 0,
                    'igst' => $taxData['igst'] ?? 0,
                    'total' => $record['total'] ?? 0,
                    'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                    'commission' => !empty($record['commission']) ? (float) $record['commission'] : 0,
                    'cash' => !empty($record['cash']) ? (string) $record['cash'] : '0',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // **Batch insert sales invoices**
            if (!empty($salesInvoicesBatch)) {
                SalesInvoiceModel::insert($salesInvoicesBatch);
                $successfulInserts += count($salesInvoicesBatch);

                // Fetch inserted invoice IDs for mapping
                $insertedInvoices = SalesInvoiceModel::whereIn('sales_invoice_no', array_column($salesInvoicesBatch, 'sales_invoice_no'))
                    ->pluck('id', 'sales_invoice_no')
                    ->toArray();
            }

            // **Batch process products**
            foreach ($chunk as $record) {
                $salesInvoiceId = $insertedInvoices[$record['si_no']] ?? null;
                if (!$salesInvoiceId) continue;

                $itemsData = json_decode($record['items'] ?? '{}', true);

                if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
                    foreach ($itemsData['product'] as $index => $product) {
                        $productModel = ProductsModel::where('name', $product)->first();

                        if (!$productModel) {
                            $errors[] = [
                                'record' => $itemsData,
                                'error' => "Product with name '{$product}' not found."
                            ];
                            continue;
                        }

                        $productsBatch[] = [
                            'sales_invoice_id' => $salesInvoiceId,
                            'company_id' => Auth::user()->company_id,
                            'product_id' => $productModel->id,
                            'product_name' => $product,
                            'description' => $itemsData['desc'][$index] ?? '',
                            // 'brand' => $itemsData['group'][$index] ?? '',
                            // 'brand' => is_array($itemsData['group'] ?? '') ? json_encode($itemsData['group']) : ($itemsData['group'] ?? ''),
                            'quantity' => $itemsData['quantity'][$index] ?? 0,
                            'unit' => $itemsData['unit'][$index] ?? '',
                            'price' => isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0,
                            'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index])
                                ? (is_numeric($itemsData['channel'][$index])
                                    ? (float)$itemsData['channel'][$index]
                                    : (strtolower($itemsData['channel'][$index]) === 'standard' ? 1
                                        : (strtolower($itemsData['channel'][$index]) === 'non-standard' ? 2
                                            : (strtolower($itemsData['channel'][$index]) === 'cbs' ? 3 : null))))
                                : null,
                            'returned' => $itemsData['returned'][$index] ?? 0,
                            'profit' => $itemsData['profit'][$index] ?? 0.0,
                            'discount_type' => 'percentage',
                            'discount' => (float) ($itemsData['discount'][$index] ?? 0),
                            // 'so_no' => $itemsData['so_no'] ?? '',
                            // 'so_no' => is_array($itemsData['so_no'] ?? '') ? json_encode($itemsData['so_no']) : ($itemsData['so_no'] ?? ''),
                            'so_no' => isset($itemsData['so_no'][$index]) && is_array($itemsData['so_no'][$index])
                                ? (empty(array_filter($itemsData['so_no'][$index])) ? null : implode(', ', $itemsData['so_no'][$index]))
                                : (isset($itemsData['so_no'][$index]) ? trim($itemsData['so_no'][$index]) : null),
                            'hsn' => $itemsData['hsn'][$index] ?? '',
                            // 'tax' => $itemsData['tax'][$index] ?? 0,
                            'tax' => isset($itemsData['tax'][$index]) && is_numeric($itemsData['tax'][$index]) ? (float) $itemsData['tax'][$index] : 0,
                            'cgst' =>  isset($itemsData['cgst'][$index]) && is_numeric($itemsData['cgst'][$index]) ? (float) $itemsData['cgst'][$index] : 0,
                            'sgst' => isset($itemsData['sgst'][$index]) && is_numeric($itemsData['sgst'][$index]) ? (float) $itemsData['sgst'][$index] : 0,
                            'igst' => isset($itemsData['igst'][$index]) && is_numeric($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0,
                            'godown' => $itemsData['place'][$index] ?? '',
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }
            }

            // **Batch insert products**
            foreach (array_chunk($productsBatch, $batchSize) as $productChunk) {
                SalesInvoiceProductsModel::insert($productChunk);
            }

            // **Batch insert addons**
            foreach ($chunk as $record) {
                $salesInvoiceId = $insertedInvoices[$record['si_no']] ?? null;
                if (!$salesInvoiceId) continue;

                $addonsData = json_decode($record['addons'] ?? '{}', true);

                if ($addonsData) {
                    foreach ($addonsData as $name => $values) {
                        $addonsBatch[] = [
                            'sales_invoice_id' => $salesInvoiceId,
                            'company_id' => Auth::user()->company_id,
                            'name' => $name,
                            'amount' => (float) ($values['igst'] ?? 0),
                            'tax' => 18,
                            'hsn' => $values['hsn'] ?? '',
                            'cgst' => 0,
                            'sgst' => 0,
                            'igst' => (float) ($values['igst'] ?? 0),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }
            }

            foreach (array_chunk($addonsBatch, $batchSize) as $addonChunk) {
                SalesInvoiceAddonsModel::insert($addonChunk);
            }
        }

        return response()->json(['message' => "Sales invoices import completed with $successfulInserts successful inserts.", 'errors' => $errors], 200);
    }

}
