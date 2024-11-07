<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;

class PurchaseInvoiceController extends Controller
{
    //
    // create
    public function add_purchase_invoice(Request $request)
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
            'purchase_invoice_no' => 'required|string',
            'purchase_invoice_date' => 'required|date',
            'purchase_order_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
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
    
    
        $register_purchase_invoice = PurchaseInvoiceModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'purchase_invoice_date' => $request->input('purchase_invoice_date'),
            'purchase_order_no' => $request->input('purchase_order_no'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            PurchaseInvoiceProductsModel::create([
                'purchase_invoice_number' => $register_purchase_invoice['id'],
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'brand' => $product['brand'],
                'quantity' => $product['quantity'],
                'brand' => $product['brand'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'discount' => $product['discount'],
                'hsn' => $product['hsn'],
                'tax' => $product['tax'],
                'cgst' => $product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
                'godown' => $product['godown'],
            ]);
        }

        unset($register_purchase_invoice['id'], $register_purchase_invoice['created_at'], $register_purchase_invoice['updated_at']);
    
        return isset($register_purchase_invoice) && $register_purchase_invoice !== null
        ? response()->json(['Purchase Invoice registered successfully!', 'data' => $register_purchase_invoice], 201)
        : response()->json(['Failed to register Purchase Invoice record'], 400);
    }

    public function view_purchase_invoice()
    {
        $get_purchase_invoices = PurchaseInvoiceModel::with(['products' => function ($query) {
            $query->select('purchase_invoice_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
        }])
        ->select('id', 'supplier_id', 'name', 'purchase_invoice_no', 'purchase_invoice_date', 'purchase_order_no', 'cgst', 'sgst', 'igst', 'currency', 'template', 'status')
        ->get();

        return isset($get_purchase_invoices) && $get_purchase_invoices->isNotEmpty()
            ? response()->json(['Purchase Invoices fetched successfully!', 'data' => $get_purchase_invoices], 200)
            : response()->json(['Failed to fetch Purchase Invoice data'], 404);
    }

    public function edit_purchase_invoice(Request $request, $id)
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
            'purchase_invoice_no' => 'required|string',
            'purchase_invoice_date' => 'required|date',
            'purchase_order_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.purchase_invoice_number' => 'required|integer',
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

        $purchaseInvoice = PurchaseInvoiceModel::where('id', $id)->first();

        $purchaseInvoiceUpdated = $purchaseInvoice->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'address_line_1' => $request->input('address_line_1'),
            'address_line_2' => $request->input('address_line_2'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'state' => $request->input('state'),
            'country' => $request->input('country'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'purchase_invoice_date' => $request->input('purchase_invoice_date'),
            'purchase_order_no' => $request->input('purchase_order_no'),
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

            $existingProduct = PurchaseInvoiceProductsModel::where('purchase_invoice_number', $productData['purchase_invoice_number'])
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
                // Add new product
                PurchaseInvoiceProductsModel::create([
                    'purchase_invoice_number' => $productData['purchase_invoice_number'],
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

        $productsDeleted = PurchaseInvoiceProductsModel::where('purchase_invoice_number', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        unset($purchaseInvoice['created_at'], $purchaseInvoice['updated_at']);

        return ($purchaseInvoiceUpdated || $productsDeleted)
            ? response()->json(['message' => 'Purchase Invoice and products updated successfully!', 'data' => $purchaseInvoice], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    public function delete_purchase_invoice($id)
    {
        $purchase_invoice = PurchaseInvoiceModel::find($id);

        if (!$purchase_invoice) {
            return response()->json(['message' => 'Purchase Invoice not found.'], 404);
        }

        // Delete related products first
        $products_deleted = PurchaseInvoiceProductsModel::where('purchase_invoice_number', $id)->delete();

        // Delete the purchase invoice
        $purchase_invoice_deleted = $purchase_invoice->delete();

        return ($products_deleted && $purchase_invoice_deleted)
            ? response()->json(['message' => 'Purchase Invoice and related products deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete Purchase Invoice.'], 400);
    }

    public function importPurchaseInvoices()
    {
        set_time_limit(300);

        // Clear the PurchaseInvoice and related tables
        PurchaseInvoiceModel::truncate();
        PurchaseInvoiceProductsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_invoice.php';  

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

        foreach ($data as $record) {
            // Decode JSON fields for items, tax, and addons
            $itemsData = json_decode($record['items'] ?? '{}', true);
            $taxData = json_decode($record['tax'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);

            // Retrieve supplier ID (you might need to adjust this based on your actual supplier retrieval logic)
            $supplier = SuppliersModel::where('name', $record['supplier'])->first();

            if (!$supplier) {
                $errors[] = [
                    'record' => $record,
                    'error' => 'Supplier not found for the provided name: ' . $record['supplier']
                ];
                continue; // Skip to the next record in the loop
            }

            // Set up main purchase invoice data
            $purchaseInvoiceData = [
                'supplier_id' => $supplier->id ?? null,
                'name' => $record['supplier'] ?? 'Unnamed Supplier',
                'address_line_1' => $supplier->address_line_1 ?? 'Address Line 1',
                'address_line_2' => $supplier->address_line_2 ?? null,
                'city' => $supplier->city ?? 'City Name',
                'pincode' => $supplier->pincode ?? '000000',
                'state' => $supplier->state ?? 'State Name',
                'country' => $supplier->country ?? 'India',
                'purchase_invoice_no' => $record['pi_no'] ?? 'Unknown',
                'purchase_invoice_date' => $record['pi_date'] ?? now(),
                'purchase_order_no' => !empty($record['oa_no']) ? $record['oa_no'] : 'Unknown',
                'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
                'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
                'igst' => !empty($taxData['igst']) ? $taxData['igst'] : 0,
                'currency' => 'INR',
                'template' => json_decode($record['pdf_template'], true)['id'] ?? 0,
                'status' => $record['status'] ?? 1,
            ];

            // Validate main purchase invoice data
            $validator = Validator::make($purchaseInvoiceData, [
                'supplier_id' => 'required|integer',
                'name' => 'required|string',
                'address_line_1' => 'required|string',
                'city' => 'required|string',
                'pincode' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'purchase_invoice_no' => 'required|string',
                'purchase_invoice_date' => 'required|date',
                'purchase_order_no' => 'required|string',
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

            try {
                $purchaseInvoice = PurchaseInvoiceModel::create($purchaseInvoiceData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert purchase invoice: ' . $e->getMessage()];
                continue;
            }

            // Process items (products) associated with the purchase invoice
            if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $product) {
                    $productModel = ProductsModel::where('name', $product)->first();

                    // Check if the product exists
                    if (!$productModel) {
                        $errors[] = [
                            'record' => $itemsData,
                            'error' => "Product with name '{$product}' not found."
                        ];
                        continue; // Skip this product if not found
                    }

                    PurchaseInvoiceProductsModel::create([
                        'purchase_invoice_number' => $purchaseInvoice->id,
                        'product_id' => $productModel->id,
                        'product_name' => $product,
                        'description' => $itemsData['desc'][$index] ?? '',
                        'brand' => $itemsData['group'][$index] ?? '',
                        'quantity' => $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => isset($itemsData['price'][$index]) && $itemsData['price'][$index] !== '' ? (float)$itemsData['price'][$index] : 0,
                        'discount' => (float)($itemsData['discount'][$index] ?? 0),
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => (float)($itemsData['tax'][$index] ?? 0),
                        'cgst' => 0,
                        'sgst' => 0,
                        'igst' => (float)($itemsData['igst'][$index] ?? 0),
                        'godown' => isset($itemsData['place'][$index]) ? $itemsData['place'][$index] : '' // You can adjust this as needed
                    ]);
                }
            }
        }

        return response()->json([
            'message' => "Purchase invoices import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
