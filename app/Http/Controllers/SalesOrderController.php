<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\SalesOrderAddonsModel;
use App\Models\ClientsModel;
use App\Models\ClientContactsModel;
use App\Models\ClientAddressModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Storage;
use DB;
use NumberFormatter;

class SalesOrderController extends Controller
{
    //
    // create
    public function add_sales_order(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'sales_order_no' => 'required|string|unique:t_sales_order,sales_order_no',
            'sales_order_date' => 'required|date_format:Y-m-d',
            'ref_no' => 'required|string',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
        
            // for products 
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.description' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit' => 'required|string|max:20',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'nullable|string',
            'products.*.tax' => 'nullable|numeric|min:0',
            'products.*.cgst' => 'nullable|numeric|min:0',
            'products.*.sgst' => 'nullable|numeric|min:0',
            'products.*.igst' => 'nullable|numeric|min:0',
            'products.*.amount' => 'nullable|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',
            
            // for add-ons 
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0'        
        ]);

        // Fetch the client details using client_id
        $client = ClientsModel::find($request->input('client_id'));

        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'Sales Order',
            'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $sales_order_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $sales_order_no = $request->input('sales_order_no');
        }

        $exists = SalesOrderModel::where('company_id', Auth::user()->company_id)
            ->where('sales_order_no', $sales_order_no)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and sales_order_no must be unique.',
            ], 422);
        }

        // Register the sales order
        $register_sales_order = SalesOrderModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $request->input('sales_order_date'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'contact_person' => $request->input('contact_person'),
            'status' => "pending",
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
        ]);

         // **Step 2: Register Sales Order Products**
        $products = $validatedData['products'];
        foreach ($products as $product) {
            // Create a record for the product
            SalesOrderProductsModel::create([
                'sales_order_id' => $register_sales_order->id,
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
                'cgst' =>$product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
                'amount' => $product['amount'],
                'channel' => $product['channel'],
            ]);
        }

        // Process and insert addons
        // **Step 3: Register Sales Order Add-ons**
        $addons = $validatedData['addons'] ?? [];
        foreach ($addons as $addon) {
            SalesOrderAddonsModel::create([
                'sales_order_id' => $register_sales_order->id,
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

        unset($register_sales_order['id'], $register_sales_order['created_at'], $register_sales_order['updated_at']);

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Sales Order registered successfully!',
            'data' => $register_sales_order,
        ], 201);
    }

    // View Sales Orders
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }

    public function view_sales_order(Request $request)
    {
        // Get filter inputs
        $clientId = $request->input('client_id');
        $clientContactId = $request->input('client_contact_id');
        $name = $request->input('name');
        $salesOrderNo = $request->input('sales_order_no');
        $salesOrderDate = $request->input('sales_order_date');
        $user = $request->input('user'); // New filter for user
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->input('status');
        $productIds = $request->input('product_ids'); 
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Get total count of records in `t_sales_order`
        $total_sales_order = SalesOrderModel::count(); 

        // Build the query
        $query = SalesOrderModel::with([
            'products' => function ($query) {
                $query->select(
                    'sales_order_id', 'product_id', 'product_name', 'description',
                    'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst', DB::raw('(tax / 2) as cgst_rate'), DB::raw('(tax / 2) as sgst_rate'), DB::raw('(tax) as igst_rate'), 'amount', 'channel', 'sent', 'short_closed', 
                )->with(['channel' => function ($channelQuery) {
                    $channelQuery->select('id', 'name'); // Fetch channel name
                }]);
            },
            'addons' => function ($query) {
                $query->select('sales_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            },
            'get_user' => function ($query) { // Fetch only user name
                $query->select('id', 'name');
            },
        ])
        ->select('id', 'client_id', 'name', 'sales_order_no', 'sales_order_date', 'ref_no', 'template', 'contact_person', 'status', 'user', 'cgst', 'sgst', 'igst', 'total')
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
        if ($salesOrderNo) {
            $query->where('sales_order_no', 'LIKE', '%' . $salesOrderNo . '%');
        }
        if ($salesOrderDate) {
            $query->whereDate('sales_order_date', $salesOrderDate);
        }
        if ($status) {
            $query->where('status', $status);
        }
        // ✅ **Filter by comma-separated statuses**
        if (!empty($status)) {
            $statusArray = explode(',', $status); // Convert CSV to array
            $query->whereIn('status', $statusArray);
        }

        // ✅ **Filter by comma-separated product IDs**
        if (!empty($productIds)) {
            $productIdArray = explode(',', $productIds); // Convert CSV to array
            $query->whereHas('products', function ($query) use ($productIdArray) {
                $query->whereIn('product_id', $productIdArray);
            });
        }      
    
        // Apply Date Range Filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('sales_order_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->whereDate('sales_order_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('sales_order_date', '<=', $dateTo);
        }
    

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_sales_orders = $query->get();

        // Transform Data
        $get_sales_orders->transform(function ($order) {

            // Format total with comma-separated values (Indian numbering system)
            $order->total = is_numeric($order->total) ? number_format((float) $order->total, 2) : $order->total;

            // Convert total to words
            $order->amount_in_words = $this->convertNumberToWords($order->total);

            // Capitalize the first letter of status
            $order->status = ucfirst($order->status);

            // Replace user ID with corresponding contact_person object
            $order->contact_person = isset($order->get_user) ? [
                'id' => $order->get_user->id,
                'name' => $order->get_user->name
            ] : ['id' => null, 'name' => 'Unknown'];

            // Replace user ID with corresponding user object
            $order->user = isset($order->get_user) ? [
                'id' => $order->get_user->id,
                'name' => $order->get_user->name
            ] : ['id' => null, 'name' => 'Unknown'];

            unset($order->get_user); // Remove raw user data

            return $order;
        });

        // Return response
        return $get_sales_orders->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Sales Orders fetched successfully!',
                'data' => $get_sales_orders,
                'fetch_records' => $get_sales_orders->count(),
                'count' => $total_sales_order,
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Orders found!',
            ], 404);
    }

    // Update Sales Order
    public function edit_sales_order(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'name' => 'required|string',
            'sales_order_no' => 'required|integer',
            'sales_order_date' => 'required|date',
            'ref_no' => 'required|string',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'contact_person' => 'required|integer|exists:users,id',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',

            // for products 
            'products' => 'required|array',
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
            'products.*.igst' => 'required|numeric',
            'products.*.amount' => 'nullable|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',

            // for addons 
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
        ]);

        $salesOrder = SalesOrderModel::where('id', $id)->first();

        if (!$salesOrder) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Sales Order not found!'], 404);
        }

        $exists = SalesOrderModel::where('company_id', Auth::user()->company_id)
            ->where('sales_order_no', $request->input('sales_order_no'))
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and sales_order_no must be unique.',
            ], 422);
        }

        $salesOrderUpdated = $salesOrder->update([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'user' => Auth::user()->id,
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $request->input('sales_order_date'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'contact_person' => $request->input('contact_person'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesOrderProductsModel::where('sales_order_id', $id)
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
                SalesOrderProductsModel::create([
                    'sales_order_id' => $id,
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
                    'amount' => $productData['amount'],
                    'channel' => $productData['channel'],
                ]);
            }
        }

        // Process addons
        $addons = $request->input('addons');
        $requestAddonNames = [];

        foreach ($addons as $addonData) {
            $requestAddonNames[] = $addonData['name'];

            $existingAddon = SalesOrderAddonsModel::where('sales_order_id', $id)
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
                SalesOrderAddonsModel::create([
                    'sales_order_id' => $id,
                    'name' => $addonData['name'],
                    'company_id' => Auth::user()->company_id,
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
        $productsDeleted = SalesOrderProductsModel::where('sales_order_id', $id)
                                                ->whereNotIn('product_id', $requestProductIDs)
                                                ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesOrderAddonsModel::where('sales_order_id', $id)
                                            ->whereNotIn('name', $requestAddonNames)
                                            ->delete();

        return ($salesOrderUpdated || $productsDeleted || $addonsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Order, products, and addons updated successfully!', 'data' => $salesOrder], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // Delete Sales Order
    public function delete_sales_order($id)
    {
        $company_id = Auth::user()->company_id;

        $delete_sales_order = SalesOrderModel::where('id', $id)
                                                ->where('company_id', $company_id)
                                                ->delete();

        $delete_sales_order_products = SalesOrderProductsModel::where('sales_order_id', $id)
                                                                ->where('company_id', $company_id)
                                                                ->delete();

        $delete_sales_order_addons = SalesOrderAddonsModel::where('sales_order_id', $id)
                                                            ->where('company_id', $company_id)
                                                            ->delete();

        return $delete_sales_order && $delete_sales_order_products && $delete_sales_order_addons
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Order and associated products/addons deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Sales Order not found.'], 404);
    }

    // migrate
    // public function importSalesOrders()
    // {
    //     set_time_limit(300); // Prevent timeout for large imports

    //     // Clear old data before import
    //     SalesOrderModel::truncate();
    //     SalesOrderProductsModel::truncate();
    //     SalesOrderAddonsModel::truncate();

    //     $url = 'https://expo.egsm.in/assets/custom/migrate/sells_order.php';

    //     // Fetch data from the external URL
    //     try {
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
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

    //     // **Batch size limit**
    //     $batchSize = 50;

    //     // Initialize batch arrays
    //     $salesOrdersBatch = [];
    //     $productsBatch = [];
    //     $addonsBatch = [];

    //     foreach ($data as $record) {
    //         // Decode JSON fields
    //         $itemsData = json_decode($record['items'] ?? '{}', true);
    //         $taxData = json_decode($record['tax'] ?? '{}', true);
    //         $addonsData = json_decode($record['addons'] ?? '{}', true);

    //         // Retrieve client details
    //         $client = ClientsModel::where('name', $record['client'])->first();
    //         if (!$client) {
    //             $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
    //             continue;
    //         }

    //         $clientContact = ClientContactsModel::where('customer_id', $client->customer_id)->first();
    //         if (!$clientContact) {
    //             $errors[] = ['record' => $record, 'error' => 'Client contact not found for customer ID: ' . $client->customer_id];
    //             continue;
    //         }

    //         // Mapping status integer values to their corresponding enum values
    //         $statusMapping = [
    //             1 => 'pending',
    //             2 => 'partial',
    //             3 => 'completed'
    //         ];

    //         // Prepare sales order data
    //         $salesOrdersBatch[] = [
    //             'company_id' => Auth::user()->company_id,
    //             'client_id' => $client->id ?? null,
    //             'client_contact_id' => $clientContact->id ?? null,
    //             'name' => $record['client'] ?? 'Unnamed Client',
    //             'address_line_1' => $client->address_line_1 ?? null,
    //             'address_line_2' => $client->address_line_2 ?? null,
    //             'city' => $client->city ?? null,
    //             'pincode' => $client->pincode ?? null,
    //             'state' => $client->state ?? null,
    //             'country' => $client->country ?? null,
    //             'user' => Auth::user()->id,
    //             'sales_order_no' => $record['so_no'] ?? 'Unknown',
    //             'sales_order_date' => date('Y-m-d', strtotime($record['so_date'] ?? now())), // Ensure correct format
    //             'ref_no' => $record['ref_no'] ?? 0,
    //             'cgst' => (float)($taxData['cgst'] ?? 0),
    //             'sgst' => (float)($taxData['sgst'] ?? 0),
    //             'igst' => (float)($taxData['igst'] ?? 0),
    //             'total' => (float)($record['total'] ?? 0),
    //             'currency' => 'INR',
    //             'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
    //             // 'status' => $record['status'] ?? 1,
    //             // Assign status based on integer mapping; default to 'pending' if not found
    //             'status' => isset($record['status']) && isset($statusMapping[$record['status']]) 
    //             ? $statusMapping[$record['status']] 
    //             : 'pending',
    //             'created_at' => now(),
    //             'updated_at' => now()
    //         ];

    //         // Temporary Sales Order ID for mapping products
    //         $salesOrderId = count($salesOrdersBatch);

    //         // Process products
    //         if (!empty($itemsData['product'])) {
    //             foreach ($itemsData['product'] as $index => $product) {
    //                 $get_product = ProductsModel::where('name', $product)->first();

    //                 if (!$get_product) {
    //                     $errors[] = ['record' => $itemsData, 'error' => "Product not found: '{$product}'"];
    //                     continue;
    //                 }

    //                 $productsBatch[] = [
    //                     'sales_order_id' => $salesOrderId,
    //                     'company_id' => Auth::user()->company_id,
    //                     'product_id' => $get_product->id,
    //                     'product_name' => $product,
    //                     'description' => $itemsData['desc'][$index] ?? '',
    //                     'group' => $itemsData['brand'][$index] ?? '',
    //                     'quantity' => is_numeric($itemsData['quantity'][$index]) ? (int)$itemsData['quantity'][$index] : 0,
    //                     'sent' => is_numeric($itemsData['sent'][$index]) ? (int)$itemsData['sent'][$index] : 0,
    //                     'unit' => $itemsData['unit'][$index] ?? '',
    //                     'price' => is_numeric($itemsData['price'][$index]) ? (float)$itemsData['price'][$index] : 0,
    //                     'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index]) 
    //                         ? (
    //                             is_numeric($itemsData['channel'][$index]) 
    //                                 ? (float)$itemsData['channel'][$index] 
    //                                 : (
    //                                     strtolower($itemsData['channel'][$index]) === 'standard' ? 1 :
    //                                     (strtolower($itemsData['channel'][$index]) === 'non-standard' ? 2 :
    //                                     (strtolower($itemsData['channel'][$index]) === 'cbs' ? 3 : null))
    //                                 )
    //                         ) 
    //                         : null,
    //                     'discount_type' => 'percentage',
    //                     'discount' => is_numeric($itemsData['discount'][$index]) ? (float)$itemsData['discount'][$index] : 0,
    //                     'hsn' => $itemsData['hsn'][$index] ?? '',
    //                     'tax' => is_numeric($itemsData['tax'][$index]) ? (float)$itemsData['tax'][$index] : 0,
    //                     'cgst' => (float)($itemsData['cgst'][$index] ?? 0),
    //                     'sgst' => (float)($itemsData['sgst'][$index] ?? 0),
    //                     'igst' => (float)($itemsData['igst'][$index] ?? 0),
    //                     'created_at' => now(),
    //                     'updated_at' => now()
    //                 ];
    //             }
    //         }

    //         // Process addons
    //         if (!empty($addonsData)) {
    //             foreach ($addonsData as $name => $values) {
    //                 $addonsBatch[] = [
    //                     'sales_order_id' => $salesOrderId,
    //                     'company_id' => Auth::user()->company_id,
    //                     'name' => $name,
    //                     'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
    //                     'tax' => 18,
    //                     'hsn' => $values['hsn'] ?? '',
    //                     'cgst' => (float)($values['cgst'] ?? 0),
    //                     'sgst' => (float)($values['sgst'] ?? 0),
    //                     'igst' => (float)($values['igst'] ?? 0),
    //                     'created_at' => now(),
    //                     'updated_at' => now()
    //                 ];
    //             }
    //         }

    //         // Batch insert when batch size reaches limit
    //         if (count($salesOrdersBatch) >= $batchSize) {
    //             SalesOrderModel::insert($salesOrdersBatch);
    //             $salesOrdersBatch = [];
    //         }

    //         if (count($productsBatch) >= $batchSize) {
    //             SalesOrderProductsModel::insert($productsBatch);
    //             $productsBatch = [];
    //         }

    //         if (count($addonsBatch) >= $batchSize) {
    //             SalesOrderAddonsModel::insert($addonsBatch);
    //             $addonsBatch = [];
    //         }
    //     }

    //     // Insert remaining records after loop completes
    //     if (!empty($salesOrdersBatch)) SalesOrderModel::insert($salesOrdersBatch);
    //     foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
    //         SalesOrderProductsModel::insert($chunk);
    //     }
    //     foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
    //         SalesOrderAddonsModel::insert($chunk);
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Sales orders import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    public function importSalesOrders()
    {
        set_time_limit(300); // Prevent timeout for large imports

        // Clear old data before import
        SalesOrderModel::truncate();
        SalesOrderProductsModel::truncate();
        SalesOrderAddonsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/sells_order.php';

        // Fetch data from external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
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
        $batchSize = 50;

        // **Step 1️⃣: Insert Sales Orders & Fetch IDs**
        $salesOrdersBatch = [];
        foreach ($data as $record) {
            // Retrieve client details
            $client = ClientsModel::where('name', $record['client'])->first();
            if (!$client) {
                $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
                continue;
            }

            // $clientContact = ClientContactsModel::where('customer_id', $client->customer_id)->first();
            // if (!$clientContact) {
            //     $errors[] = ['record' => $record, 'error' => 'Client contact not found for customer ID: ' . $client->customer_id];
            //     continue;
            // }

            // Map status
            $statusMapping = [1 => 'pending', 2 => 'partial', 3 => 'completed'];
            $salesOrdersBatch[] = [
                'company_id' => Auth::user()->company_id,
                'client_id' => $client->id,
                // 'client_contact_id' => $clientContact->id,
                'name' => $record['client'],
                // 'address_line_1' => $client->address_line_1 ?? null,
                // 'address_line_2' => $client->address_line_2 ?? null,
                // 'city' => $client->city ?? null,
                // 'pincode' => $client->pincode ?? null,
                // 'state' => $client->state ?? null,
                // 'country' => $client->country ?? null,
                'user' => Auth::user()->id,
                'sales_order_no' => $record['so_no'],
                'sales_order_date' => date('Y-m-d', strtotime($record['so_date'] ?? now())),
                'ref_no' => $record['ref_no'] ?? null,
                'cgst' => (float)($record['tax']['cgst'] ?? 0),
                'sgst' => (float)($record['tax']['sgst'] ?? 0),
                'igst' => (float)($record['tax']['igst'] ?? 0),
                'total' => (float)($record['total'] ?? 0),
                'currency' => 'INR',
                'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                'status' => $statusMapping[$record['status']] ?? 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // **Insert Sales Orders**
        foreach (array_chunk($salesOrdersBatch, $batchSize) as $chunk) {
            SalesOrderModel::insert($chunk);
        }

        // **Fetch Inserted Sales Order IDs**
        $salesOrderIds = SalesOrderModel::whereIn('sales_order_no', array_column($salesOrdersBatch, 'sales_order_no'))
            ->pluck('id', 'sales_order_no')
            ->toArray();

        // **Step 2️⃣: Insert Products**
        $productsBatch = [];
        foreach ($data as $record) {
            $salesOrderId = $salesOrderIds[$record['so_no']] ?? null;
            if (!$salesOrderId) {
                continue; // Skip if sales order is not found
            }

            $itemsData = json_decode($record['items'] ?? '{}', true);

            if (!empty($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {
                    $get_product = ProductsModel::where('name', $product)->first();
                    if (!$get_product) {
                        $errors[] = ['record' => $itemsData, 'error' => "Product not found: '{$product}'"];
                        continue;
                    }

                    $productsBatch[] = [
                        'sales_order_id' => $salesOrderId,
                        'company_id' => Auth::user()->company_id,
                        'product_id' => $get_product->id,
                        'product_name' => $product,
                        'description' => $itemsData['desc'][$index] ?? '',
                        // 'group' => $itemsData['brand'][$index] ?? '',
                        'quantity' => is_numeric($itemsData['quantity'][$index]) ? (int)$itemsData['quantity'][$index] : 0,
                        'sent' => is_numeric($itemsData['sent'][$index]) ? (int)$itemsData['sent'][$index] : 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => is_numeric($itemsData['price'][$index]) ? (float)$itemsData['price'][$index] : 0,
                        'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index]) 
                                ? (
                                    is_numeric($itemsData['channel'][$index]) 
                                        ? (float)$itemsData['channel'][$index] 
                                        : (
                                            strtolower($itemsData['channel'][$index]) === 'standard' ? 1 :
                                            (strtolower($itemsData['channel'][$index]) === 'non-standard' ? 2 :
                                            (strtolower($itemsData['channel'][$index]) === 'cbs' ? 3 : null))
                                        )
                                ) 
                                : null,
                        'discount_type' => 'percentage',
                        'discount' => is_numeric($itemsData['discount'][$index]) ? (float)$itemsData['discount'][$index] : 0,
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => is_numeric($itemsData['tax'][$index]) ? (float)$itemsData['tax'][$index] : 0,
                        'cgst' => (float)($itemsData['cgst'][$index] ?? 0),
                        'sgst' => (float)($itemsData['sgst'][$index] ?? 0),
                        'igst' => (float)($itemsData['igst'][$index] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        // **Insert Products in Batches**
        foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
            SalesOrderProductsModel::insert($chunk);
        }

        // **Step 3️⃣: Insert Addons**
        $addonsBatch = [];
        foreach ($data as $record) {
            $salesOrderId = $salesOrderIds[$record['so_no']] ?? null;
            if (!$salesOrderId) {
                continue;
            }

            $addonsData = json_decode($record['addons'] ?? '{}', true);

            if (!empty($addonsData)) {
                foreach ($addonsData as $name => $values) {
                    $addonsBatch[] = [
                        'sales_order_id' => $salesOrderId,
                        'company_id' => Auth::user()->company_id,
                        'name' => $name,
                        'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
                        'tax' => 18,
                        'hsn' => $values['hsn'] ?? '',
                        'cgst' => (float)($values['cgst'] ?? 0),
                        'sgst' => (float)($values['sgst'] ?? 0),
                        'igst' => (float)($values['igst'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }

        foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
            SalesOrderAddonsModel::insert($chunk);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Sales orders import completed successfully.",
            'errors' => $errors,
        ], 200);
    }


    // export
    public function export_sales_orders(Request $request)
    {
        // Check for comma-separated IDs
        $ids = $request->input('id') ? explode(',', $request->input('id')) : null;

        // Get filter inputs
        $clientId = $request->input('client_id');
        $clientContactId = $request->input('client_contact_id');
        $name = $request->input('name');
        $city = $request->input('city');
        $pincode = $request->input('pincode');
        $state = $request->input('state');
        $country = $request->input('country');
        $salesOrderNo = $request->input('sales_order_no');
        $salesOrderDate = $request->input('sales_order_date');

        // Build the query
        $query = SalesOrderModel::query()
            ->select(
                'id', 
                'client_id', 
                'client_contact_id', 
                'name', 
                'address_line_1', 
                'address_line_2', 
                'city', 
                'pincode', 
                'state', 
                'country', 
                'sales_order_no', 
                'sales_order_date', 
                'quotation_no', 
                'cgst', 
                'sgst', 
                'igst', 
                'total', 
                'currency', 
                'template', 
                'status'
            )
            ->where('company_id', Auth::user()->company_id);

        // If IDs are provided, prioritize them
        if ($ids) {
            $query->whereIn('id', $ids);
        } else {
            // Apply filters only if IDs are not provided
            if ($clientId) {
                $query->where('client_id', $clientId);
            }
            if ($clientContactId) {
                $query->where('client_contact_id', $clientContactId);
            }
            if ($name) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            }
            if ($city) {
                $query->where('city', 'LIKE', '%' . $city . '%');
            }
            if ($pincode) {
                $query->where('pincode', $pincode);
            }
            if ($state) {
                $query->where('state', 'LIKE', '%' . $state . '%');
            }
            if ($country) {
                $query->where('country', 'LIKE', '%' . $country . '%');
            }
            if ($salesOrderNo) {
                $query->where('sales_order_no', 'LIKE', '%' . $salesOrderNo . '%');
            }
            if ($salesOrderDate) {
                $query->whereDate('sales_order_date', $salesOrderDate);
            }
        }

        $salesOrders = $query->get();

        if ($salesOrders->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Orders found to export!',
            ], 404);
        }

        // Format data for Excel
        $exportData = $salesOrders->map(function ($order) {
            return [
                'Sales Order ID' => $order->id,
                'Client ID' => $order->client_id,
                'Client Contact ID' => $order->client_contact_id,
                'Name' => $order->name,
                'Address Line 1' => $order->address_line_1,
                'Address Line 2' => $order->address_line_2,
                'City' => $order->city,
                'Pincode' => $order->pincode,
                'State' => $order->state,
                'Country' => $order->country,
                'Sales Order No' => $order->sales_order_no,
                'Sales Order Date' => $order->sales_order_date,
                'Quotation No' => $order->quotation_no,
                'CGST' => $order->cgst,
                'SGST' => $order->sgst,
                'IGST' => $order->igst,
                'Total' => $order->total,
                'Currency' => $order->currency,
                'Template' => $order->template,
                'Status' => $order->status,
            ];
        })->toArray();

        // Generate the file path
        $fileName = 'sales_orders_export_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'uploads/sales_orders_excel/' . $fileName;

        // Save Excel to storage
        Excel::store(new class($exportData) implements FromCollection, WithHeadings {
            private $data;

            public function __construct(array $data)
            {
                $this->data = collect($data);
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Sales Order ID',
                    'Client ID',
                    'Client Contact ID',
                    'Name',
                    'Address Line 1',
                    'Address Line 2',
                    'City',
                    'Pincode',
                    'State',
                    'Country',
                    'Sales Order No',
                    'Sales Order Date',
                    'Quotation No',
                    'CGST',
                    'SGST',
                    'IGST',
                    'Total',
                    'Currency',
                    'Template',
                    'Status',
                ];
            }
        }, $filePath, 'public');

        // Get file details
        $fileUrl = asset('storage/' . $filePath);
        $fileSize = Storage::disk('public')->size($filePath);

        // Return response with file details
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'File available for download',
            'data' => [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'content_type' => 'Excel',
            ],
        ], 200);
    }

}
