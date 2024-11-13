<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderProductsModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Auth;

class PurchaseOrderController extends Controller
{
    //
    // create
    // public function add_purchase_order(Request $request)
    // {
    //     $request->validate([
    //         'supplier_id' => 'required|integer',
    //         'name' => 'required|string',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'nullable|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required|string',
    //         'state' => 'required|string',
    //         'country' => 'required|string',
    //         'purchase_order_no' => 'required|string',
    //         'purchase_order_date' => 'required|date',
    //         'cgst' => 'required|numeric',
    //         'sgst' => 'required|numeric',
    //         'igst' => 'required|numeric',
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
    //         'products.*.godown' => 'required|integer',
    //     ]);
    
    
    //     $register_purchase_order = PurchaseOrderModel::create([
    //         'supplier_id' => $request->input('supplier_id'),
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $request->input('name'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'purchase_order_no' => $request->input('purchase_order_no'),
    //         'purchase_order_date' => $request->input('purchase_order_date'),
    //         'cgst' => $request->input('cgst'),
    //         'sgst' => $request->input('sgst'),
    //         'igst' => $request->input('igst'),
    //         'currency' => $request->input('currency'),
    //         'template' => $request->input('template'),
    //         'status' => $request->input('status'),
        
    //     ]);
        
    //     $products = $request->input('products');

    //     // Iterate over the products array and insert each contact
    //     foreach ($products as $product) 
    //     {
    //         PurchaseOrderProductsModel::create([
    //             'purchase_order_number' => $register_purchase_order['id'],
    //             'product_id' => $product['product_id'],
    //             'company_id' => Auth::user()->company_id,
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

    //     unset($register_purchase_order['id'], $register_purchase_order['created_at'], $register_purchase_order['updated_at']);
    
    //     return isset($register_purchase_order) && $register_purchase_order !== null
    //     ? response()->json(['Purchase Order registered successfully!', 'data' => $register_purchase_order], 201)
    //     : response()->json(['Failed to register Purchase Order record'], 400);
    // }

    public function add_purchase_order(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'purchase_order_no' => 'required|string',
            'purchase_order_date' => 'required|date',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|integer',
            'products.*.godown' => 'required|integer',
        ]);

        // Fetch supplier details using supplier_id
        $supplier = SuppliersModel::find($request->input('supplier_id'));
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
    
        $currentDate = Carbon::now()->toDateString();
    
        $register_purchase_order = PurchaseOrderModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $supplier->name,
            'address_line_1' => $supplier->address_line_1,
            'address_line_2' => $supplier->address_line_2,
            'city' => $supplier->city,
            'pincode' => $supplier->pincode,
            'state' => $supplier->state,
            'country' => $supplier->country,
            'purchase_order_no' => $request->input('purchase_order_no'),
            'purchase_order_date' => $currentDate,
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
            $product_details = ProductsModel::find($product['product_id']);
            
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
                if (strtolower($supplier->state) === 'west bengal') {
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

                PurchaseOrderProductsModel::create([
                    'purchase_order_number' => $register_purchase_order['id'],
                    'product_id' => $product['product_id'],
                    'company_id' => Auth::user()->company_id,
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
        }

        // Update the total amount and tax values in the sales invoice record
        $register_purchase_order->update([
            'total' => $total_amount,
            'cgst' => $total_cgst,
            'sgst' => $total_sgst,
            'igst' => $total_igst,
        ]);

        unset($register_purchase_order['id'], $register_purchase_order['created_at'], $register_purchase_order['updated_at']);
    
        return isset($register_purchase_order) && $register_purchase_order !== null
        ? response()->json(['Purchase Order registered successfully!', 'data' => $register_purchase_order, 'total_cgst' => $total_cgst, 'total_sgst' => $total_sgst, 'total_igst' => $total_igst, 'total_discount' => $total_discount, 'total_amount' => $total_amount], 201)
        : response()->json(['Failed to register Purchase Order record'], 400);
    }

    // view
    public function view_purchase_order()
    {
        $get_purchase_orders = PurchaseOrderModel::with(['products' => function ($query) {
            $query->select('purchase_order_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'supplier_id', 'name', 'purchase_order_no', 'purchase_order_date', 'cgst', 'sgst', 'igst', 'currency', 'template', 'status')
        ->where('company_id',Auth::user()->company_id)
        ->get();

        return isset($get_purchase_orders) && $get_purchase_orders->isNotEmpty()
            ? response()->json(['Purchase Orders fetched successfully!', 'data' => $get_purchase_orders], 200)
            : response()->json(['Failed to fetch Purchase Order data'], 404);
    }

    // update
    public function edit_purchase_order(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'city' => 'required|string',
            'pincode' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'purchase_order_no' => 'required|string',
            'purchase_order_date' => 'required|date',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.purchase_order_number' => 'required|integer',
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

        $purchaseOrder = PurchaseOrderModel::where('id', $id)->first();

        $purchaseOrderUpdated = $purchaseOrder->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'purchase_order_no' => $request->input('purchase_order_no'),
            'purchase_order_date' => $request->input('purchase_order_date'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = PurchaseOrderProductsModel::where('purchase_order_number', $productData['purchase_order_number'])
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
                PurchaseOrderProductsModel::create([
                    'purchase_order_number' => $productData['purchase_order_number'],
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
                    'godown' => $productData['godown'],
                ]);
            }
        }

        $productsDeleted = PurchaseOrderProductsModel::where('purchase_order_number', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        unset($purchaseOrder['created_at'], $purchaseOrder['updated_at']);

        return ($purchaseOrderUpdated || $productsDeleted)
            ? response()->json(['message' => 'Purchase Order and products updated successfully!', 'data' => $purchaseOrder], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_purchase_order($id)
    {
        $get_purchase_order_id = PurchaseOrderModel::select('id', 'company_id')->where('id', $id)->first();

        if ($get_purchase_order_id && $get_purchase_order_id->company_id === Auth::user()->company_id) {
            $delete_purchase_order = PurchaseOrderModel::where('id', $id)->delete();

            $delete_purchase_order_products = PurchaseOrderProductsModel::where('purchase_order_number', $get_purchase_order_id->id)->delete();

            return $delete_purchase_order && $delete_purchase_order_products
                ? response()->json(['message' => 'Purchase Order and associated products deleted successfully!'], 200)
                : response()->json(['message' => 'Failed to delete Purchase Order or products.'], 400);
        } else {
            return response()->json(['message' => 'Purchase Order not found.'], 404);
        }
    }

    public function importPurchaseOrders()
    {
        PurchaseOrderModel::truncate();  
        
        PurchaseOrderProductsModel::truncate();  

        $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_order.php'; // Replace with the actual URL

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
        
            // Parse JSON data for items and tax
            $itemsData = json_decode($record['items'], true);
            $taxData = json_decode($record['tax'], true);
            $addonsData = json_decode($record['addons'], true);
            $topData = json_decode($record['top'], true);

            if (!is_array($itemsData) || !is_array($taxData) || !is_array($addonsData) || !is_array($topData)) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in one of the fields.'];
                continue;
            }

            // Generate dummy sales data and fallback for missing fields
            $supplier = SuppliersModel::where('name', $record['supplier'])->first();

            $defaultSupplierId = 0;

            if (!empty($record['po_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $record['po_date']) && $record['po_date'] !== '0000-00-00') {
                $purchaseOrderDate = \DateTime::createFromFormat('Y-m-d', $record['po_date']);
            }

            $formattedDate = $purchaseOrderDate ? $purchaseOrderDate->format('Y-m-d') : '1970-01-01';

            // Prepare purchase order data
            $purchaseOrderData = [
                'supplier_id' => $supplier->id ?? $defaultSupplierId,
                'name' =>$supplier->name ?? "Random Supplier",
                'address_line_1' => $supplier->address_line_1 ?? 'N/A', // Default, since no specific address is provided in data
                'address_line_2' => $supplier->address_line_2 ?? 'N/A',
                'city' => $supplier->city ?? 'N/A', // Default values
                'pincode' => $supplier->pincode ??'000000',
                'state' => !empty($record['state']) ? $record['state'] : 'Unknown State',
                'country' => $supplier->country ?? 'INDIA',
                'purchase_order_no' => $record['po_no'] ?? '0000',
                'purchase_order_date' => $formattedDate,
                'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? $taxData['igst'] : 0,
                'currency' => !empty($record['currency']) ? $record['currency'] : 'INR',
                'template' => json_decode($record['pdf_template'], true)['id'] ?? 1,
                'status' => $record['status'] ?? 1,
            ];

            // print_r($purchaseOrderData);

            // Validate purchase order data
            $validator = Validator::make($purchaseOrderData, [
                'supplier_id' => 'required|integer',
                'name' => 'required|string',
                'address_line_1' => 'required|string',
                'address_line_2' => 'nullable|string',
                'city' => 'required|string',
                'pincode' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'purchase_order_no' => 'required|string',
                'purchase_order_date' => 'required|date_format:Y-m-d',
                'cgst' => 'required|numeric',
                'sgst' => 'required|numeric',
                'igst' => 'required|numeric',
                'currency' => 'required|string',
                'template' => 'required|integer',
                'status' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            // Insert purchase order data
            try {
                $purchaseOrder = PurchaseOrderModel::create($purchaseOrderData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert purchase order: ' . $e->getMessage()];
                continue;
            }

            // Insert products
            if ($itemsData && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $productName) {
                    try {
                        PurchaseOrderProductsModel::create([
                            'purchase_order_number' => $purchaseOrder->id,
                            'product_id' => $index + 1,
                            'product_name' => $productName,
                            'description' => $itemsData['desc'][$index] ?? 'No Description',
                            'brand' => 'Unknown', // Default as brand data is missing in the sample
                            'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
                            'unit' => $itemsData['unit'][$index] ?? '',
                            'price' => (float) $itemsData['price'][$index] ?? 0.0,
                            'discount' => isset($itemsData['discount'][$index]) && $itemsData['discount'][$index] !== '' ? (float) $itemsData['discount'][$index] : 0.0,
                            'hsn' => $itemsData['hsn'][$index] ?? '',
                            'tax' => (float) $itemsData['tax'][$index] ?? 0,
                            'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
                            'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
                            'igst' => isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0,
                        ]);
                    } catch (\Exception $e) {
                        $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
                    }
                }
            }
        }

        return response()->json([
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
