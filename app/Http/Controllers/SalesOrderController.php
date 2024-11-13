<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\SalesOrderAddonsModel;
use App\Models\ClientsModel;
use App\Models\ClientsContactsModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Auth;
use Carbon\Carbon;

class SalesOrderController extends Controller
{
    //
    // create
    // public function add_sales_order(Request $request)
    // {
    //     $request->validate([
    //         'client_id' => 'required',
    //         'client_contact_id' => 'required',
    //         'name' => 'required|string',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required',
    //         'state' => 'required|string',
    //         'country' => 'required|string',
    //         'sales_order_no' => 'required|integer',
    //         'sales_order_date' => 'required|date',
    //         'quotation_no' => 'required|integer',
    //         'cgst' => 'required|numeric',
    //         'sgst' => 'required|numeric',
    //         'igst' => 'required|numeric',
    //         'total' => 'required|numeric',
    //         'currency' => 'required|string',
    //         'template' => 'required|integer',
    //         'status' => 'required|integer',
    //     ]);
    
    
    //     $register_sales_order = SalesOrderModel::create([
    //         'client_id' => $request->input('client_id'),
    //         'client_contact_id' => $request->input('client_contact_id'),
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $request->input('name'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'sales_order_no' => $request->input('sales_order_no'),
    //         'sales_order_date' => $request->input('sales_order_date'),
    //         'quotation_no' => $request->input('quotation_no'),
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
    //         SalesOrderProductsModel::create([
    //         'sales_order_id' => $register_sales_order['id'],
    //         'company_id' => Auth::user()->company_id,
    //         'product_id' => $product['product_id'],
    //         'product_name' => $product['product_name'],
    //         'description' => $product['description'],
    //         'brand' => $product['brand'],
    //         'quantity' => $product['quantity'],
    //         'unit' => $product['unit'],
    //         'price' => $product['price'],
    //         'discount' => $product['discount'],
    //         'hsn' => $product['hsn'],
    //         'tax' => $product['tax'],
    //         'cgst' => $product['cgst'],
    //         'sgst' => $product['sgst'],
    //         'igst' => $product['igst'],
    //         ]);
    //     }

    //     $addons = $request->input('addons');
    
    //     // Iterate over the addons array and insert each contact
    //     foreach ($addons as $addon) 
    //     {
    //         SalesOrderAddonsModel::create([
    //         'sales_order_id' => $register_sales_order['id'],
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $addon['name'],
    //         'amount' => $addon['amount'],
    //         'tax' => $addon['tax'],
    //         'hsn' => $addon['hsn'],
    //         'cgst' => $addon['cgst'],
    //         'sgst' => $addon['sgst'],
    //         'igst' => $addon['igst'],
    //         ]);
    //     }

    //     unset($register_sales_order['id'], $register_sales_order['created_at'], $register_sales_order['updated_at']);
    
    //     return isset($register_sales_order) && $register_sales_order !== null
    //     ? response()->json(['Sales Order registered successfully!', 'data' => $register_sales_order], 201)
    //     : response()->json(['Failed to register Sales Order record'], 400);
    // }

    public function add_sales_order(Request $request)
    {
        // Validate the request data
        $request->validate([
            'client_id' => 'required|integer',
            'client_contact_id' => 'required|integer',
            'sales_order_no' => 'required|integer',
            'quotation_no' => 'required|integer',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
        ]);

        // Fetch the client details using client_id
        $client = ClientsModel::find($request->input('client_id'));

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
            'quotation_no' => $request->input('quotation_no'),
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
            $product_details = ProductsModel::find($product['product_id']);

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
                    'brand' => $product_details->brand,
                    'quantity' => $quantity,
                    'unit' => $product_details->unit,
                    'price' => $rate,
                    'discount' => $discount_amount,
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
    public function view_sales_order()
    {
        $get_sales_orders = SalesOrderModel::with(['products' => function ($query) {
            $query->select('sales_order_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }, 'addons' => function ($query) {
            $query->select('sales_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'client_id', 'client_contact_id', 'name', 'address_line_1', 'address_line_2', 'city', 'pincode', 'state', 'country', 'sales_order_no', 'sales_order_date', 'quotation_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->where('company_id',Auth::user()->company_id)
        ->get();

        return isset($get_sales_orders) && $get_sales_orders !== null
            ? response()->json(['Sales Orders fetched successfully!', 'data' => $get_sales_orders], 200)
            : response()->json(['Failed to fetch Sales Order data'], 404);
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
                SalesOrderProductsModel::create([
                    'sales_order_id' => $productData['sales_order_id'],
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
            ? response()->json(['message' => 'Sales Order, products, and addons updated successfully!', 'data' => $salesOrder], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
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
            ? response()->json(['message' => 'Sales Order and associated products/addons deleted successfully!'], 200)
            : response()->json(['message' => 'Sales Order not found.'], 404);
    }

    // migrate
    public function importSalesOrders()
    {
        set_time_limit(300);

        SalesOrderModel::truncate();
        SalesOrderProductsModel::truncate();
        SalesOrderAddonsModel::truncate();

        // Define the external URL
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

        foreach ($data as $record) {
            // Decode JSON fields for items, tax, and addons
            $itemsData = json_decode($record['items'] ?? '{}', true);
            $taxData = json_decode($record['tax'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);

            // Retrieve client and client contact IDs
            $client = ClientsModel::where('name', $record['client'])->first();

            if (!$client) {
                // If the client is not found, log an error or skip this record
                $errors[] = [
                    'record' => $record,
                    'error' => 'Client not found for the provided name: ' . $record['client']
                ];
                continue; // Skip to the next record in the loop
            }

            $clientContact = ClientsContactsModel::where('customer_id', $client->customer_id)->first();

            if (!$clientContact) {
                // If the client contact is not found, log an error or skip this record
                $errors[] = [
                    'record' => $record,
                    'error' => 'Client contact not found for customer ID: ' . $client->customer_id
                ];
                continue; // Skip to the next record in the loop
            }

            // Set up main sales order data with fallbacks
            $salesOrderData = [
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
                'sales_order_date' => $record['so_date'] ?? now(),
                'quotation_no' => $record['quotation_no'] ?? 0,
                'cgst' => $taxData['cgst'] ?? 0,
                'sgst' => $taxData['sgst'] ?? 0,
                'igst' => $taxData['igst'] ?? 0,
                'total' => $record['total'] ?? 0,
                'currency' => 'INR',
                'template' => json_decode($record['pdf_template'], true)['id'] ?? '0',
                'status' => $record['status'] ?? 1,
            ];

            // Validate main sales order data
            $validator = Validator::make($salesOrderData, [
                'client_id' => 'nullable|integer',
                'client_contact_id' => 'nullable|integer',
                'name' => 'required|string',
                'address_line_1' => 'required|string',
                'city' => 'required|string',
                'pincode' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'sales_order_no' => 'required|string',
                'sales_order_date' => 'required|date',
                'quotation_no' => 'required|integer',
                'cgst' => 'required|numeric',
                'sgst' => 'required|numeric',
                'igst' => 'required|numeric',
                'total' => 'required|numeric',
                'currency' => 'required|string',
                'template' => 'required|string',
                'status' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                $salesOrder = SalesOrderModel::create($salesOrderData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert sales order: ' . $e->getMessage()];
                continue;
            }

            // Process items (products) associated with the sales order
            if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {

                    $get_product = ProductsModel::where('name', $product)->first();
                    // Check if the product exists
                    if (!$get_product) {
                        $errors[] = [
                            'record' => $itemsData,
                            'error' => "Product with name '{$get_product}' not found."
                        ];
                        continue; // Skip this product if not found
                    }

                    SalesOrderProductsModel::create([
                        'sales_order_id' => $salesOrder->id,
                        'product_id' => $get_product->id,
                        'product_name' => $itemsData['product'][$index] ?? 'Unnamed Product',
                        'description' => $itemsData['desc'][$index] ?? '',
                        'brand' => $itemsData['brand'][$index] ?? '',
                        'quantity' => $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => isset($itemsData['price'][$index]) && $itemsData['price'][$index] !== '' ? (float)$itemsData['price'][$index] : 0,
                        'discount' => (float)($itemsData['discount'][$index] ?? 0),
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => isset($itemsData['tax'][$index]) && $itemsData['tax'][$index] !== '' ? (float)$itemsData['tax'][$index] : 0,
                        'cgst' => $itemsData['cgst'][$index] ?? 0,
                        'sgst' => $itemsData['sgst'][$index] ?? 0,
                        'igst' => $itemsData['igst'][$index] ?? 0,
                    ]);
                }
            }

            // Process addons for the sales order
            if ($addonsData) {
                foreach ($addonsData as $name => $values) {
                    $totalAmount = (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0);

                    SalesOrderAddonsModel::create([
                        'sales_order_id' => $salesOrder->id,
                        'name' => $name,
                        'amount' => $totalAmount,
                        'tax' => 18,
                        'hsn' => $values['hsn'] ?? '',
                        'cgst' => (float)($values['cgst'] ?? 0),
                        'sgst' => (float)($values['sgst'] ?? 0),
                        'igst' => (float)($values['igst'] ?? 0),
                    ]);
                }
            }
        }

        return response()->json([
            'message' => "Sales orders import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
