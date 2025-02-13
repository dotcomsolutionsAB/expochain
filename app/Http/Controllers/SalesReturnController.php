<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\SalesReturnModel;
use App\Models\SalesReturnProductsModel;
use App\Models\ClientsModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Carbon\Carbon;
use Auth;

class SalesReturnController extends Controller
{
    //
    // create
    // public function add_sales_return(Request $request)
    // {
    //     $request->validate([
    //         'client_id' => 'required|integer',
    //         'name' => 'required|string',
    //         'sales_return_no' => 'required|string',
    //         'sales_return_date' => 'required|date',
    //         'sales_invoice_no' => 'required|string',
    //         'cgst' => 'required|numeric',
    //         'sgst' => 'required|numeric',
    //         'igst' => 'required|numeric',
    //         'total' => 'required|numeric',
    //         'currency' => 'required|string',
    //         'template' => 'required|integer',
    //         'status' => 'required|integer',
    //         'products' => 'required|array', // For validating array of products
    //         'products.*.sales_return_id' => 'required|integer',
    //         'products.*.product_id' => 'required|integer',
    //         'products.*.product_name' => 'required|string',
    //         'products.*.description' => 'nullable|string',
    //         'products.*.brand' => 'required|string',
    //         'products.*.quantity' => 'required|integer',
    //         'products.*.unit' => 'required|integer',
    //         'products.*.price' => 'required|numeric',
    //         'products.*.discount' => 'nullable|numeric',
    //         'products.*.hsn' => 'required|string',
    //         'products.*.tax' => 'required|numeric',
    //         'products.*.cgst' => 'required|numeric',
    //         'products.*.sgst' => 'required|numeric',
    //         'products.*.igst' => 'required|numeric',
    //         'products.*.godown' => 'required|integer'
    //     ]);
    
    
    //     $register_sales_return = SalesReturnModel::create([
    //         'client_id' => $request->input('client_id'),
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $request->input('name'),
    //         'sales_return_no' => $request->input('sales_return_no'),
    //         'sales_return_date' => $request->input('sales_return_date'),
    //         'sales_invoice_no' => $request->input('sales_invoice_no'),
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
    //         SalesReturnProductsModel::create([
    //             'sales_return_id' => $register_sales_return['id'],
    //             'company_id' => Auth::user()->company_id,
    //             'product_id' => $product['product_id'],
    //             'product_name' => $product['product_name'],
    //             'description' => $product['description'],
    //             'brand' => $product['brand'],
    //             'quantity' => $product['quantity'],
    //             'unit' => $product['unit'],
    //             'price' => $product['price'],
    //             'discount' => $product['discount'],
    //             'hsn' => $product['hsn'],
    //             'tax' => $product['tax'],
    //             'cgst' => $product['cgst'],
    //             'sgst' => $product['sgst'],
    //             'igst' => $product['igst'],
    //             'godown' => $product['godown'],
    //         ]);
    //     }

    //     unset($register_sales_return['id'], $register_sales_return['created_at'], $register_sales_return['updated_at']);
    
    //     return isset($register_sales_return) && $register_sales_return !== null
    //     ? response()->json(['Sales Retrun registered successfully!', 'data' => $register_sales_return], 201)
    //     : response()->json(['Failed to register Sales Return record'], 400);
    // }

    public function add_sales_return(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'sales_return_no' => 'required|string',
            'sales_invoice_no' => 'required|string',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // For validating array of products
            'products.*.sales_return_id' => 'required|integer',
            'products.*.quantity' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.godown' => 'required|integer'
        ]);

        // Fetch supplier details using supplier_id
        $client = ClientsModel::find($request->input('client_id'));
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
    
        $currentDate = Carbon::now()->toDateString();
    
        $register_sales_return = SalesReturnModel::create([
            'client_id' => $request->input('client_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $client->name,
            'sales_return_no' => $request->input('sales_return_no'),
            'sales_return_date' => $currentDate,
            'sales_invoice_no' => $request->input('sales_invoice_no'),
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

            $product_details = ProductsModel::where('id', $product['product_id'])
                                            ->where('company_id', Auth::user()->company_id)
                                            ->first();
            
            if ($product_details) 
            {
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

                // Determine the tax distribution based on the client's state
                if (strtolower($client->state) === 'west bengal') {
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

                SalesReturnProductsModel::create([
                    'sales_return_id' => $register_sales_return['id'],
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $product['product_id'],
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
                return response()->json(['code' => 404,'success' => false, 'message' => 'Sorry, Products not found'], 404);
            }

            // Update the total amount and tax values in the sales invoice record
            $register_sales_return->update([
                'total' => $total_amount,
                'cgst' => $total_cgst,
                'sgst' => $total_sgst,
                'igst' => $total_igst,
            ]);
        }

        unset($register_sales_return['id'], $register_sales_return['created_at'], $register_sales_return['updated_at']);
    
        return isset($register_sales_return) && $register_sales_return !== null
        ? response()->json(['code' => 201,'success' => true, 'Sales Retrun registered successfully!', 'data' => $register_sales_return, 'total_cgst' => $total_cgst, 'total_sgst' => $total_sgst, 'total_igst' => $total_igst, 'total_discount' => $total_discount, 'total_amount' => $total_amount], 201)
        : response()->json(['code' => 400,'success' => false, 'Failed to register Sales Return record'], 400);
    }

    // view
    // public function view_sales_return()
    // {
    //     $get_sales_returns = SalesReturnModel::with(['products' => function ($query) {
    //         $query->select('sales_return_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
    //     }])
    //     ->select('id', 'client_id', 'name', 'sales_return_no', 'sales_return_date', 'sales_invoice_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
    //     ->where('company_id',Auth::user()->company_id)
    //     ->get();

    //     return isset($get_sales_returns) && $get_sales_returns !== null
    //         ? response()->json(['Sales Returns fetched successfully!', 'data' => $get_sales_returns], 200)
    //         : response()->json(['Failed to fetch Sales Return data'], 404);
    // }

    public function view_sales_return(Request $request)
    {
        // Get filter inputs
        $salesReturnId = $request->input('sales_return_id'); // Filter by sales return ID
        $productId = $request->input('product_id'); // Filter by product ID
        $productName = $request->input('product_name'); // Filter by product name
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = SalesReturnModel::with(['products' => function ($query) use ($productId, $productName) {
            $query->select('sales_return_id', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
            
            // Apply product-related filters
            if ($productId) {
                $query->where('product_id', $productId);
            }
            if ($productName) {
                $query->where('product_name', 'LIKE', '%' . $productName . '%');
            }
        }])
        ->select('id', 'client_id', 'name', 'sales_return_no', 'sales_return_date', 'sales_invoice_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->where('company_id', Auth::user()->company_id);

        // Apply sales_return_id filter
        if ($salesReturnId) {
            $query->where('id', $salesReturnId);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch the data
        $get_sales_returns = $query->get();

        // Return the response
        return $get_sales_returns->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Sales Returns fetched successfully!',
                'data' => $get_sales_returns,
                'count' => $get_sales_returns->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Sales Returns found!',
            ], 404);
    }


    // update
    public function edit_sales_return(Request $request, $id)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'sales_return_no' => 'required|string',
            'sales_return_date' => 'required|date',
            'sales_invoice_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.sales_return_id' => 'required|integer',
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
            'products.*.godown' => 'required|integer',
        ]);

        $salesReturn = SalesReturnModel::where('id', $id)->first();

        $salesReturnUpdated = $salesReturn->update([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'sales_return_no' => $request->input('sales_return_no'),
            'sales_return_date' => $request->input('sales_return_date'),
            'sales_invoice_no' => $request->input('sales_invoice_no'),
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

            $existingProduct = SalesReturnProductsModel::where('sales_return_id', $productData['sales_return_id'])
                                                    ->where('product_id', $productData['product_id'])
                                                    ->first();

            if ($existingProduct) {
                // Update existing product
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
                // Create new product
                SalesReturnProductsModel::create([
                    'sales_return_id' => $productData['sales_return_id'],
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

        // Delete products not included in the request
        $productsDeleted = SalesReturnProductsModel::where('sales_return_id', $id)
                                                ->whereNotIn('product_id', $requestProductIDs)
                                                ->delete();

        unset($salesReturn['created_at'], $salesReturn['updated_at']);
        
        return ($salesReturnUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Return and products updated successfully!', 'data' => $salesReturn], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_sales_return($id)
    {
        $delete_sales_return = SalesReturnModel::where('id', $id)
                                                ->where('company_id', $company_id)
                                                ->delete();

        $delete_sales_return_products = SalesReturnProductsModel::where('sales_return_id', $id)
                                                                ->where('company_id', $company_id)
                                                                ->delete();

        return $delete_sales_return && $delete_sales_return_products
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Sales Return and associated products deleted successfully!'], 200)
            : response()->json(['code' => 404,'success' => false, 'message' => 'Sales Return not found.'], 404);
    }

    // migration
    public function importSalesReturns()
    {
        // Clear the SalesReturn and related tables
        SalesReturnModel::truncate();
        SalesReturnProductsModel::truncate();

        // Example URL to fetch the data from
        $url = 'https://expo.egsm.in/assets/custom/migrate/sales_return.php'; // Replace with the actual URL

        // Fetch data from the external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source: ' . $e->getMessage()], 500);
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
            // Decode JSON fields for items
            $itemsData = json_decode($record['items'], true);
            $taxData = json_decode($record['tax'], true);
            // $addonsData = json_decode($record['addons'], true);

            if (!is_array($itemsData)) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure for items.'];
                continue;
            }

            // Retrieve supplier information
            $supplier = ClientsModel::where('name', $record['supplier'])->first();
            if (!$supplier) {
                $errors[] = ['record' => $record, 'error' => 'Supplier not found: ' . $record['supplier']];
                continue;
            }

            // Prepare sales return data
            $salesReturnData = [
                'client_id' => $supplier->id,
                'name' => $record['supplier'],
                'sales_return_no' => $record['pi_no'] ?? 'Unknown', // Generate a random sales return number
                'sales_return_date' => $record['pi_date'] ?? now()->format('Y-m-d'),
                'sales_invoice_no' => $record['reference_no'] ?? 'Unknown',
                'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? $taxData['igst'] : 0,
                'total' => $record['total'] ?? 0,
                'currency' => 'INR', // Default currency
                'template' => json_decode($record['pdf_template'], true)['id'] ?? 0, // Default template ID
                'status' => $record['status'] ?? 0,
            ];

            // Validate and insert sales return data
            $validator = Validator::make($salesReturnData, [
                'client_id' => 'required|integer',
                'name' => 'required|string',
                'sales_return_no' => 'required|string',
                'sales_return_date' => 'required|date',
                'sales_invoice_no' => 'required|string',
                'cgst' => 'required|numeric',
                'sgst' => 'required|numeric',
                'igst' => 'required|numeric',
                'total' => 'required|numeric',
                'currency' => 'required|string',
                'template' => 'required|integer',
                'status' => 'required|integer',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                $salesReturn = SalesReturnModel::create($salesReturnData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert sales return: ' . $e->getMessage()];
                continue;
            }

            // Insert products data
            foreach ($itemsData['product'] as $index => $productName) {
                $product = ProductsModel::where('name', $productName)->first();

                if (!$product) {
                    $errors[] = ['record' => $record, 'error' => "Product not found: {$productName}"];
                    continue;
                }

                try {
                    SalesReturnProductsModel::create([
                        'sales_return_id' => $salesReturn->id,
                        'product_id' => $product->id,
                        'product_name' => $productName,
                        'description' => !empty($itemsData['desc'][$index]) ? $itemsData['desc'][$index] : 'No Desc',
                        'brand' => 'DefaultBrand/ No brand available', // Replace with actual brand if available
                        'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => (float) $itemsData['price'][$index] ?? 0,
                        'discount' => (float) $itemsData['discount'][$index] ?? 0,
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => (float) $itemsData['tax'][$index] ?? 0,
                        'cgst' => (float) ($itemsData['cgst'][$index] ?? 0),
                        'sgst' => (float) ($itemsData['sgst'][$index] ?? 0),
                        'igst' => 0,
                        'godown' => 'DefaultGodown', // Replace with actual godown if available
                    ]);
                } catch (\Exception $e) {
                    $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
                }
            }
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
