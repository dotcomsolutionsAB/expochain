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
use App\Models\SalesInvoiceProductsModel;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
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
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',
        
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
            'products.*.channel' => 'nullable|exists:t_channels,id',
            'products.*.godown' => 'nullable|exists:t_godown,id',

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
            ->where('purchase_invoice_no', $request->input('purchase_invoice_no'))
            ->exists();

        if ($exists) {
            return response()->json([
                'code' => 422,
                'success' => true,
                'error' => 'The combination of company_id and purchase_invoice_number must be unique.',
            ], 422);
        }

        // Fetch supplier details using supplier_id
        $supplier = SuppliersModel::find($request->input('supplier_id'));
        if (!$supplier) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Supplier not found'], 404);
        }
    
        // $currentDate = Carbon::now()->toDateString();
    
        $register_purchase_invoice = PurchaseInvoiceModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $supplier->name,
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            // 'purchase_invoice_date' => $currentDate,
            'purchase_invoice_date' => $request->input('purchase_invoice_date'),
            'oa_no' => $request->input('oa_no'),
            'ref_no' => $request->input('ref_no'),
            'template' => $request->input('template'),
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                'channel' => $product['channel'],
                'godown' => isset($product['godown']) ? $product['godown'] : null,
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
        ? response()->json(['code' => 201,'success' => true, 'message' => 'Purchase Invoice registered successfully!', 'data' => $register_purchase_invoice], 201)
        : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to register Purchase Invoice record'], 400);
    }

    // view
    // helper function
     private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }
    // public function view_purchase_invoice(Request $request)
    // {
    //     // Get filter inputs
    //     $supplierId = $request->input('supplier_id');
    //     $name = $request->input('name');
    //     $purchaseInvoiceNo = $request->input('purchase_invoice_no');
    //     $purchaseInvoiceDate = $request->input('purchase_invoice_date');
    //     $purchaseOrderNo = $request->input('purchase_order_no');
    //     $productIds = $request->input('product_ids'); 
    //     $limit = $request->input('limit', 10); // Default limit to 10
    //     $offset = $request->input('offset', 0); // Default offset to 0

    //     // Get total count of records in `t_purchase_order`
    //     $get_purchase_invoice = PurchaseInvoiceModel::count(); 

    //     // Build the query
    //     $query = PurchaseInvoiceModel::with(['products' => function ($query) {
    //         $query->select('purchase_invoice_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst',DB::raw('(tax / 2) as cgst_rate'), DB::raw('(tax / 2) as sgst_rate'), DB::raw('(tax) as igst_rate'), 'amount', 'channel', 'godown', 'returned', 'stock');
    //     }, 'addons' => function ($query) {
    //         $query->select('purchase_invoice_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
    //     }])
    //     ->select('id', 'supplier_id', 'name', 'purchase_invoice_no', DB::raw('DATE_FORMAT(purchase_invoice_date, "%d-%m-%Y") as purchase_invoice_date'), 'oa_no', 'ref_no', 'template', 'user', 'cgst', 'sgst', 'igst', 'total', 'gross', 'round_off')
    //     ->where('company_id', Auth::user()->company_id);

    //     // Apply filters
    //     if ($supplierId) {
    //         $query->where('supplier_id', $supplierId);
    //     }
    //     if ($name) {
    //         $query->where('name', 'LIKE', '%' . $name . '%');
    //     }
    //     if ($purchaseInvoiceNo) {
    //         $query->where('purchase_invoice_no', 'LIKE', '%' . $purchaseInvoiceNo . '%');
    //     }
    //     if ($purchaseInvoiceDate) {
    //         $query->whereDate('purchase_invoice_date', $purchaseInvoiceDate);
    //     }
    //     if ($purchaseOrderNo) {
    //         $query->where('purchase_order_no', 'LIKE', '%' . $purchaseOrderNo . '%');
    //     }
    //         // **Filter by comma-separated product IDs**
    //         if (!empty($productIds)) {
    //         $productIdArray = explode(',', $productIds); // Convert CSV to array
    //         $query->whereHas('products', function ($query) use ($productIdArray) {
    //             $query->whereIn('product_id', $productIdArray);
    //         });
    //     }

    //     $purchase_invoice_count = $query->count();
    //     // Apply limit and offset
    //     $query->offset($offset)->limit($limit);

    //     // Fetch data
    //     $get_purchase_invoices = $query->get();

    //     // Transform Data
    //     $get_purchase_invoices->transform(function ($invoice) {

    //         // Convert total to words
    //         $invoice->amount_in_words = $this->convertNumberToWords($invoice->total);

    //         // Format total with comma-separated values
    //         $invoice->total = is_numeric($invoice->total) ? number_format((float) $invoice->total, 2) : $invoice->total;

    //         // Replace user ID with corresponding contact_person object
    //         $invoice->contact_person = isset($invoice->get_user) ? [
    //             'id' => $invoice->get_user->id,
    //             'name' => $invoice->get_user->name
    //         ] : ['id' => null, 'name' => 'Unknown'];

    //         // Convert user ID into an object with `id` and `name`
    //         $invoice->user = isset($invoice->get_user) ? [
    //             'id' => $invoice->get_user->id,
    //             'name' => $invoice->get_user->name
    //         ] : ['id' => null, 'name' => 'Unknown'];

    //         unset($invoice->get_user); // Remove original relationship data

    //         return $invoice;
    //     });

    //     // Return response
    //     return $get_purchase_invoices->isNotEmpty()
    //         ? response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Purchase Invoices fetched successfully!',
    //             'data' => $get_purchase_invoices,
    //             'count' => $get_purchase_invoices->count(),
    //             'total_records' => $purchase_invoice_count,
    //         ], 200)
    //         : response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'No Purchase Invoices found!',
    //         ], 404);
    // }
    public function view_purchase_invoice(Request $request, $id = null)
    {
        // Get filter inputs
        $supplierId = $request->input('supplier_id');
        $name = $request->input('name');
        $purchaseInvoiceNo = $request->input('purchase_invoice_no');
        $purchaseInvoiceDate = $request->input('purchase_invoice_date');
        $purchaseOrderNo = $request->input('purchase_order_no');
        $productIds = $request->input('product_ids');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        // Query Purchase Invoices
        $query = PurchaseInvoiceModel::with([
            'products' => function ($query) {
                $query->select('purchase_invoice_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount', 'discount_type', 'hsn', 'tax', 'cgst', 'sgst', 'igst',
                    DB::raw('(tax / 2) as cgst_rate'),
                    DB::raw('(tax / 2) as sgst_rate'),
                    DB::raw('(tax) as igst_rate'),
                    'amount', 'channel', 'godown', 'returned', 'stock');
            },
            'addons' => function ($query) {
                $query->select('purchase_invoice_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            },
            'get_user:id,name',
            'supplier' => function ($q) {
                // Select key supplier columns and include addresses
                $q->select('id', 'supplier_id')
                  ->with(['addresses' => function ($query) {
                      $query->select('supplier_id', 'state');
                  }]);
            }
        ])
        ->select('id', 'supplier_id', 'name', 'purchase_invoice_no', 
            DB::raw('DATE_FORMAT(purchase_invoice_date, "%d-%m-%Y") as purchase_invoice_date'),
            'oa_no', 'ref_no', 'template', 'user', 'cgst', 'sgst', 'igst', 'total', 'gross', 'round_off'
        )
        ->where('company_id', Auth::user()->company_id);

        // 🔹 **Fetch Single Purchase Invoice by ID**
        if ($id) {
            $purchaseInvoice = $query->where('id', $id)->first();
            if (!$purchaseInvoice) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Purchase Invoice not found!',
                ], 404);
            }

            // Transform Single Purchase Invoice
            $purchaseInvoice->amount_in_words = $this->convertNumberToWords($purchaseInvoice->total);
            $purchaseInvoice->total = is_numeric($purchaseInvoice->total) ? number_format((float) $purchaseInvoice->total, 2) : $purchaseInvoice->total;
            $purchaseInvoice->contact_person = $purchaseInvoice->get_user ? ['id' => $purchaseInvoice->get_user->id, 'name' => $purchaseInvoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            $purchaseInvoice->user = $purchaseInvoice->get_user ? ['id' => $purchaseInvoice->get_user->id, 'name' => $purchaseInvoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
            unset($purchaseInvoice->get_user);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Purchase Invoice fetched successfully!',
                'data' => $purchaseInvoice,
            ], 200);
        }

        // 🔹 **Apply Filters for Listing**
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
        if (!empty($productIds)) {
            $productIdArray = explode(',', $productIds);
            $query->whereHas('products', function ($query) use ($productIdArray) {
                $query->whereIn('product_id', $productIdArray);
            });
        }

        // Get total record count before applying limit
        $totalRecords = $query->count();
        $query->offset($offset)->limit($limit);

        // Fetch paginated results
        $get_purchase_invoices = $query->get();

        if ($get_purchase_invoices->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Purchase Invoices found!',
            ], 404);
        }

        // Transform Data
        $get_purchase_invoices->transform(function ($invoice) {
            $invoice->amount_in_words = $this->convertNumberToWords($invoice->total);
            $invoice->total = is_numeric($invoice->total) ? number_format((float) $invoice->total, 2) : $invoice->total;
            $invoice->contact_person = $invoice->get_user ? ['id' => $invoice->get_user->id, 'name' => $invoice->get_user->name] : ['id' => null, 'name' => 'Unknown'];
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
            'message' => 'Purchase Invoices fetched successfully!',
            'data' => $get_purchase_invoices,
            'count' => $get_purchase_invoices->count(),
            'total_records' => $totalRecords,
        ], 200);
    }

    // update
    public function edit_purchase_invoice(Request $request, $id)
    {
        $request->validate([
            // Purchase Invoice Fields
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
            'name' => 'nullable|string|exists:t_suppliers,name',
            'purchase_invoice_no' => 'required|string|max:255',
            'purchase_invoice_date' => 'required|date',
            'oa_no' => 'required|string|max:50',
            'ref_no' => 'required|string|max:50',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',
        
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
            'products.*.channel' => 'nullable|exists:t_channels,id',
            'products.*.godown' => 'nullable|exists:t_godown,id',

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

        // $exists = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
        //     ->where('purchase_invoice_number', $request->input('purchase_invoice_no'))
        //     ->exists();

        // if ($exists) {
        //     return response()->json([
        //         'code' => 422,
        //         'success' => true,
        //         'error' => 'The combination of company_id and purchase_invoice_number must be unique.',
        //     ], 422);
        // }
        
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
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                    'channel' => $productData['channel'],
                    'godown' => $productData['godown'],
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
                    'channel' => $productData['channel'],
                    'godown' => $productData['godown'],
                ]);
            }
        }

        $productsDeleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
                                                    ->whereNotIn('product_id', $requestProductIDs)
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
                                    ->whereNotIn('name', $requestAddonIDs)
                                    ->delete();

        unset($purchaseInvoice['created_at'], $purchaseInvoice['updated_at']);

        return ($purchaseInvoiceUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Invoice and products updated successfully!', 'data' => $purchaseInvoice], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    public function delete_purchase_invoice($id)
    {
        $purchase_invoice = PurchaseInvoiceModel::find($id);

        $company_id = Auth::user()->company_id;

        if (!$purchase_invoice) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Purchase Invoice not found.'], 404);
        }

        // Delete related products first
        $products_deleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)->delete();

        $delete_products_addons = PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
                                                        ->where('company_id', $company_id)
                                                        ->delete();

        // Delete the purchase invoice
        $purchase_invoice_deleted = $purchase_invoice->delete();

        return ($products_deleted && $purchase_invoice_deleted && $delete_products_addons)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Invoice and related products deleted successfully!'], 200)
            : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Purchase Invoice.'], 400);
    }

    // public function importPurchaseInvoices()
    // {
    //     set_time_limit(300);

    //     // Clear the PurchaseInvoice and related tables
    //     PurchaseInvoiceModel::truncate();
    //     PurchaseInvoiceProductsModel::truncate();

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_invoice.php';  

    //     // Fetch data from the external URL
    //     try {
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
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

    //     foreach ($data as $record) {
    //         // Decode JSON fields for items, tax, and addons
    //         $itemsData = json_decode($record['items'] ?? '{}', true);
    //         $taxData = json_decode($record['tax'] ?? '{}', true);
    //         $addonsData = json_decode($record['addons'] ?? '{}', true);

    //         // Retrieve supplier ID (you might need to adjust this based on your actual supplier retrieval logic)
    //         $supplier = SuppliersModel::where('name', $record['supplier'])->first();

    //         if (!$supplier) {
    //             $errors[] = [
    //                 'record' => $record,
    //                 'error' => 'Supplier not found for the provided name: ' . $record['supplier']
    //             ];
    //             continue; // Skip to the next record in the loop
    //         }

    //         // Set up main purchase invoice data
    //         $purchaseInvoiceData = [
    //             'supplier_id' => $supplier->id ?? null,
    //             'name' => $record['supplier'] ?? 'Unnamed Supplier',
    //             'address_line_1' => $supplier->address_line_1 ?? 'Address Line 1',
    //             'address_line_2' => $supplier->address_line_2 ?? null,
    //             'city' => $supplier->city ?? 'City Name',
    //             'pincode' => $supplier->pincode ?? '000000',
    //             'state' => $supplier->state ?? 'State Name',
    //             'country' => $supplier->country ?? 'India',
    //             'purchase_invoice_no' => $record['pi_no'] ?? 'Unknown',
    //             'purchase_invoice_date' => $record['pi_date'] ?? now(),
    //             'purchase_order_no' => !empty($record['oa_no']) ? $record['oa_no'] : 'Unknown',
    //             'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
    //             'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
    //             'igst' => !empty($taxData['igst']) ? $taxData['igst'] : 0,
    //             'currency' => 'INR',
    //             'template' => json_decode($record['pdf_template'], true)['id'] ?? 0,
    //             'status' => $record['status'] ?? 1,
    //         ];

    //         // Validate main purchase invoice data
    //         $validator = Validator::make($purchaseInvoiceData, [
    //             'supplier_id' => 'required|integer',
    //             'name' => 'required|string',
    //             'address_line_1' => 'required|string',
    //             'city' => 'required|string',
    //             'pincode' => 'required|string',
    //             'state' => 'required|string',
    //             'country' => 'required|string',
    //             'purchase_invoice_no' => 'required|string',
    //             'purchase_invoice_date' => 'required|date',
    //             'purchase_order_no' => 'required|string',
    //             'cgst' => 'required|numeric',
    //             'sgst' => 'required|numeric',
    //             'igst' => 'required|numeric',
    //             'currency' => 'required|string',
    //             'template' => 'required|integer',
    //             'status' => 'required|integer',
    //         ]);

    //         if ($validator->fails()) {
    //             $errors[] = ['record' => $record, 'errors' => $validator->errors()];
    //             continue;
    //         }

    //         try {
    //             $purchaseInvoice = PurchaseInvoiceModel::create($purchaseInvoiceData);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert purchase invoice: ' . $e->getMessage()];
    //             continue;
    //         }

    //         // Process items (products) associated with the purchase invoice
    //         if ($itemsData && isset($itemsData['product']) && is_array($itemsData['product'])) {
    //             foreach ($itemsData['product'] as $index => $product) {
    //                 $productModel = ProductsModel::where('name', $product)->first();

    //                 // Check if the product exists
    //                 if (!$productModel) {
    //                     $errors[] = [
    //                         'record' => $itemsData,
    //                         'error' => "Product with name '{$product}' not found."
    //                     ];
    //                     continue; // Skip this product if not found
    //                 }

    //                 PurchaseInvoiceProductsModel::create([
    //                     'purchase_invoice_id' => $purchaseInvoice->id,
    //                     'product_id' => $productModel->id,
    //                     'product_name' => $product,
    //                     'description' => $itemsData['desc'][$index] ?? '',
    //                     'brand' => $itemsData['group'][$index] ?? '',
    //                     'quantity' => $itemsData['quantity'][$index] ?? 0,
    //                     'unit' => $itemsData['unit'][$index] ?? '',
    //                     'price' => isset($itemsData['price'][$index]) && $itemsData['price'][$index] !== '' ? (float)$itemsData['price'][$index] : 0,
    //                     'discount' => (float)($itemsData['discount'][$index] ?? 0),
    //                     'hsn' => $itemsData['hsn'][$index] ?? '',
    //                     'tax' => (float)($itemsData['tax'][$index] ?? 0),
    //                     'cgst' => 0,
    //                     'sgst' => 0,
    //                     'igst' => (float)($itemsData['igst'][$index] ?? 0),
    //                     'godown' => isset($itemsData['place'][$index]) ? $itemsData['place'][$index] : '' // You can adjust this as needed
    //                 ]);
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Purchase invoices import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    public function importPurchaseInvoices()
    {
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('memory_limit', '1024M');   // Optimize memory usage

        // Truncate Purchase Invoice and related tables before import
        PurchaseInvoiceModel::truncate();
        PurchaseInvoiceProductsModel::truncate();
        PurchaseInvoiceAddonsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_invoice.php';

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

        $batchSize = 500; // Optimal batch size for insert
        $purchaseInvoicesBatch = [];
        $productsBatch = [];
        $addonsBatch = [];
        $successfulInserts = 0;
        $errors = [];

        // **Step 1️⃣: Fetch Existing Data in Memory**
        $existingSuppliers = SuppliersModel::pluck('id', 'name')->toArray();
        $existingProducts = ProductsModel::pluck('id', 'name')->toArray();

        foreach ($data as $record) {
            // Decode JSON fields
            $itemsData = json_decode($record['items'] ?? '{}', true);
            $taxData = json_decode($record['tax'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);

            // Get supplier ID
            $supplierId = $existingSuppliers[$record['supplier']] ?? null;
            if (!$supplierId) {
                $errors[] = [
                    'record' => $record,
                    'error' => 'Supplier not found: ' . $record['supplier']
                ];
                continue;
            }

            // Prepare purchase invoice data
            $purchaseInvoicesBatch[] = [
                'company_id' => Auth::user()->company_id,
                'supplier_id' => $supplierId,
                'name' => $record['supplier'] ?? null,
                'purchase_invoice_no' => $record['pi_no'] ?? null,
                'purchase_invoice_date' => !empty($record['pi_date']) ? date('Y-m-d', strtotime($record['pi_date'])) : null,
                'oa_no' => $record['oa_no'] ?? null,
                'ref_no' => $record['reference_no'] ?? null,
                'template' => json_decode($record['pdf_template'], true)['id'] ?? 0,
                'user' => Auth::user()->id,
                'cgst' => $taxData['cgst'] ?? 0,
                'sgst' => $taxData['sgst'] ?? 0,
                'igst' => $taxData['igst'] ?? 0,
                'total' => $record['total'] ?? null,
                // gross will be updated later after processing items
                'gross' => 0,
                'round_off' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // **2️⃣ Batch Insert Purchase Invoices and Fetch IDs**
        foreach (array_chunk($purchaseInvoicesBatch, $batchSize) as $chunk) {
            PurchaseInvoiceModel::insert($chunk);
        }

        // Fetch newly inserted IDs
        $purchaseInvoiceIds = PurchaseInvoiceModel::whereIn('purchase_invoice_no', array_column($purchaseInvoicesBatch, 'purchase_invoice_no'))
            ->pluck('id', 'purchase_invoice_no')
            ->toArray();

        // **3️⃣ Insert Products and Addons**
        foreach ($data as $record) {
            $purchaseInvoiceId = $purchaseInvoiceIds[$record['pi_no']] ?? null;

            if (!$purchaseInvoiceId) {
                continue;
            }

            $itemsData = json_decode($record['items'] ?? '{}', true);
            $addonsData = json_decode($record['addons'] ?? '{}', true);

            $gross = 0;

            if (!empty($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $productName) {
                    $qty = isset($itemsData['quantity'][$index]) ? (float) $itemsData['quantity'][$index] : 0;
                    $price = isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0;
                    $gross += $qty * $price;
                }
            }

            $roundoff = isset($addonsData['roundoff']) ? (float) $addonsData['roundoff'] : 0;

            // Now update invoice with correct gross and roundoff

            if ($purchaseInvoiceId) {
                PurchaseInvoiceModel::where('id', $purchaseInvoiceId)->update([
                    'gross' => $gross,
                    'round_off' => $roundoff
                ]);
            }

            // **Process Items (Products)**
            if (!empty($itemsData['product'])) {
                foreach ($itemsData['product'] as $index => $productName) {
                    $productId = $existingProducts[$productName] ?? null;

                    if (!$productId) {
                        $errors[] = [
                            'record' => $record,
                            'error' => "Product not found: {$productName}"
                        ];
                        continue;
                    }

                    $productsBatch[] = [
                        'purchase_invoice_id' => $purchaseInvoiceId, // Assign parent ID
                        'company_id' => Auth::user()->company_id,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'description' => $itemsData['desc'][$index] ?? '',
                        'quantity' => $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? '',
                        'price' => isset($itemsData['price'][$index]) && $itemsData['price'][$index] !== '' ? (float)$itemsData['price'][$index] : 0,
                        'discount' => isset($itemsData['discount'][$index]) && $itemsData['discount'][$index] !== ''
                        ? (round((float)$itemsData['discount'][$index], 2) + (round((float)$itemsData['discount'][$index], 2) < (float)$itemsData['discount'][$index] ? 0.01 : 0))
                        : 0,
                        'discount_type' => "percentage",
                        'hsn' => $itemsData['hsn'][$index] ?? '',
                        'tax' => (float)($itemsData['tax'][$index] ?? 0),
                        'cgst' => (float)($itemsData['cgst'][$index] ?? 0),
                        'sgst' => (float)($itemsData['sgst'][$index] ?? 0),
                        'igst' => (float)($itemsData['igst'][$index] ?? 0),
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

                        'godown' => isset($itemsData['place'][$index])
                        ? (
                            strtoupper(trim($itemsData['place'][$index])) === 'OFFICE' ? 1 :
                            (strtoupper(trim($itemsData['place'][$index])) === 'KUSHTIA' ? 2 :
                            (strtoupper(trim($itemsData['place'][$index])) === 'ANKURHATI' ? 3 : null))
                        )
                        : null,
                        'stock' => (float)($itemsData['instock'][$index] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // **Process Addons**
            if (!empty($addonsData)) {
                foreach ($addonsData as $name => $values) {
                    $addonsBatch[] = [
                        'purchase_invoice_id' => $purchaseInvoiceId, // Assign parent ID
                        'company_id' => Auth::user()->company_id,
                        'name' => $name,
                        'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
                        'tax' => 18,
                        'hsn' => $values['hsn'] ?? '',
                        'cgst' => (float)($values['cgst'] ?? 0),
                        'sgst' => (float)($values['sgst'] ?? 0),
                        'igst' => (float)($values['igst'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $successfulInserts++;

            // **Batch Insert when batch size is reached**
            if (count($productsBatch) >= $batchSize) {
                PurchaseInvoiceProductsModel::insert($productsBatch);
                $productsBatch = [];
            }
            if (count($addonsBatch) >= $batchSize) {
                PurchaseInvoiceAddonsModel::insert($addonsBatch);
                $addonsBatch = [];
            }
        }

        // **Insert remaining data**
        if (!empty($productsBatch)) {
            PurchaseInvoiceProductsModel::insert($productsBatch);
        }
        if (!empty($addonsBatch)) {
            PurchaseInvoiceAddonsModel::insert($addonsBatch);
        }

        // **Return response**
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Purchase invoices import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

    // export purchase invoice report
    public function exportPurchaseInvoiceReport(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // Parse filters
            // $supplierIds = $request->filled('supplier_id') ? explode(',', $request->supplier_id) : null;
            $supplierIds = $request->filled('supplier_id') 
            ? array_map('intval', array_map('trim', explode(',', $request->supplier_id))) 
            : null;
            //$productIds = $request->filled('product_id') ? explode(',', $request->product_id) : null;
            $productIds = $request->filled('product_id') 
            ? array_map('intval', array_map('trim', explode(',', $request->product_id))) 
            : null;
            //$groupIds = $request->filled('group_id') ? explode(',', $request->group_id) : null;
            $groupIds = $request->filled('group_id') 
            ? array_map('intval', array_map('trim', explode(',', $request->group_id))) 
            : null;
            //$categoryIds = $request->filled('category_id') ? explode(',', $request->category_id) : null;
            $categoryIds = $request->filled('category_id') 
            ? array_map('intval', array_map('trim', explode(',', $request->category_id))) 
            : null;
            //$subCategoryIds = $request->filled('sub_category_id') ? explode(',', $request->sub_category_id) : null;
            $subCategoryIds = $request->filled('sub_category_id') 
            ? array_map('intval', array_map('trim', explode(',', $request->sub_category_id))) 
            : null;

            // Load invoice products with filters
            // $query = PurchaseInvoiceProductsModel::with([
            //     'purchaseInvoice' => function ($q) use ($companyId, $startDate, $endDate, $supplierIds) {
            //         $q->where('company_id', $companyId)
            //         ->whereBetween('purchase_invoice_date', [$startDate, $endDate]);

            //         if ($supplierIds) {
            //             $q->whereIn('supplier_id', $supplierIds);
            //         }

            //         $q->with('supplier:id,name');
            //     },
            //     'product' => function ($q) use ($groupIds, $categoryIds, $subCategoryIds) {
            //         $q->with('groupRelation:id,name');

            //         if ($groupIds) {
            //             $q->whereIn('group', $groupIds);
            //         }
            //         if ($categoryIds) {
            //             $q->whereIn('category', $categoryIds);
            //         }
            //         if ($subCategoryIds) {
            //             $q->whereIn('sub_category', $subCategoryIds);
            //         }
            //     }
            // ]);
            $query = PurchaseInvoiceProductsModel::with([
                'purchaseInvoice.supplier:id,name',
                'product.groupRelation:id,name'
            ])
            ->whereHas('purchaseInvoice', function ($q) use ($companyId, $startDate, $endDate, $supplierIds) {
                $q->where('company_id', $companyId)
                  ->whereBetween('purchase_invoice_date', [$startDate, $endDate]);
            
                if ($supplierIds) {
                    $q->whereIn('supplier_id', $supplierIds);
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

            // Filter valid entries
            $filtered = $items->filter(fn ($item) => $item->purchaseInvoice !== null);

            // Prepare export data
            $exportData = [];
            $sn = 1;
            foreach ($filtered as $item) {
                $exportData[] = [
                    'SN' => $sn++,
                    'Supplier' => $item->purchaseInvoice->supplier->name ?? 'N/A',
                    'Invoice' => $item->purchaseInvoice->purchase_invoice_no,
                    'Date' => Carbon::parse($item->purchaseInvoice->purchase_invoice_date)->format('d-m-Y'),
                    'Item Name' => $item->product_name,
                    // 'Group' => $item->product->groupRelation->name ?? 'N/A',
                    'Group' => optional(optional($item->product)->groupRelation)->name ?? 'N/A',
                    'Quantity' => $item->quantity,
                    'Unit' => $item->unit,
                    'Price' => $item->price,
                    'Discount' => $item->discount,
                    'Amount' => $item->amount,
                    'Added On' => Carbon::parse($item->created_at)->format('d-m-Y H:i')
                ];
            }

            if (empty($exportData)) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No purchase invoice products found for the selected range.'
                ]);
            }

            // Generate file details
            $fileName = 'purchase_invoices_export_' . now()->format('Ymd_His') . '.xlsx';
            $relativePath = 'purchase_invoices_report/' . $fileName;

            // Store Excel file
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
                        'SN', 'Supplier', 'Invoice', 'Date', 'Item Name', 'Group',
                        'Quantity', 'Unit', 'Price', 'Discount', 'Amount', 'Added On'
                    ];
                }
            }, $relativePath, 'public');

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'File available for download',
                'data' => [
                    'file_url' => asset('storage/' . $relativePath),
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
    public function fetchPurchasesByProduct(Request $request, $productId)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Input Parameters
            $sortField = $request->input('sort_field', 'date');
            $sortOrder = $request->input('sort_order', 'asc');
            $limit = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = $request->input('search');

            $validFields = ['invoice', 'oa', 'date', 'supplier', 'qty', 'in_stock', 'price', 'amount', 'place'];
            if (!in_array($sortField, $validFields)) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invalid sort field.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0
                ], 422);
            }

            // Fetch all relevant records
            $rawRecords = PurchaseInvoiceProductsModel::with([
                    'purchaseInvoice:id,purchase_invoice_no,purchase_invoice_date,oa_no,supplier_id',
                    'purchaseInvoice.supplier:id,name',
                    'godownRelation:id,name'
                ])
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->select('purchase_invoice_id', 'product_id', 'quantity', 'stock', 'price', 'amount', 'godown')
                ->get()
                ->map(function ($item) {
                    return [
                        'invoice'   => optional($item->purchaseInvoice)->purchase_invoice_no,
                        'oa'        => optional($item->purchaseInvoice)->oa_no,
                        'date'      => optional($item->purchaseInvoice)->purchase_invoice_date,
                        'supplier'  => optional($item->purchaseInvoice->supplier)->name,
                        'qty'       => $item->quantity,
                        'in_stock'  => $item->stock,
                        'price'     => $item->price,
                        'amount'    => $item->amount,
                        'place'     => optional($item->godownRelation)->name ?? '-',
                    ];
                })->toArray();

            // Apply search filters (case-insensitive)
            if (!empty($search)) {
                $rawRecords = array_filter($rawRecords, function ($item) use ($search) {
                    return stripos($item['invoice'], $search) !== false ||
                        stripos($item['supplier'], $search) !== false;
                });
            }


            // Sort the filtered data
            usort($rawRecords, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc'
                    ? $a[$sortField] <=> $b[$sortField]
                    : $b[$sortField] <=> $a[$sortField];
            });

            $totalRecords = count($rawRecords);

            // Totals before pagination
            $totalQty = array_sum(array_column($rawRecords, 'qty'));
            $totalStock = array_sum(array_column($rawRecords, 'in_stock'));
            $totalPrice = array_sum(array_column($rawRecords, 'price'));
            $totalAmount = array_sum(array_column($rawRecords, 'amount'));

            // Paginate
            $paginated = array_slice($rawRecords, $offset, $limit);

            // Sub-totals for paginated records
            $subQty = array_sum(array_column($paginated, 'qty'));
            $subStock = array_sum(array_column($paginated, 'in_stock'));
            $subPrice = array_sum(array_column($paginated, 'price'));
            $subAmount = array_sum(array_column($paginated, 'amount'));

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
                'sub_total' => [
                    'qty' => $subQty,
                    'in_stock' => $subStock,
                    'price' => $subPrice,
                    'amount' => $subAmount,
                ],
                'total' => [
                    'qty' => $totalQty,
                    'in_stock' => $totalStock,
                    'price' => $totalPrice,
                    'amount' => $totalAmount,
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error fetching purchases: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0
            ], 500);
        }
    }

}
