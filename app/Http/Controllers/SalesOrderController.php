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
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Storage;

class SalesOrderController extends Controller
{
    //
    // create
    public function add_sales_order(Request $request)
    {
        // Validate the request data
        $request->validate([
            'client_id' => 'required|integer|exists:t_clients,id',
            'client_contact_id' => 'required|integer|exists:t_client_contacts,id',
            'sales_order_no' => 'required|string|unique:t_sales_order,sales_order_no',
            // 'sales_order_date' => 'required|date_format:Y-m-d',
            // 'quotation_no' => 'nullable|integer|exists:t_quotations,id',
            'ref_no' => 'required|string',
            'currency' => 'required|string|max:10',
            'template' => 'required|integer',
            'status' => 'required|integer',
            
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
        
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit' => 'required|string|max:20',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.channel' => 'nullable|integer|exists:t_channels,id',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.hsn' => 'nullable|string',
            'products.*.tax' => 'nullable|numeric|min:0',
            'products.*.cgst' => 'nullable|numeric|min:0',
            'products.*.sgst' => 'nullable|numeric|min:0',
            'products.*.igst' => 'nullable|numeric|min:0',
        
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

        dd($client->address_line_1);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $currentDate = Carbon::now()->toDateString();

        // Register the sales order
        $register_sales_order = SalesOrderModel::create([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'address_line_1' => $client->address_line_1,
            'address_line_2' => $client->address_line_2,
            'city' => $client->city,
            'pincode' => $client->pincode,
            'state' => $client->state,
            'country' => $client->country,
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $currentDate,
            'ref_no' => $request->input('ref_no'),
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

        // Iterate over the products array and calculate totals
        foreach ($products as $product) {
            $product_details = ProductsModel::where('id', $product['product_id'])
                                            ->where('company_id', Auth::user()->company_id)
                                            ->first();

            if ($product_details) {
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

                // Determine the tax distribution
                if (strtolower($client->state) === 'west bengal') {
                    $cgst = $tax_amount / 2;
                    $sgst = $tax_amount / 2;
                    $igst = 0;
                } else {
                    $cgst = 0;
                    $sgst = 0;
                    $igst = $tax_amount;
                }

                // Accumulate the totals
                $total_amount += $product_total;
                $total_cgst += $cgst;
                $total_sgst += $sgst;
                $total_igst += $igst;

                // Create a record for the product
                SalesOrderProductsModel::create([
                    'sales_order_id' => $register_sales_order->id,
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product_details->id,
                    'product_name' => $product_details->name,
                    'description' => $product_details->description,
                    'group' => $product_details->group,
                    'quantity' => $quantity,
                    // 'unit' => $product_details->unit,
                    // 'price' => $rate,
                    // 'channel' => $product_details->channel,
                    // 'discount_type' => $product_details->discount_type,
                    // 'discount' => $discount_amount,
                    'unit' => $product_details->unit,
                    'price' => $product_details->price,
                    'channel' => $product_details->channel,
                    'discount_type' => $product_details->discount_type,
                    'discount' => $product_details->discount,
                    'hsn' => $product_details->hsn,
                    'tax' => $tax_rate,
                    'cgst' => $cgst,
                    'sgst' => $sgst,
                    'igst' => $igst,
                ]);
            }
        }

        // Update the total amount and tax values in the sales order record
        $register_sales_order->update([
            'total' => $total_amount,
            'cgst' => $total_cgst,
            'sgst' => $total_sgst,
            'igst' => $total_igst,
        ]);

        // Process and insert addons
        $addons = $request->input('addons');
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
            'total_cgst' => $total_cgst,
            'total_sgst' => $total_sgst,
            'total_igst' => $total_igst,
            'total_discount' => $total_discount,
            'total_amount' => $total_amount
        ], 201);
    }

    // View Sales Orders
    public function view_sales_order(Request $request)
    {
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
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Get total count of records in `t_sales_order`
        $total_sales_order = SalesOrderModel::count(); 

        // Build the query
        $query = SalesOrderModel::with([
            'products' => function ($query) {
                $query->select(
                    'sales_order_id', 'product_id', 'product_name', 'description', 'group', 
                    'quantity', 'sent', 'short_closed', 'unit', 'price', 'discount_type', 
                    'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'channel'
                )->with(['channel' => function ($channelQuery) {
                    $channelQuery->select('id', 'name'); // Fetch channel name
                }]);
            },
            'addons' => function ($query) {
                $query->select('sales_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            }
        ])
        ->select('id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'sales_order_no', 'sales_order_date', 'ref_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
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

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_sales_orders = $query->get();

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
            'client_id' => 'required|integer',
            'client_contact_id' => 'required|integer',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'required|string',
            'city' => 'required|string',
            'pincode' => 'required',
            'state' => 'required|string',
            'country' => 'required|string',
            'sales_order_no' => 'required|integer',
            'sales_order_date' => 'required|date',
            'quotation_no' => 'required|integer',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.sales_order_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.group' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|integer',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'addons' => 'nullable|array',
            'addons.*.sales_order_id' => 'required|integer',
            'addons.*.name' => 'required|string',
            'addons.*.amount' => 'required|numeric',
            'addons.*.tax' => 'required|numeric',
            'addons.*.hsn' => 'required|string',
            'addons.*.cgst' => 'required|numeric',
            'addons.*.sgst' => 'required|numeric',
            'addons.*.igst' => 'required|numeric',
        ]);

        $salesOrder = SalesOrderModel::where('id', $id)->first();

        $salesOrderUpdated = $salesOrder->update([
            'client_id' => $request->input('client_id'),
            'client_contact_id' => $request->input('client_contact_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'sales_order_no' => $request->input('sales_order_no'),
            'sales_order_date' => $request->input('sales_order_date'),
            'quotation_no' => $request->input('quotation_no'),
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

        // Process products
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = SalesOrderProductsModel::where('sales_order_id', $productData['sales_order_id'])
                                                    ->where('product_id', $productData['product_id'])
                                                    ->toSql();
                                                    // dd($productData['product_id']);

            if ($existingProduct) {
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'group' => $productData['group'],
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
                SalesOrderProductsModel::create([
                    'sales_order_id' => $productData['sales_order_id'],
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'group' => $productData['group'],
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

        // Process addons
        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = SalesOrderAddonsModel::where('sales_order_id', $addonData['sales_order_id'])
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
                    'sales_order_id' => $addonData['sales_order_id'],
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
        $productsDeleted = SalesOrderProductsModel::where('sales_order_id', $id)
                                                ->where('product_id', $requestProductIDs)
                                                ->delete();

        // Delete addons not included in the request
        $addonsDeleted = SalesOrderAddonsModel::where('sales_order_id', $id)
                                            ->where('name', $requestAddonIDs)
                                            ->delete();

        return ($salesOrderUpdated || $productsDeleted || $addonsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Order, products, and addons updated successfully!', 'data' => $salesOrder], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // Delete Sales Order
    public function delete_sales_order($id)
    {
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
    public function importSalesOrders()
    {
        set_time_limit(300); // Prevent timeout for large imports

        // Clear old data before import
        SalesOrderModel::truncate();
        SalesOrderProductsModel::truncate();
        SalesOrderAddonsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/sells_order.php';

        // Fetch data from the external URL
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

        // **Batch size limit**
        $batchSize = 50;

        // Initialize batch arrays
        $salesOrdersBatch = [];
        $productsBatch = [];
        $addonsBatch = [];

        foreach ($data as $record) {
            // Decode JSON fields
            $itemsData = json_decode($record['items'] ?? '{}', true);
            $taxData = json_decode($record['tax'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);

            // Retrieve client details
            $client = ClientsModel::where('name', $record['client'])->first();
            if (!$client) {
                $errors[] = ['record' => $record, 'error' => 'Client not found: ' . $record['client']];
                continue;
            }

            $clientContact = ClientContactsModel::where('customer_id', $client->customer_id)->first();
            if (!$clientContact) {
                $errors[] = ['record' => $record, 'error' => 'Client contact not found for customer ID: ' . $client->customer_id];
                continue;
            }

            // Prepare sales order data
            $salesOrdersBatch[] = [
                'company_id' => Auth::user()->company_id,
                'client_id' => $client->id ?? null,
                'client_contact_id' => $clientContact->id ?? null,
                'name' => $record['client'] ?? 'Unnamed Client',
                'address_line_1' => $client->address_line_1 ?? 'Address Line 1',
                'address_line_2' => $client->address_line_2 ?? 'Address Line 2',
                'city' => $client->city ?? 'City Name',
                'pincode' => $client->pincode ?? '000000',
                'state' => $client->state ?? 'State Name',
                'country' => $client->country ?? 'India',
                'sales_order_no' => $record['so_no'] ?? 'Unknown',
                'sales_order_date' => date('Y-m-d', strtotime($record['so_date'] ?? now())), // Ensure correct format
                'ref_no' => $record['ref_no'] ?? 0,
                'cgst' => (float)($taxData['cgst'] ?? 0),
                'sgst' => (float)($taxData['sgst'] ?? 0),
                'igst' => (float)($taxData['igst'] ?? 0),
                'total' => (float)($record['total'] ?? 0),
                'currency' => 'INR',
                'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                'status' => $record['status'] ?? 1,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Temporary Sales Order ID for mapping products
            $salesOrderId = count($salesOrdersBatch);

            // Process products
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
                        'group' => $itemsData['brand'][$index] ?? '',
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

            // Process addons
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

            // Batch insert when batch size reaches limit
            if (count($salesOrdersBatch) >= $batchSize) {
                SalesOrderModel::insert($salesOrdersBatch);
                $salesOrdersBatch = [];
            }

            if (count($productsBatch) >= $batchSize) {
                SalesOrderProductsModel::insert($productsBatch);
                $productsBatch = [];
            }

            if (count($addonsBatch) >= $batchSize) {
                SalesOrderAddonsModel::insert($addonsBatch);
                $addonsBatch = [];
            }
        }

        // Insert remaining records after loop completes
        if (!empty($salesOrdersBatch)) SalesOrderModel::insert($salesOrdersBatch);
        foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
            SalesOrderProductsModel::insert($chunk);
        }
        foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
            SalesOrderAddonsModel::insert($chunk);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Sales orders import completed with $successfulInserts successful inserts.",
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
