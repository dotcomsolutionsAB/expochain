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
use DB;
use NumberFormatter;

class PurchaseOrderController extends Controller
{
    //
    // create
    public function add_purchase_order(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
            'purchase_order_no' => 'required|string|max:255', // Matches DB column
            'purchase_order_date' => 'required|date_format:Y-m-d',
            'oa_no' => 'required|string', 
            'oa_date' => 'required|date', 
            'template' => 'required|integer|exists:t_pdf_template,id',
            'cgst' => 'nullable|numeric|min:0', // Made nullable, default 0
            'sgst' => 'nullable|numeric|min:0', // Made nullable, default 0
            'igst' => 'nullable|numeric|min:0', // Made nullable, default 0
            'total' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',

            // Product Details (Array Validation)
            'products' => 'required|array', // Validating array of products
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
            'products.*.amount' => 'nullable|numeric',

            // for add-ons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',

            // for terms
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string|max:255',
            'terms.*.value' => 'required|string|min:0',
        ]);
        
        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter', 'GET', [
            'name' => 'Purchase Order',
            'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view_counter($sendRequest);
        $decodedResponse = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if ($get_customer_type == "auto") {
            $purchase_order_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $purchase_order_no = $request->input('purchase_order_no');
        }

        $exists = PurchaseOrderModel::where('company_id', Auth::user()->company_id)
            ->where('purchase_order_no', $purchase_order_no)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and purchase_order_no must be unique.',
            ], 422);
        }

        // Fetch supplier details using supplier_id
        $supplier = SuppliersModel::find($request->input('supplier_id'));
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }

        $register_purchase_order = PurchaseOrderModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'company_id' => Auth::user()->company_id,
            'name' => $supplier->name,
            'purchase_order_no' => $purchase_order_no,
            'purchase_order_date' => $request->input('purchase_order_date'),
            'oa_no' => $request->input('oa_no'),
            'oa_date' => $request->input('oa_date'),
            'template' => $request->input('template'),
            'status' => "pending",
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
        ]);

        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            PurchaseOrderProductsModel::create([
                'purchase_order_id' => $register_purchase_order['id'],
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
            PurchaseOrderAddonsModel::create([
                'purchase_order_id' => $register_purchase_order['id'],
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

        // Iterate over the terms array and insert each contact
        foreach ($request->input('terms', []) as $term) {
            PurchaseOrderTermsModel::create([
                'purchase_order_id' => $register_purchase_order['id'],
                'company_id' => Auth::user()->company_id,
                'name' => $term['name'],
                'value' => $term['value'],
            ]);
        }

        unset($register_purchase_order['id'], $register_purchase_order['created_at'], $register_purchase_order['updated_at']);
    
        return isset($register_purchase_order) && $register_purchase_order !== null
        ? response()->json(['Purchase Order registered successfully!', 'data' => $register_purchase_order], 201)
        : response()->json(['Failed to register Purchase Order record'], 400);
    }

    // public function add_purchase_order(Request $request)
    // {
    //     $request->validate([
    //         'supplier_id' => 'required|integer',
    //         'purchase_order_no' => 'required|string',
    //         'purchase_order_date' => 'required|date',
    //         'currency' => 'required|string',
    //         'template' => 'required|integer',
    //         'status' => 'required|integer',
    //         'products' => 'required|array', // Validating array of products
    //         'products.*.product_id' => 'required|integer',
    //         'products.*.quantity' => 'required|integer',
    //         'products.*.godown' => 'required|integer',
    //     ]);

    //     // Fetch supplier details using supplier_id
    //     $supplier = SuppliersModel::find($request->input('supplier_id'));
    //     if (!$supplier) {
    //         return response()->json(['message' => 'Supplier not found'], 404);
    //     }
    
    //     $currentDate = Carbon::now()->toDateString();
    
    //     $register_purchase_order = PurchaseOrderModel::create([
    //         'supplier_id' => $request->input('supplier_id'),
    //         'company_id' => Auth::user()->company_id,
    //         'name' => $supplier->name,
    //         'address_line_1' => $supplier->address_line_1,
    //         'address_line_2' => $supplier->address_line_2,
    //         'city' => $supplier->city,
    //         'pincode' => $supplier->pincode,
    //         'state' => $supplier->state,
    //         'country' => $supplier->country,
    //         'purchase_order_no' => $request->input('purchase_order_no'),
    //         'purchase_order_date' => $currentDate,
    //         'cgst' => 0,
    //         'sgst' => 0,
    //         'igst' => 0,
    //         'total' => 0,
    //         'currency' => $request->input('currency'),
    //         'template' => $request->input('template'),
    //         'status' => $request->input('status'),
        
    //     ]);
        
    //     $products = $request->input('products');
    //     $total_amount = 0;
    //     $total_cgst = 0;
    //     $total_sgst = 0;
    //     $total_igst = 0;
    //     $total_discount = 0;

    //     // Iterate over the products array and insert each contact
    //     foreach ($products as $product) 
    //     {
    //         $product_details = ProductsModel::where('id', $product['product_id'])
    //                                         ->where('company_id', Auth::user()->company_id)
    //                                         ->first();
            
    //         if ($product_details) {
    //             $quantity = $product['quantity'];
    //             $rate = $product_details->sale_price;
    //             $tax_rate = $product_details->tax;

    //            // Calculate the discount based on category or sub-category
    //            $sub_category_discount = DiscountModel::select('discount')
    //                                                 ->where('client', $request->input('supplier_id'))
    //                                                 ->where('sub_category', $product_details->sub_category)
    //                                                 ->first();

    //             $category_discount = DiscountModel::select('discount')
    //                                                 ->where('client', $request->input('supplier_id'))
    //                                                 ->where('category', $product_details->category)
    //                                                 ->first();

    //             $discount_rate = $sub_category_discount->discount ?? $category_discount->discount ?? 0;
    //             $discount_amount = $rate * $quantity * ($discount_rate / 100);
    //             $total_discount += $discount_amount;

    //             // Calculate the total for the product
    //             $product_total = $rate * $quantity - $discount_amount;
    //             $tax_amount = $product_total * ($tax_rate / 100);

    //             // Determine the tax distribution based on the client's state
    //             if (strtolower($supplier->state) === 'west bengal') {
    //                 $cgst = $tax_amount / 2;
    //                 $sgst = $tax_amount / 2;
    //                 $igst = 0;
    //             } else {
    //                 $cgst = 0;
    //                 $sgst = 0;
    //                 $igst = $tax_amount;
    //             }

    //             // Accumulate totals
    //             $total_amount += $product_total;
    //             $total_cgst += $cgst;
    //             $total_sgst += $sgst;
    //             $total_igst += $igst;

    //             PurchaseOrderProductsModel::create([
    //                 'purchase_order_number' => $register_purchase_order['id'],
    //                 'product_id' => $product['product_id'],
    //                 'company_id' => Auth::user()->company_id,
    //                 'product_name' => $product_details->name,
    //                 'description' => $product_details->description,
    //                 'brand' => $product_details->brand,
    //                 'quantity' => $product['quantity'],
    //                 'unit' => $product_details->unit,
    //                 'price' => $rate,
    //                 'discount' => $discount_amount,
    //                 'hsn' => $product_details->hsn,
    //                 'tax' => $product_details->tax,
    //                 'cgst' => $cgst,
    //                 'sgst' => $sgst,
    //                 'igst' => $igst,
    //             ]);
    //         }

    //         else{
    //             return response()->json(['message' => 'Sorry, Products not found'], 404);
    //         }
    //     }

    //     // Update the total amount and tax values in the sales invoice record
    //     $register_purchase_order->update([
    //         'total' => $total_amount,
    //         'cgst' => $total_cgst,
    //         'sgst' => $total_sgst,
    //         'igst' => $total_igst,
    //     ]);

    //     unset($register_purchase_order['id'], $register_purchase_order['created_at'], $register_purchase_order['updated_at']);
    
    //     return isset($register_purchase_order) && $register_purchase_order !== null
    //     ? response()->json(['code' => 201,'success' => true, 'Purchase Order registered successfully!', 'data' => $register_purchase_order, 'total_cgst' => $total_cgst, 'total_sgst' => $total_sgst, 'total_igst' => $total_igst, 'total_discount' => $total_discount, 'total_amount' => $total_amount], 201)
    //     : response()->json(['code' => 400,'success' => false, 'Failed to register Purchase Order record'], 400);
    // }

    // view
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }
    public function view_purchase_order(Request $request)
    {
        // Get filter inputs
        $supplierId = $request->input('supplier_id');
        $name = $request->input('name');
        $purchaseOrderNo = $request->input('purchase_order_no');
        $purchaseOrderDate = $request->input('purchase_order_date');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $user = $request->input('user');
        $status = $request->input('status');
        $productIds = $request->input('product_ids');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Get total count of records in `t_purchase_order`
        $get_purchase_order = PurchaseOrderModel::count(); 

        // Build the query
        $query = PurchaseOrderModel::with(['products' => function ($query) {
            $query->select('purchase_order_number', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'price', 'discount',  'discount_type', 'hsn', 'tax', DB::raw('(tax / 2) as cgst_rate'), DB::raw('(tax / 2) as sgst_rate'), DB::raw('(tax) as igst_rate'), 'cgst', 'sgst', 'igst', 'amount', 'channel', 'received', 'short-closed');
            },'addons' => function ($query) {
                $query->select('quotation_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            }, 'terms' => function ($query) {
                $query->select('quotation_id', 'name', 'value');
            },
                'get_user' => function ($query) { // Fetch only user name
                    $query->select('id', 'name');
            },
                'get_template' => function ($query) { // Fetch template id and name
                $query->select('id', 'name');
            }])
        ->select('id', 'supplier_id', 'name', 'purchase_order_no', 'purchase_order_date', 'oa_no', 'oa_date', 'template', 'status', 'user', 'cgst', 'sgst', 'igst', 'total')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }
        if ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        if ($purchaseOrderNo) {
            $query->where('purchase_order_no', 'LIKE', '%' . $purchaseOrderNo . '%');
        }
        if ($purchaseOrderDate) {
            $query->whereDate('purchase_order_date', $purchaseOrderDate);
        }

        if ($dateFrom && $dateTo) {
            $query->whereBetween('purchase_order_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->whereDate('purchase_order_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('purchase_order_date', '<=', $dateTo);
        }

        if ($user) {
            $query->whereDate('user', $user);
        }

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_purchase_orders = $query->get();

        // Transform Data
        $get_purchase_orders->transform(function ($purchase_orders) {

            // Convert total to words
            $purchase_orders->amount_in_words = $this->convertNumberToWords($quotation->total);

            // ✅ Format total with comma-separated values
            $purchase_orders->total = is_numeric($purchase_orders->total) ? number_format((float) $purchase_orders->total, 2) : $purchase_orders->total;

            // Capitalize the first letter of status
            $purchase_orders->status = ucfirst($purchase_orders->status);

            // Replace user ID with user object
            $purchase_orders->user = isset($purchase_orders->get_user) ? [
                'id' => $purchase_orders->get_user->id,
                'name' => $purchase_orders->get_user->name
            ] : ['id' => null, 'name' => 'Unknown'];
            unset($purchase_orders->get_user);

            // ✅ New Fix for sales_person
            $purchase_orders->sales_person = isset($purchase_orders->salesPerson) ? [
                'id' => $purchase_orders->salesPerson->id,
                'name' => $purchase_orders->salesPerson->name
            ] : ['id' => null, 'name' => 'Unknown'];
            unset($purchase_orders->salesPerson);

            // Replace template ID with template object
            $purchase_orders->template = isset($purchase_orders->get_template) ? [
                'id' => $purchase_orders->get_template->id,
                'name' => $purchase_orders->get_template->name
            ] : ['id' => null, 'name' => 'Unknown'];
            unset($purchase_orders->get_template); // Remove user object after fetching the name

            // **Remove `purchase_orders_id` from products**
            $purchase_orders->products->transform(function ($product) {
                unset($product->purchase_orders_id);
                return $product;
            });

            // **Remove `purchase_orders_id` from addons**
            $purchase_orders->addons->transform(function ($addon) {
                unset($addon->purchase_orders_id);
                return $addon;
            });

            // **Remove `purchase_orders_id` from terms**
            $purchase_orders->terms->transform(function ($term) {
                unset($term->purchase_orders_id);
                return $term;
            });

            return $purchase_orders;
        });

        // Return response
        return $get_purchase_orders->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Purchase Orders fetched successfully!',
                'data' => $get_purchase_orders,
                'fetched_records' => $get_purchase_order->count(),
                'count' => $total_purchase_orders,
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Purchase Orders found!',
            ], 404);
    }

    // update
    public function edit_purchase_order(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
            'purchase_order_no' => 'required|string|max:255', // Matches DB column
            'purchase_order_date' => 'required|date_format:Y-m-d',
            'oa_no' => 'required|string', 
            'oa_date' => 'required|date', 
            'template' => 'required|integer|exists:t_pdf_template,id',
            'cgst' => 'nullable|numeric|min:0', // Made nullable, default 0
            'sgst' => 'nullable|numeric|min:0', // Made nullable, default 0
            'igst' => 'nullable|numeric|min:0', // Made nullable, default 0
            'total' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',

            // Product Details (Array Validation)
            'products' => 'required|array', // Validating array of products
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
            'products.*.amount' => 'nullable|numeric',

            // for add-ons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',

            // for terms
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string|max:255',
            'terms.*.value' => 'required|string|min:0',
        ]);

        $purchaseOrder = PurchaseOrderModel::where('id', $id)->first();

        $exists = PurchaseOrderModel::where('company_id', Auth::user()->company_id)
        ->where('purchase_order_no', $request->input('purchase_order_no'))
        ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'The combination of company_id and purchase_order_no must be unique.',
            ], 422);
        }

        $purchaseOrderUpdated = $purchaseOrder->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'purchase_order_no' => $request->input('purchase_order_no'),
            'purchase_order_date' => $request->input('purchase_order_date'),
            'oa_no' => $request->input('oa_no'),
            'oa_date' => $request->input('oa_date'),
            'template' => $request->input('template'),
            'status' => "pending",
            'user' => Auth::user()->id,
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = PurchaseOrderProductsModel::where('purchase_order_id', $id)
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
                ]);
            } else {
                PurchaseOrderProductsModel::create([
                    'purchase_order_number' => $productData['purchase_order_number'],
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

        $productsDeleted = PurchaseOrderProductsModel::where('purchase_order_id', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        $addons = $request->input('addons');
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = PurchaseOrderAddonsModel::where('purchase_order_id', $id)
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
                PurchaseOrderAddonsModel::create([
                    'purchase_order_id' => $id,
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

        PurchaseOrderAddonsModel::where('purchase_order_id', $id)
                                    ->where('product_id', $requestAddonIDs)
                                    ->delete();

        $terms = $request->input('terms');
        $requestTermsIDs = [];

        foreach ($terms as $termData) {
            $requestTermsIDs[] = $termData['name'];

            $existingAddon = PurchaseOrderTermsModel::where('purchase_order_id', $id)
                                                ->where('name', $termData['name'])
                                                ->first();

            if ($existingAddon) {
                $existingAddon->update([
                    'value' => $termData['value'],
                ]);
            } else {
                PurchaseOrderTermsModel::create([
                    'purchase_order_id' => $id,
                    'company_id' => Auth::user()->company_id,
                    'name' => $termData['name'],
                    'value' => $termData['value'],
                ]);
            }
        }

        PurchaseOrderTermsModel::where('purchase_order_id', $id)
                                    ->where('product_id', $requestTermsIDs)
                                    ->delete();
                                            

        unset($purchaseOrder['created_at'], $purchaseOrder['updated_at']);

        return ($purchaseOrderUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Order and products updated successfully!', 'data' => $purchaseOrder], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_purchase_order($id)
    {
        $get_purchase_order_id = PurchaseOrderModel::select('id', 'company_id')->where('id', $id)->first();

        if ($get_purchase_order_id && $get_purchase_order_id->company_id === Auth::user()->company_id) {
            $delete_purchase_order = PurchaseOrderModel::where('id', $id)->delete();

            $delete_purchase_order_products = PurchaseOrderProductsModel::where('purchase_order_number', $get_purchase_order_id->id)->delete();

            return $delete_purchase_order && $delete_purchase_order_products
                ? response()->json(['code' => 200,'success' => false, 'message' => 'Purchase Order and associated products deleted successfully!'], 200)
                : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Purchase Order or products.'], 400);
        } else {
            return response()->json(['code' => 404,'success' => false, 'message' => 'Purchase Order not found.'], 404);
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
            'code' => 200,
            'success' => true,
            'message' => "Data import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
