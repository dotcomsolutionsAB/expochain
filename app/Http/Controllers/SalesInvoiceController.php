<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Storage;
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
use App\Models\CounterModel;
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
            'sales_order_id' => 'nullable|string|exists:t_sales_order,id',
            'sales_order_date' => 'required|date_format:Y-m-d',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'sales_person' => 'required|integer|exists:users,id',
            'cash' => 'required|in:0,1',
            'cgst' => 'required|numeric|min:0',
            'sgst' => 'required|numeric|min:0',
            'igst' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',
            
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
            'products.*.godown' => 'nullable|exists:t_godown,id',

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
            'name' => 'sales_invoice',
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
                'code' => 422,
                'success' => false,
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
            'sales_person' => $request->input('sales_person'),
            'cash' => $request->input('cash'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                'godown' => $product['godown'],
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
                'hsn' =>  '99',
                'cgst' => $addon['cgst'],
                'sgst' => $addon['sgst'],
                'igst' => $addon['igst'],
            ]);
        }

        // increment the `next_number` by 1
        CounterModel::where('name', 'sales_invoice')
            ->where('company_id', Auth::user()->company_id)
            ->increment('next_number');

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

    public function view_sales_invoice(Request $request, $id = null)
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
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        // Query Sales Invoices
        $query = SalesInvoiceModel::with([
            'products' => function ($query) {
                $query->select('sales_invoice_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 
                    DB::raw('(tax / 2) as cgst_rate'), 
                    DB::raw('(tax / 2) as sgst_rate'), 
                    DB::raw('(tax) as igst_rate'), 
                    'amount', 'channel', 'godown', 'so_id', 'returned', 'profit', 'purchase_invoice_id', 'purchase_rate'
                );
            },
            'addons' => function ($query) {
                $query->select('sales_invoice_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            },
            'get_user:id,name',
            'client' => function ($q) {
                    // Only select the key columns needed for the join (ID and customer_id)
                    $q->select('id', 'customer_id')
                    ->with(['addresses' => function ($query) {
                        // Only fetch the customer_id (for joining) and the state field
                        $query->select('customer_id', 'state');
                    }]);
            }
        ])
        ->select('id', 'client_id', 'name', 'sales_invoice_no', 
            DB::raw('DATE_FORMAT(sales_invoice_date, "%d-%m-%Y") as sales_invoice_date'), 
            'user', 'sales_order_id', 
            DB::raw('DATE_FORMAT(sales_order_date, "%d-%m-%Y") as sales_order_date'), 
            'template', 'sales_person', 'cash', 'user', 'cgst', 'sgst', 'igst', 'total', 'gross', 'round_off'
        )
        ->where('company_id', Auth::user()->company_id);

        // 🔹 **Fetch Single Sales Invoice by ID**
        if ($id) {
            $salesInvoice = $query->where('id', $id)->first();
            if (!$salesInvoice) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Sales Invoice not found!',
                ], 404);
            }

            // Transform Single Sales Invoice
            $salesInvoice->amount_in_words = $this->convertNumberToWords($salesInvoice->total);
            $salesInvoice->total = is_numeric($salesInvoice->total) ? number_format((float) $salesInvoice->total, 2) : $salesInvoice->total;
            $salesInvoice->sales_person = $salesInvoice->get_user ? ['id' => $salesInvoice->get_user->id, 'name' => $salesInvoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            $salesInvoice->user = $salesInvoice->get_user ? ['id' => $salesInvoice->get_user->id, 'name' => $salesInvoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            unset($salesInvoice->get_user);

            // Transform client: Only return state from addresses
            if ($salesInvoice->client) {
                $state = optional($salesInvoice->client->addresses->first())->state;
                $salesInvoice->client = ['state' => $state];
            } else {
                $salesInvoice->client = null;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Sales Invoice fetched successfully!',
                'data' => $salesInvoice,
            ], 200);
        }

        // 🔹 **Apply Filters for Listing**
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
        if (!empty($productIds)) {
            $productIdArray = explode(',', $productIds);
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
        if ($product) {
            $query->whereHas('products', function ($q) use ($product) {
                $q->where('product_name', 'LIKE', '%' . $product . '%')
                ->orWhere('product_id', $product);
            });
        }
        if ($user) {
            $query->where('user', $user);
        }

        // Get total record count before applying limit
        $totalRecords = $query->count();
        $query->offset($offset)->limit($limit);

        // Fetch paginated results
        $get_sales_invoices = $query->get();

        if ($get_sales_invoices->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Invoices found!',
            ], 404);
        }

        // Transform Data
        $get_sales_invoices->transform(function ($invoice) {
            $invoice->amount_in_words = $this->convertNumberToWords($invoice->total);
            $invoice->total = is_numeric($invoice->total) ? number_format((float) $invoice->total, 2) : $invoice->total;
            $invoice->sales_person = $invoice->get_user ? ['id' => $invoice->get_user->id, 'name' => $invoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            $invoice->user = $invoice->get_user ? ['id' => $invoice->get_user->id, 'name' => $invoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            unset($invoice->get_user);

            // Transform client: Only return state from addresses for each invoice
            if ($invoice->client) {
                $state = optional($invoice->client->addresses->first())->state;
                $invoice->client = ['state' => $state];
            } else {
                $invoice->client = null;
            }

            return $invoice;
        });

        // Return response for list
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Sales Invoices fetched successfully!',
            'data' => $get_sales_invoices,
            'count' => $get_sales_invoices->count(),
            'total_records' => $totalRecords,
        ], 200);
    }

    // Update Sales Invoice
    public function edit_sales_invoice(Request $request, $id)
    {
        $request->validate([
            // Sales Invoice
            'client_id' => 'required|integer|exists:t_clients,id',
            'name' => 'required|string|exists:t_clients,name',
            'sales_invoice_no' => 'required|string',
            'sales_invoice_date' => 'required|date_format:Y-m-d',
            'sales_order_id' => 'required|string|exists:t_sales_order,id',
            'sales_order_date' => 'required|date_format:Y-m-d',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'sales_person' => 'required|integer|exists:users,id',
            'cash' => 'required|in:0,1',
            'cgst' => 'required|numeric|min:0',
            'sgst' => 'required|numeric|min:0',
            'igst' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',
            
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
            'products.*.godown' => 'nullable|exists:t_godown,id',

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
            'name' => $request->input('name'),
            'sales_invoice_no' => $request->input('sales_invoice_no'),
            'sales_invoice_date' => $currentDate,
            'sales_order_id' => $request->input('sales_order_id'),
            'sales_order_date' => $request->input('sales_order_date'),
            'template' => $request->input('template'),
            'sales_person' => $request->input('sales_person'),
            'cash' => $request->input('cash'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                    'godown' => $productData['godown'],
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
                    'godown' => $productData['godown'],
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

                // Gross calculation
                $gross = 0;
                if (!empty($itemsData['product'])) {
                    foreach ($itemsData['product'] as $index => $productName) {
                        $qty = isset($itemsData['quantity'][$index]) ? (float)$itemsData['quantity'][$index] : 0;
                        $price = isset($itemsData['price'][$index]) ? (float)$itemsData['price'][$index] : 0;
                        $gross += $qty * $price;
                    }
                }

                // Roundoff (if available in addons)
                $roundoff = isset($addonsData['roundoff']) ? (float)$addonsData['roundoff'] : 0;

                $client_address_record = ClientAddressModel::select('address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country')
                    ->where('customer_id', $client->customer_id)
                    ->where('type', 'billing')
                    ->first();

                // Prepare sales invoice data for batch insert
                $salesInvoicesBatch[] = [
                    'company_id' => Auth::user()->company_id,
                    'client_id' => $client->id ?? null,
                    'name' => $record['client'] ?? 'Unnamed Client',
                    'sales_invoice_no' => !empty($record['si_no']) ? trim($record['si_no']) : null,
                    'sales_invoice_date' => $record['si_date'] ?? now(),
                    // 'sales_order_id' => !empty($record['so_no']) ? (int) $record['so_no'] : 0,
                    'sales_order_id' => isset($record['so_no']) 
                    ? (
                        is_array($record['so_no']) 
                            ? (empty(array_filter($record['so_no'])) ? null : implode(', ', array_filter($record['so_no'])))
                            : (!empty($record['so_no']) ? (int) $record['so_no'] : null)
                    )
                    : null,
                    'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                    'cash' => !empty($record['cash']) ? (string) $record['cash'] : '0',
                    'user' => Auth::user()->id,
                    'cgst' => $taxData['cgst'] ?? 0,
                    'sgst' => $taxData['sgst'] ?? 0,
                    'igst' => $taxData['igst'] ?? 0,
                    'total' => $record['total'] ?? 0,
                    'gross' => $gross,
                    'round_off' => $roundoff,
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
                            'discount' => (float) ($itemsData['discount'][$index] ?? 0),
                            'discount_type' => 'percentage',
                            'hsn' => $itemsData['hsn'][$index] ?? '',
                            // 'tax' => $itemsData['tax'][$index] ?? 0,
                            'tax' => isset($itemsData['tax'][$index]) && is_numeric($itemsData['tax'][$index]) ? (float) $itemsData['tax'][$index] : 0,
                            'cgst' =>  isset($itemsData['cgst'][$index]) && is_numeric($itemsData['cgst'][$index]) ? (float) $itemsData['cgst'][$index] : 0,
                            'sgst' => isset($itemsData['sgst'][$index]) && is_numeric($itemsData['sgst'][$index]) ? (float) $itemsData['sgst'][$index] : 0,
                            'igst' => isset($itemsData['igst'][$index]) && is_numeric($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0,
                            'amount' => (
                                (isset($itemsData['quantity'][$index]) ? (float) $itemsData['quantity'][$index] : 0.0) *
                                (
                                    (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0) -
                                    (
                                        ((isset($itemsData['discount'][$index]) ? (float) $itemsData['discount'][$index] : 0.0) *
                                        (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0)) / 100
                                    )
                                )
                            ) + (
                                (isset($itemsData['cgst'][$index]) ? (float) $itemsData['cgst'][$index] : 0.0) +
                                (isset($itemsData['sgst'][$index]) ? (float) $itemsData['sgst'][$index] : 0.0) +
                                (isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0.0)
                            ),
                            'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index])
                                ? (is_numeric($itemsData['channel'][$index])
                                    ? (float)$itemsData['channel'][$index]
                                    : (strtolower($itemsData['channel'][$index]) === 'standard' ? 1
                                        : (strtolower($itemsData['channel'][$index]) === 'non-standard' ? 2
                                            : (strtolower($itemsData['channel'][$index]) === 'cbs' ? 3 : null))))
                                : null,
                            'godown' => isset($itemsData['place'][$index])
                            ? (
                                strtoupper(trim($itemsData['place'][$index])) === 'OFFICE' ? 1 :
                                (strtoupper(trim($itemsData['place'][$index])) === 'KUSHTIA' ? 2 :
                                (strtoupper(trim($itemsData['place'][$index])) === 'ANKURHATI' ? 3 : null))
                            )
                            : null,
                           'so_id' => isset($itemsData['so_no'][$index]) 
                            ? (is_numeric($itemsData['so_no'][$index]) ? (int)$itemsData['so_no'][$index] : null)
                            : null,
                            'returned' => $itemsData['returned'][$index] ?? 0,
                            'profit' => $itemsData['profit'][$index] ?? 0.0,
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

        return response()->json(['code' => 200, 'success' => true, 'message' => "Sales invoices import completed with $successfulInserts successful inserts.", 'errors' => $errors], 200);
    }

    // export sales invoice report
    public function exportSalesInvoiceReport(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // Eager load relationships with joins to client and product group
            // $invoices = SalesInvoiceProductsModel::with([
            //     'salesInvoice' => function ($q) use ($companyId, $startDate, $endDate) {
            //         $q->where('company_id', $companyId)
            //         ->whereBetween('sales_invoice_date', [$startDate, $endDate])
            //         ->with('client:id,name');
            //     },
            //     'product.groupRelation:id,name'
            // ])->get();

            // Parse optional filters
            $clientIds = $request->filled('client_id') ? array_map('intval', explode(',', $request->client_id)) : null;
            $productIds = $request->filled('product_id') ? array_map('intval', explode(',', $request->product_id)) : null;
            $groupIds = $request->filled('group_id') ? array_map('intval', explode(',', $request->group_id)) : null;
            $categoryIds = $request->filled('category_id') ? array_map('intval', explode(',', $request->category_id)) : null;
            $subCategoryIds = $request->filled('sub_category_id') ? array_map('intval', explode(',', $request->sub_category_id)) : null;

            // Build query with relations and filters
            $query = SalesInvoiceProductsModel::with([
                'salesInvoice.client:id,name',
                'product.groupRelation:id,name'
            ])
            ->whereHas('salesInvoice', function ($q) use ($companyId, $startDate, $endDate, $clientIds) {
                $q->where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$startDate, $endDate]);

                if ($clientIds) {
                    $q->whereIn('client_id', $clientIds);
                }
            });

            if ($productIds) {
                $query->whereIn('product_id', $productIds);
            }

            if ($groupIds || $categoryIds || $subCategoryIds) {
                $query->whereHas('product', function ($q) use ($groupIds, $categoryIds, $subCategoryIds) {
                    if ($groupIds) {
                        $q->whereIn('group', $groupIds);
                    }
                    if ($categoryIds) {
                        $q->whereIn('category', $categoryIds);
                    }
                    if ($subCategoryIds) {
                        $q->whereIn('sub_category', $subCategoryIds);
                    }
                });
            }

            $items = $query->get();

            // Filter only those with invoices in date range
            $filtered = $items->filter(fn ($item) => $item->salesInvoice !== null);

            // Build export data
            $exportData = [];
            $sn = 1;
            foreach ($filtered as $item) {
                $exportData[] = [
                    'SN' => $sn++,
                    'Client' => $item->salesInvoice->client->name ?? 'N/A',
                    'Invoice' => $item->salesInvoice->sales_invoice_no,
                    'Date' => Carbon::parse($item->salesInvoice->sales_invoice_date)->format('d-m-Y'),
                    'Item Name' => $item->product_name,
                    'Group' => $item->product->groupRelation->name ?? 'N/A',
                    'Quantity' => $item->quantity,
                    'Unit' => $item->unit,
                    'Price' => $item->price,
                    'Discount' => $item->discount,
                    'Amount' => $item->amount,
                    'Added On' => Carbon::parse($item->created_at)->format('d-m-Y H:i'),
                    'Profit' => $item->profit
                ];
            }

            if (empty($exportData)) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No sales invoice products found in the given range.'
                ]);
            }

            // Generate dynamic filename
            $timestamp = now()->format('Ymd_His');
            $fileName = "sales_invoices_export_{$timestamp}.xlsx";
            $relativePath = "uploads/sales_invoices_report/{$fileName}";
            $fullPath = storage_path("app/public/{$relativePath}");

            // Store Excel using inline export class
            Excel::store(new class($exportData) implements FromCollection, WithHeadings {
                private $data;
                public function __construct($data)
                {
                    $this->data = $data;
                }
                public function collection()
                {
                    return collect($this->data);
                }
                public function headings(): array
                {
                    return [
                        'SN', 'Client', 'Invoice', 'Date', 'Item Name', 'Group',
                        'Quantity', 'Unit', 'Price', 'Discount', 'Amount',
                        'Added On', 'Profit'
                    ];
                }
            }, $relativePath, 'public');

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'File available for download',
                'data' => [
                    'file_url' => asset("storage/{$relativePath}"),
                    'file_name' => $fileName,
                    'file_size' => Storage::disk('public')->size($relativePath),
                    'content_type' => 'Excel'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while generating Excel.',
                'error' => $e->getMessage()
            ]);
        }
    }

    // fetch by product id
    public function fetchSalesByProduct($productId)
    {
        try {
            $companyId = Auth::user()->company_id;

            $sales = SalesInvoiceProductsModel::with([
                    'salesInvoice:id,sales_invoice_no,sales_invoice_date,client_id',
                    'salesInvoice.client:id,name',
                    'godownRelation:id,name',
                ])
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->select('sales_invoice_id', 'product_id', 'quantity', 'price', 'amount', 'profit', 'godown')
                ->get()
                ->map(function ($item) {
                    return [
                        'invoice' => optional($item->salesInvoice)->sales_invoice_no,
                        'date' => optional($item->salesInvoice)->sales_invoice_date,
                        'client' => optional($item->salesInvoice->client)->name,
                        'qty' => $item->quantity,
                        'price' => $item->price,
                        'amount' => $item->amount,
                        'profit' => $item->profit,
                        'place' => optional($item->godownRelation)->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Sales records fetched successfully.',
                'data' => $sales,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching sales: ' . $e->getMessage(),
            ], 500);
        }
    }


}
