<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\PurchaseReturnModel;
use App\Models\PurchaseReturnProductsModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Carbon\Carbon;
use Auth;

class PurchaseReturnController extends Controller
{
    //
    // create
    public function add_purchase_return(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'purchase_return_no' => 'required|string',
            'purchase_invoice_no' => 'required|string',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.quantity' => 'required|integer',
            'products.*.godown' => 'required|integer'
        ]);

        // Fetch supplier details using supplier_id
        $supplier = SuppliersModel::find($request->input('supplier_id'));
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
    
        $currentDate = Carbon::now()->toDateString();
    
    
        $register_purchase_return = PurchaseReturnModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' =>  $supplier->name,
            'purchase_return_no' => $request->input('purchase_return_no'),
            'purchase_return_date' => $currentDate,
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
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
            
            if ($product_details) 
            {
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

                PurchaseReturnProductsModel::create([
                    'purchase_return_number' => $register_purchase_return['id'],
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
                    'godown' => $product['godown'],
                ]);
            }

            else{
                return response()->json(['message' => 'Sorry, Products not found'], 404);
            }

            // Update the total amount and tax values in the sales invoice record
            $register_purchase_return->update([
                'total' => $total_amount,
                'cgst' => $total_cgst,
                'sgst' => $total_sgst,
                'igst' => $total_igst,
            ]);
        }

        unset($register_purchase_return['id'], $register_purchase_return['created_at'], $register_purchase_return['updated_at']);
    
        return isset($register_purchase_return) && $register_purchase_return !== null
        ? response()->json(['Purchase Return registered successfully!', 'data' => $register_purchase_return, 'total_cgst' => $total_cgst, 'total_sgst' => $total_sgst, 'total_igst' => $total_igst, 'total_discount' => $total_discount, 'total_amount' => $total_amount], 201)
        : response()->json(['Failed to register Purchase Return record'], 400);
    }

    // view
    public function view_purchase_return()
    {
        $get_purchase_returns = PurchaseReturnModel::with(['products' => function ($query) {
            $query->select('purchase_return_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
        }])
        ->select('id', 'supplier_id', 'name', 'purchase_return_no', 'purchase_return_date', 'purchase_invoice_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->where('company_id',Auth::user()->company_id)
        ->get();

        return isset($get_purchase_returns) && $get_purchase_returns->isNotEmpty()
            ? response()->json(['Purchase Returns fetched successfully!', 'data' => $get_purchase_returns], 200)
            : response()->json(['Failed to fetch Purchase Return data'], 404);
    }

    // update
    public function edit_purchase_return(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'purchase_return_no' => 'required|string',
            'purchase_return_date' => 'required|date',
            'purchase_invoice_no' => 'required|string',
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
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
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
            ? response()->json(['message' => 'Purchase Return and products updated successfully!', 'data' => $purchaseReturn], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_purchase_return($id)
    {
        $get_purchase_return_id = PurchaseReturnModel::select('id')->where('id', $id)->first();

        if ($get_purchase_return_id) {
            $delete_purchase_return = PurchaseReturnModel::where('id', $id)->delete();

            $delete_purchase_return_products = PurchaseReturnProductsModel::where('purchase_return_number', $get_purchase_return_id->id)->delete();

            return $delete_purchase_return && $delete_purchase_return_products
                ? response()->json(['message' => 'Purchase Return and associated products deleted successfully!'], 200)
                : response()->json(['message' => 'Failed to delete Purchase Return or products.'], 400);
        } else {
            return response()->json(['message' => 'Purchase Return not found.'], 404);
        }
    }

    // migration
    public function importPurchaseReturns()
    {
        set_time_limit(300);

        // Clear the PurchaseReturn and related tables
        PurchaseReturnModel::truncate();
        PurchaseReturnProductsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_return.php'; // Replace with your actual URL

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

            // Retrieve client/supplier
            $supplier = SuppliersModel::where('name', $record['client'])->first();

            if (!$supplier) {
                $errors[] = [
                    'record' => $record,
                    'error' => 'Supplier not found for the provided name: ' . $record['client']
                ];
                continue; // Skip to the next record in the loop
            }

            // Set up main purchase return data
            $purchaseReturnData = [
                'supplier_id' => $supplier->id,
                'name' => $record['client'],
                'purchase_return_no' => $record['si_no'] ?? 'Unknown',
                'purchase_return_date' => $record['si_date'] ?? now(),
                'purchase_invoice_no' => $record['so_no'] ?? 'Unknown',
                // 'state' => $record['state'] ?? 'Unknown State',
                'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? $taxData['igst'] : 0,
                'total' => $record['total'] ?? 0,
                'currency' => 'INR',
                'template' => json_decode($record['pdf_template'], true)['id'] ?? 0,
                'status' => $record['status'] ?? 1,
                // 'remarks' => $record['remarks'] ?? '',
                // 'log_user' => $record['log_user'] ?? 'Unknown',
            ];

            // Validate main purchase return data
            $validator = Validator::make($purchaseReturnData, [
                'supplier_id' => 'required|integer',
                'name' => 'required|string',
                'purchase_return_no' => 'required|string',
                'purchase_return_date' => 'required|date',
                'purchase_invoice_no' => 'required|string',
                // 'state' => 'required|string',
                'cgst' => 'required|numeric',
                'sgst' => 'required|numeric',
                'igst' => 'required|numeric',
                'total' => 'required|numeric',
                'currency' => 'required|string',
                'template' => 'required|integer',
                'status' => 'required|integer',
                // 'remarks' => 'nullable|string',
                // 'log_user' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                $purchaseReturn = PurchaseReturnModel::create($purchaseReturnData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert purchase return: ' . $e->getMessage()];
                continue;
            }

            // Process items (products) associated with the purchase return
            if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $productName) {
                    $product = ProductsModel::where('name', $productName)->first();

                    // Check if the product exists
                    if (!$product) {
                        $errors[] = [
                            'record' => $record,
                            'error' => "Product with name '{$productName}' not found."
                        ];
                        continue; // Skip this product if not found
                    }

                    PurchaseReturnProductsModel::create([
                        'purchase_return_number' => $purchaseReturn->id,
                        'product_id' => $product->id,
                        'product_name' => $productName,
                        'description' => !empty($itemsData['desc'][$index]) ? ($itemsData['desc'][$index]) : 'null',
                        'brand' => 'No brand Available',
                        'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => (float) $itemsData['price'][$index] ?? 0,
                        'discount' => (float) $itemsData['discount'][$index] ?? 0,
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => (float) $itemsData['tax'][$index] ?? 0,
                        'cgst' => (float) ($itemsData['cgst'][$index] ?? 0),
                        'sgst' => (float) ($itemsData['sgst'][$index] ?? 0),
                        'igst' => 0,
                        'godown' => $itemsData['place'][$index] ?? 'Default Godown',
                    ]);
                }
            }
        }

        return response()->json([
            'message' => "Purchase returns import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
