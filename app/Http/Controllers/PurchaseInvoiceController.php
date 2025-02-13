<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\PurchaseInvoiceModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\PurchaseInvoiceAddonsModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use Carbon\Carbon;
use Auth;
use DB;
use NumberFormatter;

class PurchaseInvoiceController extends Controller
{
    //
    // create
    public function add_purchase_invoice(Request $request)
    {
        $request->validate([
            // Purchase Invoice Fields
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
            'purchase_invoice_no' => 'required|string|max:255',
            'purchase_invoice_date' => 'required|date_format:Y-m-d',
            'oa_no' => 'required|string|max:50',
            'ref_no' => 'required|string|max:50',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
        
            // Product Details (Array Validation)
            'products' => 'required|array',
            'products.*.purchase_invoice_number' => 'required|string|max:50|exists:t_purchase_invoices,purchase_invoice_no',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit' => 'required|string|max:20',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'required|string|max:20',
            'products.*.tax' => 'required|numeric|min:0',
            'products.*.cgst' => 'nullable|numeric|min:0',
            'products.*.sgst' => 'nullable|numeric|min:0',
            'products.*.igst' => 'nullable|numeric|min:0',
            'products.*.amount' => 'nullable|numeric|min:0',

            // for add-ons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',
        ]);     
        
        $exists = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
            ->where('purchase_invoice_number', $request->input('purchase_invoice_no'))
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and purchase_invoice_number must be unique.',
            ], 422);
        }

        // Fetch supplier details using supplier_id
        $supplier = SuppliersModel::find($request->input('supplier_id'));
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
    
        $currentDate = Carbon::now()->toDateString();
    
        $register_purchase_invoice = PurchaseInvoiceModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $supplier->name,
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'purchase_invoice_date' => $currentDate,
            'oa_no' => $request->input('oa_no'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            PurchaseInvoiceProductsModel::create([
                'purchase_invoice_id' => $register_purchase_invoice['id'],
                'product_id' => $product['product_id'],
                'company_id' => Auth::user()->company_id,
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
            ]);
        }

        // Iterate over the addons array and insert each contact
        foreach ($request->input('addons', []) as $addon) {
            PurchaseInvoiceAddonsModel::create([
                'purchase_invoice_id' => $register_purchase_invoice['id'],
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

        unset($register_purchase_invoice['id'], $register_purchase_invoice['created_at'], $register_purchase_invoice['updated_at']);
    
        return isset($register_purchase_invoice) && $register_purchase_invoice !== null
        ? response()->json(['code' => 201,'success' => true, 'Purchase Invoice registered successfully!', 'data' => $register_purchase_invoice], 201)
        : response()->json(['code' => 400,'success' => false, 'Failed to register Purchase Invoice record'], 400);
    }

    // view
    // helper function
     private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }
    public function view_purchase_invoice(Request $request)
    {
        // Get filter inputs
        $supplierId = $request->input('supplier_id');
        $name = $request->input('name');
        $purchaseInvoiceNo = $request->input('purchase_invoice_no');
        $purchaseInvoiceDate = $request->input('purchase_invoice_date');
        $purchaseOrderNo = $request->input('purchase_order_no');
        $productIds = $request->input('product_ids'); 
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Get total count of records in `t_purchase_order`
        $get_purchase_invoice = PurchaseInvoiceModel::count(); 

        // Build the query
        $query = PurchaseInvoiceModel::with(['products' => function ($query) {
            $query->select('purchase_invoice_number', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst',DB::raw('(tax / 2) as cgst_rate'), DB::raw('(tax / 2) as sgst_rate'), DB::raw('(tax) as igst_rate'), 'amount', 'channel', 'stock');
        }, 'addons' => function ($query) {
            $query->select('quotation_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
        }])
        ->select('id', 'supplier_id', 'name', 'purchase_invoice_no', 'purchase_invoice_date', 'oa_no', 'ref_no', 'template', 'user', 'cgst', 'sgst', 'igst', 'total')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($purchaseInvoiceNo) {
            $query->where('purchase_invoice_no', 'LIKE', '%' . $purchaseInvoiceNo . '%');
        }
        if ($purchaseInvoiceDate) {
            $query->whereDate('purchase_invoice_date', $purchaseInvoiceDate);
        }
        if ($purchaseOrderNo) {
            $query->where('purchase_order_no', 'LIKE', '%' . $purchaseOrderNo . '%');
        }
            // ✅ **Filter by comma-separated product IDs**
            if (!empty($productIds)) {
            $productIdArray = explode(',', $productIds); // Convert CSV to array
            $query->whereHas('products', function ($query) use ($productIdArray) {
                $query->whereIn('product_id', $productIdArray);
            });
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_purchase_invoices = $query->get();

        // Transform Data
        $get_purchase_invoices->transform(function ($invoice) {

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
        return $get_purchase_invoices->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Purchase Invoices fetched successfully!',
                'data' => $get_purchase_invoices,
                'fetched_records' => $get_purchase_invoice->count(),
                'count' => $total_purchase_invoices,
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Purchase Invoices found!',
            ], 404);
    }

    // update
    public function edit_purchase_invoice(Request $request, $id)
    {
        $request->validate([
            // Purchase Invoice Fields
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
            'purchase_invoice_no' => 'required|string|max:255',
            'purchase_invoice_date' => 'required|date_format:Y-m-d',
            'oa_no' => 'required|string|max:50',
            'ref_no' => 'required|string|max:50',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
        
            // Product Details (Array Validation)
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit' => 'required|string|max:20',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.discount_type' => 'required|in:percentage,value',
            'products.*.hsn' => 'required|string|max:20',
            'products.*.tax' => 'required|numeric|min:0',
            'products.*.cgst' => 'nullable|numeric|min:0',
            'products.*.sgst' => 'nullable|numeric|min:0',
            'products.*.igst' => 'nullable|numeric|min:0',
            'products.*.amount' => 'nullable|numeric|min:0',

            // for add-ons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',
        ]);

        $purchaseInvoice = PurchaseInvoiceModel::where('id', $id)->first();

        $exists = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
            ->where('purchase_invoice_number', $request->input('purchase_invoice_no'))
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and purchase_invoice_number must be unique.',
            ], 422);
        }
        
        $purchaseInvoiceUpdated = $purchaseInvoice->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'purchase_invoice_date' => $request->input('purchase_invoice_date'),
            'oa_no' => $request->input('oa_no'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
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
                    'amount' => $productData['amount'],
                ]);
            } else {
                // Add new product
                PurchaseInvoiceProductsModel::create([
                    'purchase_invoice_id' => $id,
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
                ]);
            }
        }

        $productsDeleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
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
                PurchaseInvoiceAddonsModel::create([
                    'purchase_invoice_id' => $id,
                    'company_id' => Auth::user()->company_id,
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

        PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
                                    ->where('product_id', $requestAddonIDs)
                                    ->delete();

        unset($purchaseInvoice['created_at'], $purchaseInvoice['updated_at']);

        return ($purchaseInvoiceUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Invoice and products updated successfully!', 'data' => $purchaseInvoice], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
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
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Invoice and related products deleted successfully!'], 200)
            : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Purchase Invoice.'], 400);
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
            'code' => 200,
            'success' => true,
            'message' => "Purchase invoices import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
