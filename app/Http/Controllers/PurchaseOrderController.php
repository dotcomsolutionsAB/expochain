<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderProductsModel;
use App\Models\PurchaseOrderAddonsModel;
use App\Models\PurchaseOrderTermsModel;
use App\Models\SuppliersModel;
use App\Models\ProductsModel;
use App\Models\DiscountModel;
use App\Models\CounterModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
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
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

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
            'products.*.gross'  => 'nullable|numeric|min:0',
            'products.*.channel' => 'nullable|exists:t_channels,id',

            // for add-ons (Array Validation)
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',

            // for terms (Array Validation)
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string|max:255',
            'terms.*.value' => 'required|string|min:0',
        ]);
        
        // Handle quotation number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter/fetch', 'GET', [
            'name' => 'purchase_order',
            'company_id' => Auth::user()->company_id,
        ]);

        $response = $counterController->view($sendRequest);
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
                'code' => 422,
                'success' => false,
                'error' => 'The combination of company_id and purchase_order_no must be unique.',
            ], 422);
        }

        // Fetch supplier details using supplier_id
        $supplier = SuppliersModel::find($request->input('supplier_id'));
        if (!$supplier) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'Supplier not found'], 404);
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
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                'gross'  => $product['gross'] ?? 0,
                'channel' => $product['channel'],
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
                'hsn' =>  $addon['hsn'],
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

        // increment the `next_number` by 1
        CounterModel::where('name', 'purchase_order')
            ->where('company_id', Auth::user()->company_id)
            ->increment('next_number');

        unset($register_purchase_order['id'], $register_purchase_order['created_at'], $register_purchase_order['updated_at']);
    
        return isset($register_purchase_order) && $register_purchase_order !== null
        ? response()->json(['code' => 200, 'success' => true, 'message' => 'Purchase Order registered successfully!', 'data' => $register_purchase_order], 201)
        : response()->json(['code' => 400, 'success' => false, 'message' => 'Failed to register Purchase Order record'], 400);
        
    }

    // view
    // helper function
    private function convertNumberToWords($num) {
        $formatter = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        return ucfirst($formatter->format($num)) . ' Only';
    }
    public function view_purchase_order(Request $request, $id = null)
    {
        // Get filter inputs
        $supplierIdRaw      = $request->input('supplier_id');
        $nameRaw            = $request->input('name');
        $purchaseOrderNoRaw = $request->input('purchase_order_no');
        $purchaseOrderDate  = $request->input('purchase_order_date');
        $dateFrom           = $request->input('date_from');
        $dateTo             = $request->input('date_to');
        $user               = $request->input('user');
        $status             = $request->input('status');
        $productIds         = $request->input('product_ids');
        $limit              = $request->input('limit', 10);
        $offset             = $request->input('offset', 0);


        // Query Purchase Orders
        $query = PurchaseOrderModel::with([
            'products' => function ($query) {
                $query->select(
                    'purchase_order_id',
                    'product_id',
                    'product_name',
                    'description',
                    'quantity',
                    'unit',
                    'price',
                    'discount',
                    'discount_type',
                    'hsn',
                    'tax',
                    DB::raw('(tax / 2) as cgst_rate'),
                    DB::raw('(tax / 2) as sgst_rate'),
                    DB::raw('(tax) as igst_rate'),
                    'cgst',
                    'sgst',
                    'igst',
                    'amount',
                    'gross',       // 🔹 added gross so it comes in response
                    'channel',
                    'received',
                    'short_closed'
                );
            },
            'addons' => function ($query) {
                $query->select('purchase_order_id', 'name', 'amount', 'tax', 'hsn', 'cgst', 'sgst', 'igst');
            },
            'terms' => function ($query) {
                $query->select('purchase_order_id', 'name', 'value');
            },
            'get_user:id,name',
            'get_template:id,name',
            'supplier' => function ($q) {
                $q->select('id', 'supplier_id')
                ->with(['addresses' => function ($query) {
                    $query->select('supplier_id', 'state');
                }]);
            }
        ])
        ->select(
            'id',
            'supplier_id',
            'name',
            'purchase_order_no',
            'purchase_order_date',
            DB::raw('DATE_FORMAT(purchase_order_date, "%d-%m-%Y") as purchase_order_date_formatted'),
            'oa_no',
            DB::raw('DATE_FORMAT(oa_date, "%d-%m-%Y") as oa_date'),
            'template',
            'status',
            'user',
            'cgst',
            'sgst',
            'igst',
            'total',
            'currency',
            'gross',
            'round_off'
        )
        ->where('company_id', Auth::user()->company_id);

        // 🔹 Single Purchase Order by ID
        if ($id) {
            $purchaseOrder = $query->where('id', $id)->first();
            if (!$purchaseOrder) {
                return response()->json([
                    'code'    => 200,
                    'success' => false,
                    'message' => 'Purchase Order not found!',
                    'data'    => null,
                ], 200);
            }

            // Transform Single Purchase Order
            $purchaseOrder->amount_in_words = $this->convertNumberToWords($purchaseOrder->total);
            $purchaseOrder->total          = is_numeric($purchaseOrder->total)
                ? number_format((float) $purchaseOrder->total, 2)
                : $purchaseOrder->total;

            $purchaseOrder->status = ucfirst($purchaseOrder->status);

            $purchaseOrder->user = $purchaseOrder->get_user
                ? ['id' => $purchaseOrder->get_user->id, 'name' => $purchaseOrder->get_user->name]
                : ['id' => null, 'name' => 'Unknown'];
            unset($purchaseOrder->get_user);

            $purchaseOrder->template = $purchaseOrder->get_template
                ? ['id' => $purchaseOrder->get_template->id, 'name' => $purchaseOrder->get_template->name]
                : ['id' => null, 'name' => 'Unknown'];
            unset($purchaseOrder->get_template);

            $purchaseOrder->products->transform(fn ($product) => collect($product)->except(['purchase_order_id']));
            $purchaseOrder->addons->transform(fn ($addon)   => collect($addon)->except(['purchase_order_id']));
            $purchaseOrder->terms->transform(fn ($term)     => collect($term)->except(['purchase_order_id']));

            // supplier: Only return state from addresses
            if ($purchaseOrder->supplier) {
                $state = optional($purchaseOrder->supplier->addresses->first())->state;
                $purchaseOrder->supplier = ['state' => $state];
            } else {
                $purchaseOrder->supplier = null;
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Purchase Order fetched successfully!',
                'data'    => $purchaseOrder,
            ], 200);
        }

        // 🔹 supplier_id: support comma-separated IDs
        if (!empty($supplierIdRaw)) {
            $supplierIds = array_filter(array_map('intval', explode(',', $supplierIdRaw)));
            if (!empty($supplierIds)) {
                $query->whereIn('supplier_id', $supplierIds);
            }
        }

        // 🔹 name: support comma-separated names, each with LIKE
        if (!empty($nameRaw)) {
            $names = array_filter(array_map('trim', explode(',', $nameRaw)));
            if (!empty($names)) {
                $query->where(function ($q) use ($names) {
                    foreach ($names as $name) {
                        $q->orWhere('name', 'LIKE', '%' . $name . '%');
                    }
                });
            }
        }

        // 🔹 purchase_order_no: support comma-separated PO numbers, each with LIKE
        if (!empty($purchaseOrderNoRaw)) {
            $poNos = array_filter(array_map('trim', explode(',', $purchaseOrderNoRaw)));
            if (!empty($poNos)) {
                $query->where(function ($q) use ($poNos) {
                    foreach ($poNos as $no) {
                        $q->orWhere('purchase_order_no', 'LIKE', '%' . $no . '%');
                    }
                });
            }
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
            $query->where('user', $user);
        }
        // 🔹 NEW: status filter
        if ($status) {
            $query->where('status', $status);  // e.g. pending/completed/short_closed
        }
        // 🔹 NEW: product_ids filter (comma-separated)
        if (!empty($productIds)) {
            $productIdArray = array_map('intval', explode(',', $productIds));
            $query->whereHas('products', function ($q) use ($productIdArray) {
                $q->whereIn('product_id', $productIdArray);
            });
        }

        // total record count before pagination
        $totalRecords = $query->count();

        // Order and paginate
        $query->orderBy('purchase_order_date', 'desc')
            ->offset($offset)
            ->limit($limit);

        $get_purchase_orders = $query->get();

        if ($get_purchase_orders->isEmpty()) {
            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'No Purchase Orders found!',
                'data'          => [],
                'count'         => 0,
                'total_records' => 0,
            ], 200);
        }

        // Transform list data
        $get_purchase_orders->transform(function ($purchaseOrder) {
            $purchaseOrder->purchase_order_date = $purchaseOrder->purchase_order_date_formatted;
            unset($purchaseOrder->purchase_order_date_formatted);

            $purchaseOrder->amount_in_words = $this->convertNumberToWords($purchaseOrder->total);
            $purchaseOrder->total          = is_numeric($purchaseOrder->total)
                ? number_format((float) $purchaseOrder->total, 2)
                : $purchaseOrder->total;

            $purchaseOrder->status = ucfirst($purchaseOrder->status);

            $purchaseOrder->user = $purchaseOrder->get_user
                ? ['id' => $purchaseOrder->get_user->id, 'name' => $purchaseOrder->get_user->name]
                : ['id' => null, 'name' => 'Unknown'];
            unset($purchaseOrder->get_user);

            $purchaseOrder->template = $purchaseOrder->get_template
                ? ['id' => $purchaseOrder->get_template->id, 'name' => $purchaseOrder->get_template->name]
                : ['id' => null, 'name' => 'Unknown'];
            unset($purchaseOrder->get_template);

            $purchaseOrder->products->transform(fn ($product) => collect($product)->except(['purchase_order_id']));
            $purchaseOrder->addons->transform(fn ($addon)   => collect($addon)->except(['purchase_order_id']));
            $purchaseOrder->terms->transform(fn ($term)     => collect($term)->except(['purchase_order_id']));

            return $purchaseOrder;
        });

        // Response for list
        return response()->json([
            'code'          => 200,
            'success'       => true,
            'message'       => 'Purchase Orders fetched successfully!',
            'data'          => $get_purchase_orders,
            'count'         => $get_purchase_orders->count(),
            'total_records' => $totalRecords,
        ], 200);
    }

    // update
    public function edit_purchase_order(Request $request, $id)
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
            'name' => 'nullable|string|exists:t_suppliers,name',
            'purchase_order_no' => 'required|string|max:255',
            'purchase_order_date' => 'required|date',
            'oa_no' => 'required|string',
            'oa_date' => 'required|date',
            'template' => 'required|integer|exists:t_pdf_template,id',
            'cgst' => 'nullable|numeric|min:0',
            'sgst' => 'nullable|numeric|min:0',
            'igst' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'gross' => 'required|numeric|min:0',
            'round_off' => 'required|numeric',

            // Products
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
            'products.*.amount' => 'nullable|numeric',
            'products.*.channel' => 'nullable|exists:t_channels,id',

            // 🔥 Added gross validation
            'products.*.gross' => 'nullable|numeric|min:0',

            // addons
            'addons' => 'nullable|array',
            'addons.*.name' => 'required|string|max:255',
            'addons.*.amount' => 'required|numeric|min:0',
            'addons.*.tax' => 'nullable|numeric|min:0',
            'addons.*.hsn' => 'nullable|string|max:255',
            'addons.*.cgst' => 'nullable|numeric|min:0',
            'addons.*.sgst' => 'nullable|numeric|min:0',
            'addons.*.igst' => 'nullable|numeric|min:0',

            // terms
            'terms' => 'nullable|array',
            'terms.*.name' => 'required|string|max:255',
            'terms.*.value' => 'required|string|min:0',
        ]);

        $purchaseOrder = PurchaseOrderModel::where('id', $id)->first();

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
            'gross' => $request->input('gross'),
            'round_off' => $request->input('round_off'),
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
                    'channel' => $productData['channel'],

                    // 🔥 Added gross update
                    'gross' => $productData['gross'],
                ]);
            } else {
                PurchaseOrderProductsModel::create([
                    'purchase_order_id' => $id,
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

                    // 🔥 Added gross create
                    'gross' => $productData['gross'],
                ]);
            }
        }

        // delete removed products
        PurchaseOrderProductsModel::where('purchase_order_id', $id)
            ->whereNotIn('product_id', $requestProductIDs)
            ->delete();

        // addons
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
            ->whereNotIn('name', $requestAddonIDs)
            ->delete();

        // terms
        $terms = $request->input('terms');
        $requestTermsIDs = [];

        foreach ($terms as $termData) {
            $requestTermsIDs[] = $termData['name'];

            $existingTerm = PurchaseOrderTermsModel::where('purchase_order_id', $id)
                ->where('name', $termData['name'])
                ->first();

            if ($existingTerm) {
                $existingTerm->update([
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
            ->whereNotIn('name', $requestTermsIDs)
            ->delete();

        unset($purchaseOrder['created_at'], $purchaseOrder['updated_at']);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Purchase Order and products updated successfully!',
            'data' => $purchaseOrder
        ], 200);
    }

    // public function edit_purchase_order(Request $request, $id)
    // {
    //     $request->validate([
    //         'supplier_id' => 'required|integer|exists:t_suppliers,id',
    //         'name' => 'nullable|string|exists:t_suppliers,name',
    //         'purchase_order_no' => 'required|string|max:255', // Matches DB column
    //         'purchase_order_date' => 'required|date',
    //         'oa_no' => 'required|string', 
    //         'oa_date' => 'required|date', 
    //         'template' => 'required|integer|exists:t_pdf_template,id',
    //         'cgst' => 'nullable|numeric|min:0', // Made nullable, default 0
    //         'sgst' => 'nullable|numeric|min:0', // Made nullable, default 0
    //         'igst' => 'nullable|numeric|min:0', // Made nullable, default 0
    //         'total' => 'required|numeric|min:0',
    //         'currency' => 'required|string|max:10',
    //         'gross' => 'required|numeric|min:0',
    //         'round_off' => 'required|numeric',

    //         // Product Details (Array Validation)
    //         'products' => 'required|array', // Validating array of products
    //         'products.*.product_id' => 'required|integer',
    //         'products.*.product_name' => 'required|string',
    //         'products.*.description' => 'nullable|string',
    //         'products.*.quantity' => 'required|integer',
    //         'products.*.unit' => 'required|string',
    //         'products.*.price' => 'required|numeric',
    //         'products.*.discount' => 'nullable|numeric',
    //         'products.*.discount_type' => 'required|in:percentage,value',
    //         'products.*.hsn' => 'required|string',
    //         'products.*.tax' => 'required|numeric',
    //         'products.*.cgst' => 'required|numeric',
    //         'products.*.sgst' => 'required|numeric',
    //         'products.*.igst' => 'required|numeric',
    //         'products.*.amount' => 'nullable|numeric',
    //         'products.*.channel' => 'nullable|exists:t_channels,id',

    //         // for add-ons
    //         'addons' => 'nullable|array',
    //         'addons.*.name' => 'required|string|max:255',
    //         'addons.*.amount' => 'required|numeric|min:0',
    //         'addons.*.tax' => 'nullable|numeric|min:0',
    //         'addons.*.hsn' => 'nullable|string|max:255',
    //         'addons.*.cgst' => 'nullable|numeric|min:0',
    //         'addons.*.sgst' => 'nullable|numeric|min:0',
    //         'addons.*.igst' => 'nullable|numeric|min:0',

    //         // for terms
    //         'terms' => 'nullable|array',
    //         'terms.*.name' => 'required|string|max:255',
    //         'terms.*.value' => 'required|string|min:0',
    //     ]);

    //     $purchaseOrder = PurchaseOrderModel::where('id', $id)->first();


    //     $purchaseOrderUpdated = $purchaseOrder->update([
    //         'supplier_id' => $request->input('supplier_id'),
    //         'name' => $request->input('name'),
    //         'purchase_order_no' => $request->input('purchase_order_no'),
    //         'purchase_order_date' => $request->input('purchase_order_date'),
    //         'oa_no' => $request->input('oa_no'),
    //         'oa_date' => $request->input('oa_date'),
    //         'template' => $request->input('template'),
    //         'status' => "pending",
    //         'user' => Auth::user()->id,
    //         'cgst' => $request->input('cgst'),
    //         'sgst' => $request->input('sgst'),
    //         'igst' => $request->input('igst'),
    //         'total' => $request->input('total'),
    //         'currency' => $request->input('currency'),
    //         'gross' => $request->input('gross'),
    //         'round_off' => $request->input('round_off'),
    //     ]);

    //     $products = $request->input('products');
    //     $requestProductIDs = [];

    //     foreach ($products as $productData) {
    //         $requestProductIDs[] = $productData['product_id'];

    //         $existingProduct = PurchaseOrderProductsModel::where('purchase_order_id', $id)
    //                                                     ->where('product_id', $productData['product_id'])
    //                                                     ->first();

    //         if ($existingProduct) {
    //             $existingProduct->update([
    //                 'product_name' => $productData['product_name'],
    //                 'description' => $productData['description'],
    //                 'quantity' => $productData['quantity'],
    //                 'unit' => $productData['unit'],
    //                 'price' => $productData['price'],
    //                 'discount' => $productData['discount'],
    //                 'discount_type' => $productData['discount_type'],
    //                 'hsn' => $productData['hsn'],
    //                 'tax' => $productData['tax'],
    //                 'cgst' => $productData['cgst'],
    //                 'sgst' => $productData['sgst'],
    //                 'igst' => $productData['igst'],
    //                 'amount' => $productData['amount'],
    //                 'channel' => $productData['channel'],
    //             ]);
    //         } else {
    //             PurchaseOrderProductsModel::create([
    //                 'purchase_order_id' => $id,
    //                 'company_id' => Auth::user()->company_id,
    //                 'product_id' => $productData['product_id'],
    //                 'product_name' => $productData['product_name'],
    //                 'description' => $productData['description'],
    //                 'quantity' => $productData['quantity'],
    //                 'unit' => $productData['unit'],
    //                 'price' => $productData['price'],
    //                 'discount' => $productData['discount'],
    //                 'discount_type' => $productData['discount_type'],
    //                 'hsn' => $productData['hsn'],
    //                 'tax' => $productData['tax'],
    //                 'cgst' => $productData['cgst'],
    //                 'sgst' => $productData['sgst'],
    //                 'igst' => $productData['igst'],
    //                 'amount' => $productData['amount'],
    //                 'channel' => $productData['channel'],
    //             ]);
    //         }
    //     }

    //     $productsDeleted = PurchaseOrderProductsModel::where('purchase_order_id', $id)
    //                                                 ->whereNotIn('product_id', $requestProductIDs)
    //                                                 ->delete();

    //     $addons = $request->input('addons');
    //     $requestAddonIDs = [];

    //     foreach ($addons as $addonData) {
    //         $requestAddonIDs[] = $addonData['name'];

    //         $existingAddon = PurchaseOrderAddonsModel::where('purchase_order_id', $id)
    //                                             ->where('name', $addonData['name'])
    //                                             ->first();

    //         if ($existingAddon) {
    //             $existingAddon->update([
    //                 'amount' => $addonData['amount'],
    //                 'tax' => $addonData['tax'],
    //                 'hsn' => $addonData['hsn'],
    //                 'cgst' => $addonData['cgst'],
    //                 'sgst' => $addonData['sgst'],
    //                 'igst' => $addonData['igst'],
    //             ]);
    //         } else {
    //             PurchaseOrderAddonsModel::create([
    //                 'purchase_order_id' => $id,
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $addonData['name'],
    //                 'amount' => $addonData['amount'],
    //                 'tax' => $addonData['tax'],
    //                 'hsn' => $addonData['hsn'],
    //                 'cgst' => $addonData['cgst'],
    //                 'sgst' => $addonData['sgst'],
    //                 'igst' => $addonData['igst'],
    //             ]);
    //         }
    //     }

    //     // Delete Addons that are not part of the request
    //     PurchaseOrderAddonsModel::where('purchase_order_id', $id)
    //     ->whereNotIn('name', $requestAddonIDs)
    //     ->delete();

    //     // Handle Terms
    //     $terms = $request->input('terms');
    //     $requestTermsIDs = [];

    //     foreach ($terms as $termData) {
    //         $requestTermsIDs[] = $termData['name'];

    //         $existingAddon = PurchaseOrderTermsModel::where('purchase_order_id', $id)
    //                                             ->where('name', $termData['name'])
    //                                             ->first();

    //         if ($existingAddon) {
    //             $existingAddon->update([
    //                 'value' => $termData['value'],
    //             ]);
    //         } else {
    //             PurchaseOrderTermsModel::create([
    //                 'purchase_order_id' => $id,
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $termData['name'],
    //                 'value' => $termData['value'],
    //             ]);
    //         }
    //     }

    //     // Delete Terms that are not part of the request
    //     PurchaseOrderTermsModel::where('purchase_order_id', $id)
    //                                 ->whereNotIn('name', $requestTermsIDs)
    //                                 ->delete();
                                            

    //     unset($purchaseOrder['created_at'], $purchaseOrder['updated_at']);

    //     return ($purchaseOrderUpdated || $productsDeleted)
    //         ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Order and products updated successfully!', 'data' => $purchaseOrder], 200)
    //         : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    // }

    // delete
    public function delete_purchase_order($id)
    {
        $get_purchase_order_id = PurchaseOrderModel::select('id', 'company_id')->where('id', $id)->first();

        if ($get_purchase_order_id && $get_purchase_order_id->company_id === Auth::user()->company_id) {

            $company_id = Auth::user()->company_id;
            
            $delete_purchase_order = PurchaseOrderModel::where('id', $id)->delete();

            $delete_purchase_order_products = PurchaseOrderProductsModel::where('purchase_order_id', $get_purchase_order_id->id)->delete();

            $delete_purchase_order_addons = PurchaseOrderAddonsModel::where('purchase_order_id', $id)
                                                        ->where('company_id', $company_id)
                                                        ->delete();

            $delete_purchase_order_addons = PurchaseOrderTermsModel::where('purchase_order_id', $id)
                                                        ->where('company_id', $company_id)
                                                        ->delete();


            return $delete_purchase_order && $delete_purchase_order_products
                ? response()->json(['code' => 200,'success' => false, 'message' => 'Purchase Order and associated products deleted successfully!'], 200)
                : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Purchase Order or products.'], 400);
        } else {
            return response()->json(['code' => 404,'success' => false, 'message' => 'Purchase Order not found.'], 404);
        }
    }

    // migration
    // public function importPurchaseOrders()
    // {
    //     set_time_limit(300);
    //     // Increase memory and execution time for large imports
    //     ini_set('max_execution_time', 300); // 5 minutes
    //     ini_set('memory_limit', '1024M');   // Increase memory limit

    //     try {
    //         // Reset Tables & Auto-Increment Properly
    //         // DB::statement("SET FOREIGN_KEY_CHECKS=0;");
    //         PurchaseOrderModel::truncate();
    //         PurchaseOrderProductsModel::truncate();
    //         PurchaseOrderAddonsModel::truncate();
    //         PurchaseOrderTermsModel::truncate();
    //         // DB::statement("ALTER TABLE t_purchase_order AUTO_INCREMENT = 1;");
    //         // DB::statement("ALTER TABLE t_purchase_order_products AUTO_INCREMENT = 1;");
    //         // DB::statement("ALTER TABLE t_purchase_order_addons AUTO_INCREMENT = 1;");
    //         // DB::statement("ALTER TABLE t_purchase_order_terms AUTO_INCREMENT = 1;");
    //         // DB::statement("SET FOREIGN_KEY_CHECKS=1;");

    //         // Fetch Data from External API
    //         $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_order.php';
    //         $response = Http::timeout(120)->get($url);

    //         if ($response->failed()) {
    //             return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
    //         }

    //         $data = $response->json('data');

    //         if (empty($data)) {
    //             return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
    //         }

    //         // Define Batch Size
    //         $batchSize = 500;
    //         $purchaseOrdersBatch = [];
    //         $purchaseOrderNos = [];
    //         $productsBatch = [];
    //         $addonsBatch = [];
    //         $termsBatch = [];

    //         // Step 1: Insert Purchase Orders
    //         foreach ($data as $record) {

    //             $taxData = json_decode($record['tax'], true);

    //             $supplier = SuppliersModel::where('name', $record['supplier'])->first();
    //             $supplierId = $supplier->id ?? 0;

    //             // Format Purchase Order Date
    //             $formattedDate = (!empty($record['po_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $record['po_date']) && $record['po_date'] !== '0000-00-00')
    //                 ? date('Y-m-d', strtotime($record['po_date']))
    //                 : null;

    //             $statusMap = [
    //                 0 => 'pending',
    //                 1 => 'partial',
    //                 2 => 'completed',
    //                 3 => 'short_closed'
    //             ];

    //             // Prepare Purchase Order Data
    //             $purchaseOrdersBatch[] = [
    //                 'company_id' => Auth::user()->company_id,
    //                 'supplier_id' => $supplierId,
    //                 'name' => $supplier->name ?? "Unknown Supplier",
    //                 'purchase_order_no' => $record['po_no'] ?? null,
    //                 'purchase_order_date' => $formattedDate,
    //                 'oa_no' => $record['oa'],
    //                 'oa_date' => $record['oa_date'],
    //                 'template' => json_decode($record['pdf_template'], true)['id'] ?? null,
    //                 'status' => $statusMap[$record['status']] ?? 'pending',
    //                 'user' => Auth::user()->id,
    //                 'cgst' => !empty($taxData['cgst']) ? $taxData['cgst'] : 0,
    //                 'sgst' => !empty($taxData['sgst']) ? $taxData['sgst'] : 0,
    //                 'igst' => !empty($taxData['igst']) ? $taxData['igst'] : 0,
    //                 'total' => !empty($record['total']) ?? null,
    //                 'currency' => !empty($record['currency']) ?? null,
    //                 'gross' => 0,
    //                 'round_off' => 0,
    //                 'created_at' => now(),
    //                 'updated_at' => now()
    //             ];

    //             $purchaseOrderNos[] = $record['po_no'];

    //             // Insert in batches
    //             if (count($purchaseOrdersBatch) >= $batchSize) {
    //                 PurchaseOrderModel::insert($purchaseOrdersBatch);
    //                 $purchaseOrdersBatch = [];
    //             }
    //         }

    //         // Insert Remaining Purchase Orders
    //         if (!empty($purchaseOrdersBatch)) {
    //             PurchaseOrderModel::insert($purchaseOrdersBatch);
    //         }

    //         // Step 2: Fetch Newly Inserted Purchase Order IDs
    //         $purchaseOrderIds = PurchaseOrderModel::whereIn('purchase_order_no', $purchaseOrderNos)
    //             ->pluck('id', 'purchase_order_no')
    //             ->toArray();

    //         // Step 3: Insert Products, Addons, and Terms with Proper ID Matching
    //         foreach ($data as $record) {
    //             $purchaseOrderId = $purchaseOrderIds[$record['po_no']] ?? null;
    //             if (!$purchaseOrderId) {
    //                 continue;
    //             }

    //             // Decode JSON fields
    //             $itemsData = json_decode($record['items'] ?? '{}', true);
    //             $addonsData = json_decode($record['addons'] ?? '{}', true);
    //             $termsData = json_decode($record['top'] ?? '{}', true);

    //             // Insert Products
    //             foreach ($itemsData['product'] as $index => $productName) {

    //                 $get_product = ProductsModel::where('name', $productName)->first();
    //                 $productId = $get_product ? $get_product->id : 0;

    //                 $productsBatch[] = [
    //                     'purchase_order_id' => $purchaseOrderId,
    //                     'company_id' => Auth::user()->company_id,
    //                     'product_id' => $productId,
    //                     'product_name' => $productName,
    //                         'description' => $itemsData['desc'][$index] ?? 'No Description',
    //                         'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
    //                         'unit' => $itemsData['unit'][$index] ?? '',
    //                         'price' => (float) $itemsData['price'][$index] ?? 0.0,
    //                         'discount' => isset($itemsData['discount'][$index]) && $itemsData['discount'][$index] !== '' ? (float) $itemsData['discount'][$index] : 0.0,
    //                         'discount_type' => "percentage",
    //                         'hsn' => $itemsData['hsn'][$index] ?? '',
    //                         'tax' => (float) $itemsData['tax'][$index] ?? 0,
    //                         'cgst' => !empty($itemsData['cgst'][$index]) ? $itemsData['cgst'][$index] : 0,
    //                         'sgst' => !empty($itemsData['sgst'][$index]) ? $itemsData['sgst'][$index] : 0,
    //                         'igst' => isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0,
    //                         'amount' => isset($itemsData['amount'][$index]) ? (float) $itemsData['amount'][$index] : 0,
    //                         'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index]) 
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
    //                         'received' => isset($itemsData['received'][$index]) ? (float) $itemsData['received'][$index] : 0,
    //                     'created_at' => now(),
    //                     'updated_at' => now()
    //                 ];
    //             }

    //             // Insert Addons
    //             foreach ($addonsData as $name => $values) {
    //                 $addonsBatch[] = [
    //                     'purchase_order_id' => $purchaseOrderId,
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

    //             // Insert Terms
    //             foreach ($termsData as $key => $value) {
    //                 $termsBatch[] = [
    //                     'purchase_order_id' => $purchaseOrderId,
    //                     'company_id' => Auth::user()->company_id,
    //                     'name' => $key,
    //                     'value' => !empty($value) ? $value : null,
    //                     'created_at' => now(),
    //                     'updated_at' => now()
    //                 ];
    //             }
    //         }

    //         // Insert in Batches
    //         foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
    //             PurchaseOrderProductsModel::insert($chunk);
    //         }
    //         foreach (array_chunk($addonsBatch, $batchSize) as $chunk) {
    //             PurchaseOrderAddonsModel::insert($chunk);
    //         }
    //         foreach (array_chunk($termsBatch, $batchSize) as $chunk) {
    //             PurchaseOrderTermsModel::insert($chunk);
    //         }

    //         DB::commit();
    //         return response()->json(['code' => 200, 'success' => true, 'message' => "Purchase orders import completed successfully."], 200);

    //     } catch (\Exception $e) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Something went wrong: ' . $e->getMessage()], 500);
    //     }
    // }

    public function importPurchaseOrders()
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        try {
            // Start clean
            PurchaseOrderModel::truncate();
            PurchaseOrderProductsModel::truncate();
            PurchaseOrderAddonsModel::truncate();
            PurchaseOrderTermsModel::truncate();

            // Fetch
            $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_order.php';
            $response = Http::timeout(120)->get($url);
            if ($response->failed()) {
                return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
            }

            $data = $response->json('data');
            if (empty($data)) {
                return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
            }

            $batchSize = 500;
            $purchaseOrdersBatch = [];
            $purchaseOrderNos = [];
            $productsBatch = [];
            $addonsBatch = [];
            $termsBatch = [];
            $statusMap = [
                '0' => 'pending', 0 => 'pending',
                '1' => 'partial', 1 => 'partial',
                '2' => 'completed', 2 => 'completed',
                '3' => 'short_closed', 3 => 'short_closed',
            ];

            // Cache suppliers/products
            $supplierMap = SuppliersModel::pluck('id', 'name')->toArray();
            $productMap  = ProductsModel::pluck('id', 'name')->toArray();

            // 1) Parents
            foreach ($data as $record) {
                $supplierName = $record['supplier'] ?? null;
                $supplierId   = $supplierName ? ($supplierMap[$supplierName] ?? null) : null;

                $poDate = (!empty($record['po_date']) && $record['po_date'] !== '0000-00-00')
                    ? date('Y-m-d', strtotime($record['po_date']))
                    : null;

                $taxObj    = $record['tax'] ?? [];
                $addonsObj = $record['addons'] ?? [];
                $tplObj    = $record['pdf_template'] ?? [];
                $itemsArr  = $record['items'] ?? [];

                // PO gross: prefer total_gross → sum item.gross → compute
                $poGross = isset($record['total_gross']) ? round((float)$record['total_gross'], 2) : null;
                if ($poGross === null) {
                    $tmp = 0.0;
                    foreach ($itemsArr as $it) {
                        if (isset($it['gross']) && $it['gross'] !== '') {
                            $tmp += (float)$it['gross'];
                        } else {
                            $q = isset($it['quantity']) ? (float)$it['quantity'] : 0.0;
                            $p = isset($it['price'])    ? (float)$it['price']    : 0.0;
                            $discRaw = isset($it['discount']) && $it['discount'] !== '' ? (float)$it['discount'] : 0.0;
                            $disc    = round($discRaw, 2);
                            if ($disc < $discRaw) $disc += 0.01;
                            $tmp += $q * ($p - ($disc * $p) / 100);
                        }
                    }
                    $poGross = round($tmp, 2);
                }

                $purchaseOrdersBatch[] = [
                    'company_id'           => Auth::user()->company_id,
                    'supplier_id'          => $supplierId ?? 0,
                    'name'                 => $supplierName ?? 'Unknown Supplier',
                    'purchase_order_no'    => $record['po_no'] ?? null,
                    'purchase_order_date'  => $poDate,
                    'oa_no'                => $record['oa'] ?? null,
                    'oa_date'              => !empty($record['oa_date']) && $record['oa_date'] !== '0000-00-00'
                                                ? date('Y-m-d', strtotime($record['oa_date'])) : null,
                    'template'             => isset($tplObj['id']) ? (int)$tplObj['id'] : null,
                    'status'               => $statusMap[$record['status'] ?? '0'] ?? 'pending',
                    'user'                 => Auth::user()->id,
                    'cgst'                 => isset($taxObj['cgst']) ? (float)$taxObj['cgst'] : 0.0,
                    'sgst'                 => isset($taxObj['sgst']) ? (float)$taxObj['sgst'] : 0.0,
                    'igst'                 => isset($taxObj['igst']) ? (float)$taxObj['igst'] : 0.0,
                    'total'                => isset($record['total']) ? (float)$record['total'] : null,
                    'currency'             => isset($record['currency']) ? (string)$record['currency'] : null,
                    'gross'                => $poGross,               // 2 decimals
                    'round_off'            => isset($addonsObj['roundoff']) ? (float)$addonsObj['roundoff'] : 0.0,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ];

                $purchaseOrderNos[] = $record['po_no'] ?? null;

                if (count($purchaseOrdersBatch) >= $batchSize) {
                    PurchaseOrderModel::insert($purchaseOrdersBatch);
                    $purchaseOrdersBatch = [];
                }
            }

            if (!empty($purchaseOrdersBatch)) {
                PurchaseOrderModel::insert($purchaseOrdersBatch);
            }

            // Map PO numbers → IDs
            $purchaseOrderIds = PurchaseOrderModel::whereIn('purchase_order_no', array_filter($purchaseOrderNos))
                ->pluck('id', 'purchase_order_no')->toArray();

            // 2) Children
            foreach ($data as $record) {
                $poNo = $record['po_no'] ?? null;
                $poId = $poNo ? ($purchaseOrderIds[$poNo] ?? null) : null;
                if (!$poId) continue;

                $itemsArr  = $record['items'] ?? [];
                $addonsObj = $record['addons'] ?? [];
                $termsObj  = $record['top'] ?? []; // already an object

                // PRODUCTS (array of objects)
                foreach ($itemsArr as $item) {
                    $productName = $item['product'] ?? null;
                    if (!$productName) continue;

                    $productId = $productMap[$productName] ?? 0;

                    $qty   = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
                    $price = isset($item['price'])    ? (float)$item['price']    : 0.0;

                    $discRaw = isset($item['discount']) && $item['discount'] !== '' ? (float)$item['discount'] : 0.0;
                    $disc    = round($discRaw, 2);
                    if ($disc < $discRaw) $disc += 0.01;

                    // line gross → prefer API; else compute
                    if (isset($item['gross']) && $item['gross'] !== '') {
                        $lineGross = round((float)$item['gross'], 2);
                    } else {
                        $lineGross = round($qty * ($price - ($disc * $price) / 100), 2);
                    }

                    $lineCgst = isset($item['cgst']) ? (float)$item['cgst'] : 0.0;
                    $lineSgst = isset($item['sgst']) ? (float)$item['sgst'] : 0.0;
                    $lineIgst = isset($item['igst']) ? (float)$item['igst'] : 0.0;

                    $lineAmount = round($lineGross + $lineCgst + $lineSgst + $lineIgst, 2);

                    $productsBatch[] = [
                        'purchase_order_id' => $poId,
                        'company_id'        => Auth::user()->company_id,
                        'product_id'        => $productId,
                        'product_name'      => $productName,
                        'description'       => $item['desc'] ?? '',
                        'quantity'          => $qty,
                        'unit'              => $item['unit'] ?? '',
                        'price'             => $price,
                        'discount'          => $disc,
                        'discount_type'     => 'percentage',
                        'hsn'               => $item['hsn'] ?? '',
                        'tax'               => isset($item['tax']) ? (float)$item['tax'] : 0.0,
                        'cgst'              => $lineCgst,
                        'sgst'              => $lineSgst,
                        'igst'              => $lineIgst,
                        'gross'             => $lineGross,     // <-- REQUIRED in DB
                        'amount'            => $lineAmount,
                        'channel'           => array_key_exists('channel', $item)
                                                ? (is_numeric($item['channel']) ? (float)$item['channel'] : (
                                                    strtolower((string)$item['channel']) === 'standard'      ? 1 :
                                                    (strtolower((string)$item['channel']) === 'non-standard' ? 2 :
                                                    (strtolower((string)$item['channel']) === 'cbs'          ? 3 : null))
                                                ))
                                                : null,
                        'received'          => isset($item['received']) ? (float)$item['received'] : 0.0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];

                    if (count($productsBatch) >= $batchSize) {
                        PurchaseOrderProductsModel::insert($productsBatch);
                        $productsBatch = [];
                    }
                }

                // ADDONS (skip roundoff as a row; keep others)
                if (!empty($addonsObj)) {
                    foreach ($addonsObj as $name => $values) {
                        if (strtolower($name) === 'roundoff') continue;

                        $valCgst = is_array($values) && array_key_exists('cgst', $values) ? (float)$values['cgst'] : 0.0;
                        $valSgst = is_array($values) && array_key_exists('sgst', $values) ? (float)$values['sgst'] : 0.0;
                        $valIgst = is_array($values) && array_key_exists('igst', $values) ? (float)$values['igst'] : 0.0;
                        $valHsn  = is_array($values) && array_key_exists('hsn',  $values) ? (string)$values['hsn'] : '';

                        $addonsBatch[] = [
                            'purchase_order_id' => $poId,
                            'company_id'        => Auth::user()->company_id,
                            'name'              => $name,
                            'amount'            => round($valCgst + $valSgst + $valIgst, 2),
                            'tax'               => 18,
                            'hsn'               => $valHsn,
                            'cgst'              => $valCgst,
                            'sgst'              => $valSgst,
                            'igst'              => $valIgst,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ];

                        if (count($addonsBatch) >= $batchSize) {
                            PurchaseOrderAddonsModel::insert($addonsBatch);
                            $addonsBatch = [];
                        }
                    }
                }

                // TERMS (object with keys)
                if (!empty($termsObj) && is_array($termsObj)) {
                    foreach ($termsObj as $key => $value) {
                        $termsBatch[] = [
                            'purchase_order_id' => $poId,
                            'company_id'        => Auth::user()->company_id,
                            'name'              => (string)$key,
                            'value'             => ($value !== '' ? $value : null),
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ];

                        if (count($termsBatch) >= $batchSize) {
                            PurchaseOrderTermsModel::insert($termsBatch);
                            $termsBatch = [];
                        }
                    }
                }
            }

            // Flush remaining
            if (!empty($productsBatch)) PurchaseOrderProductsModel::insert($productsBatch);
            if (!empty($addonsBatch))   PurchaseOrderAddonsModel::insert($addonsBatch);
            if (!empty($termsBatch))    PurchaseOrderTermsModel::insert($termsBatch);

            return response()->json(['code' => 200, 'success' => true, 'message' => "Purchase orders import completed successfully."], 200);

        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }


    public function getPendingPurchaseOrders(Request $request)
    {
        // Validate request
        $request->validate([
            'supplier_id' => 'required|integer|exists:t_suppliers,id',
        ]);

        // Get authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['code' => 401, 'success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Fetch pending purchase orders for the given supplier and authenticated company
        $purchaseOrders = PurchaseOrderModel::where('supplier_id', $request->input('supplier_id'))
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->select('id', 'oa_no') // Fetch only `oa_no`
            ->get();

        // Check if any records exist
        if ($purchaseOrders->isEmpty()) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No pending purchase orders found.'], 404);
        }

        // Return the result
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Pending purchase orders oa fetched successfully!',
            'data' => $purchaseOrders
        ], 200);
    }

    // export purchase order report
    public function exportPurchaseOrdersReport(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // 🔹 Pagination controls (optional)
            $limit  = (int) $request->input('limit', 0);   // 0 = no limit
            $offset = (int) $request->input('offset', 0);  // default 0

            // 🔹 Date range is OPTIONAL now
            $startDate = $request->filled('start_date')
                ? Carbon::parse($request->start_date)->startOfDay()
                : null;

            $endDate = $request->filled('end_date')
                ? Carbon::parse($request->end_date)->endOfDay()
                : null;

            // 🔹 Optional filters (comma-separated)
            $supplierIds = $request->filled('supplier_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->supplier_id))))
                : null;

            $productIds = $request->filled('product_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->product_id))))
                : null;

            $groupIds = $request->filled('group_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->group_id))))
                : null;

            $categoryIds = $request->filled('category_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->category_id))))
                : null;

            $subCategoryIds = $request->filled('sub_category_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->sub_category_id))))
                : null;

            // 🔹 NEW: purchase_order_no can be multiple (comma separated)
            $poNumbers = $request->filled('purchase_order_no')
                ? array_filter(array_map('trim', explode(',', $request->purchase_order_no)))
                : null;

            // 🔹 NEW: status filter
            $status = $request->filled('status') ? trim($request->status) : null;

            // 🔹 Build query with relations and filters
            $query = PurchaseOrderProductsModel::with([
                    'purchaseOrder.supplier:id,name',
                    'product.groupRelation:id,name',
                ])
                ->whereHas('purchaseOrder', function ($q) use (
                    $companyId,
                    $startDate,
                    $endDate,
                    $supplierIds,
                    $poNumbers,
                    $status
                ) {
                    $q->where('company_id', $companyId);

                    // Optional date filters
                    if ($startDate && $endDate) {
                        $q->whereBetween('purchase_order_date', [$startDate, $endDate]);
                    } elseif ($startDate) {
                        $q->whereDate('purchase_order_date', '>=', $startDate);
                    } elseif ($endDate) {
                        $q->whereDate('purchase_order_date', '<=', $endDate);
                    }

                    if (!empty($supplierIds)) {
                        $q->whereIn('supplier_id', $supplierIds);
                    }

                    // Multiple PO numbers with LIKE
                    if (!empty($poNumbers)) {
                        $q->where(function ($q2) use ($poNumbers) {
                            foreach ($poNumbers as $no) {
                                $q2->orWhere('purchase_order_no', 'LIKE', '%' . $no . '%');
                            }
                        });
                    }

                    if (!empty($status)) {
                        $q->where('status', $status);
                    }
                });

            // 🔹 Product-related filters (optional)
            if (!empty($productIds)) {
                $query->whereIn('product_id', $productIds);
            }

            if ($groupIds || $categoryIds || $subCategoryIds) {
                $query->whereHas('product', function ($q) use ($groupIds, $categoryIds, $subCategoryIds) {
                    if (!empty($groupIds)) {
                        $q->whereIn('group', $groupIds);
                    }
                    if (!empty($categoryIds)) {
                        $q->whereIn('category', $categoryIds);
                    }
                    if (!empty($subCategoryIds)) {
                        $q->whereIn('sub_category', $subCategoryIds);
                    }
                });
            }

            // 🔹 Order + apply pagination for export rows
            $query->orderBy('id'); // stable ordering by product-row id

            if ($limit > 0) {
                $query->skip($offset)->take($limit);
            }

            $items = $query->get();

            // Filter invalid entries
            $filtered = $items->filter(fn ($item) => $item->purchaseOrder !== null);

            // Build export data
            $exportData = [];
            $sn = 1;

            foreach ($filtered as $item) {
                $exportData[] = [
                    'SN'        => $sn++,
                    'Supplier'  => optional($item->purchaseOrder->supplier)->name ?? 'N/A',
                    'Order'     => $item->purchaseOrder->purchase_order_no,
                    'Date'      => Carbon::parse($item->purchaseOrder->purchase_order_date)->format('d-m-Y'),
                    'Item Name' => $item->product_name,
                    'Group'     => optional(optional($item->product)->groupRelation)->name ?? 'N/A',
                    'Quantity'  => $item->quantity,
                    'Unit'      => $item->unit,
                    'Price'     => $item->price,
                    'Discount'  => $item->discount,
                    'Amount'    => $item->amount,
                    // 'Added On'  => Carbon::parse($item->created_at)->format('d-m-Y H:i'),
                ];
            }

            // ✅ No data = 200 + empty data (not error)
            if (empty($exportData)) {
                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'No purchase order products found for the given filters.',
                    'data'    => [],
                ], 200);
            }

            // Generate Excel file
            $fileName     = 'purchase_orders_export_' . now()->format('Ymd_His') . '.xlsx';
            $relativePath = 'purchase_orders_report/' . $fileName;

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
                        'SN',
                        'Supplier',
                        'Order',
                        'Date',
                        'Item Name',
                        'Group',
                        'Quantity',
                        'Unit',
                        'Price',
                        'Discount',
                        'Amount',
                        // 'Added On',
                    ];
                }
            }, $relativePath, 'public');

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'File available for download',
                'data'    => [
                    'file_url'     => asset('storage/' . $relativePath),
                    'file_name'    => $fileName,
                    'file_size'    => Storage::disk('public')->size($relativePath),
                    'content_type' => 'Excel',
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while generating Excel.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // public function exportPurchaseOrdersReport(Request $request)
    // {
    //     try {
    //         $companyId = Auth::user()->company_id;
    //         $startDate = Carbon::parse($request->start_date)->startOfDay();
    //         $endDate = Carbon::parse($request->end_date)->endOfDay();

    //         // // Load purchase order products with order and supplier
    //         // $items = PurchaseOrderProductsModel::with([
    //         //     'purchaseOrder' => function ($q) use ($companyId, $startDate, $endDate) {
    //         //         $q->where('company_id', $companyId)
    //         //             ->whereBetween('purchase_order_date', [$startDate, $endDate])
    //         //             ->with('supplier:id,name');
    //         //     },
    //         //     'product.groupRelation:id,name'
    //         // ])->get();

    //         // Parse comma-separated filters
    //         $supplierIds = $request->filled('supplier_id') ? array_map('intval', explode(',', $request->supplier_id)) : null;
    //         $productIds = $request->filled('product_id') ? array_map('intval', explode(',', $request->product_id)) : null;
    //         $groupIds = $request->filled('group_id') ? array_map('intval', explode(',', $request->group_id)) : null;
    //         $categoryIds = $request->filled('category_id') ? array_map('intval', explode(',', $request->category_id)) : null;
    //         $subCategoryIds = $request->filled('sub_category_id') ? array_map('intval', explode(',', $request->sub_category_id)) : null;

    //         // Build query with relations and filters
    //         $query = PurchaseOrderProductsModel::with([
    //             'purchaseOrder.supplier:id,name',
    //             'product.groupRelation:id,name'
    //         ])
    //         ->whereHas('purchaseOrder', function ($q) use ($companyId, $startDate, $endDate, $supplierIds) {
    //             $q->where('company_id', $companyId)
    //             ->whereBetween('purchase_order_date', [$startDate, $endDate]);

    //             if ($supplierIds) {
    //                 $q->whereIn('supplier_id', $supplierIds);
    //             }
    //         });

    //         if ($productIds) {
    //             $query->whereIn('product_id', $productIds);
    //         }

    //         if ($groupIds || $categoryIds || $subCategoryIds) {
    //             $query->whereHas('product', function ($q) use ($groupIds, $categoryIds, $subCategoryIds) {
    //                 if ($groupIds) {
    //                     $q->whereIn('group', $groupIds);
    //                 }
    //                 if ($categoryIds) {
    //                     $q->whereIn('category', $categoryIds);
    //                 }
    //                 if ($subCategoryIds) {
    //                     $q->whereIn('sub_category', $subCategoryIds);
    //                 }
    //             });
    //         }

    //         $items = $query->get();

    //         // Filter out entries without a purchase order
    //         $filtered = $items->filter(fn ($item) => $item->purchaseOrder !== null);

    //         // Prepare export data
    //         $exportData = [];
    //         $sn = 1;
    //         foreach ($filtered as $item) {
    //             $exportData[] = [
    //                 'SN' => $sn++,
    //                 'Supplier' => $item->purchaseOrder->supplier->name ?? 'N/A',
    //                 'Order' => $item->purchaseOrder->purchase_order_no,
    //                 'Date' => Carbon::parse($item->purchaseOrder->purchase_order_date)->format('d-m-Y'),
    //                 'Item Name' => $item->product_name,
    //                 'Group' => $item->product->groupRelation->name ?? 'N/A',
    //                 'Quantity' => $item->quantity,
    //                 'Unit' => $item->unit,
    //                 'Price' => $item->price,
    //                 'Discount' => $item->discount,
    //                 'Amount' => $item->amount,
    //                 'Added On' => Carbon::parse($item->created_at)->format('d-m-Y H:i')
    //             ];
    //         }

    //         if (empty($exportData)) {
    //             return response()->json([
    //                 'code' => 404,
    //                 'success' => false,
    //                 'message' => 'No purchase order products found for the selected range.'
    //             ]);
    //         }

    //         // Save Excel file
    //         $fileName = 'purchase_orders_export_' . now()->format('Ymd_His') . '.xlsx';
    //         $relativePath = 'purchase_orders_report/' . $fileName;

    //         Excel::store(new class($exportData) implements FromCollection, WithHeadings {
    //             private $data;
    //             public function __construct($data)
    //             {
    //                 $this->data = $data;
    //             }
    //             public function collection()
    //             {
    //                 return collect($this->data);
    //             }
    //             public function headings(): array
    //             {
    //                 return [
    //                     'SN', 'Supplier', 'Order', 'Date', 'Item Name', 'Group',
    //                     'Quantity', 'Unit', 'Price', 'Discount', 'Amount', 'Added On'
    //                 ];
    //             }
    //         }, $relativePath, 'public');

    //         return response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'File available for download',
    //             'data' => [
    //                 'file_url' => asset('storage/' . $relativePath),
    //                 'file_name' => $fileName,
    //                 'file_size' => Storage::disk('public')->size($relativePath),
    //                 'content_type' => 'Excel'
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'code' => 500,
    //             'success' => false,
    //             'message' => 'Something went wrong while generating Excel.',
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    // fetch by product id
    public function fetchPurchaseOrdersByProduct(Request $request, $productId)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Input Parameters
            $sortField = $request->input('sort_field', 'date');
            $sortOrder = $request->input('sort_order', 'asc');
            $limit = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = $request->input('search');

            // Valid sort fields
            $validSortFields = ['order_no', 'oa_no', 'date', 'supplier', 'qty', 'received', 'price', 'amount'];
            if (!in_array($sortField, $validSortFields)) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invalid sort field.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0
                ], 422);
            }

            // Fetch records
            $records = PurchaseOrderProductsModel::with([
                    'purchaseOrder:id,purchase_order_no,oa_no,purchase_order_date,supplier_id',
                    'purchaseOrder.supplier:id,name'
                ])
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->select('purchase_order_id', 'product_id', 'quantity', 'received', 'price', 'amount')
                ->get()
                ->map(function ($item) {
                    return [
                        'order_no'  => optional($item->purchaseOrder)->purchase_order_no,
                        'oa_no'     => optional($item->purchaseOrder)->oa_no,
                        'date'      => optional($item->purchaseOrder)->purchase_order_date,
                        'supplier'  => optional($item->purchaseOrder->supplier)->name,
                        'qty'       => (float) $item->quantity,
                        'received'  => (float) $item->received,
                        'price'     => (float) $item->price,
                        'amount'    => (float) $item->amount,
                    ];
                })->toArray();

            // Apply filters
            if (!empty($search)) {
                $records = array_filter($records, function ($item) use ($search) {
                    return stripos($item['order_no'], $search) !== false ||
                        stripos($item['oa_no'], $search) !== false ||
                        stripos($item['supplier'], $search) !== false;
                });
            }

            // Sort
            usort($records, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc'
                    ? $a[$sortField] <=> $b[$sortField]
                    : $b[$sortField] <=> $a[$sortField];
            });

            $totalRecords = count($records);

            // Totals (before pagination)
            $totalQty = array_sum(array_column($records, 'qty'));
            $totalReceived = array_sum(array_column($records, 'received'));
            $totalAmount = array_sum(array_column($records, 'amount'));

            // Pagination
            $paginated = array_slice($records, $offset, $limit);

            // Subtotals (current page)
            $subQty = array_sum(array_column($paginated, 'qty'));
            $subReceived = array_sum(array_column($paginated, 'received'));
            $subAmount = array_sum(array_column($paginated, 'amount'));

            $subTotalRow = [
                'order_no' => '',
                'oa_no' => '',
                'date' => '',
                'supplier' => 'SubTotal - ',
                'qty' => $subQty,
                'received' => $subReceived,
                'price' => '',
                'amount' => $subAmount
            ];

            $totalRow = [
                'order_no' => '',
                'oa_no' => '',
                'date' => '',
                'supplier' => 'Total -',
                'qty' => $totalQty,
                'received' => $totalReceived,
                'price' => '',
                'amount' => $totalAmount
            ];

            $paginated[] = $subTotalRow;
            $paginated[] = $totalRow;

            // Final Response
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
                // 'sub_total' => [
                //     'qty' => $subQty,
                //     'received' => $subReceived,
                //     'amount' => $subAmount,
                // ],
                // 'total' => [
                //     'qty' => $totalQty,
                //     'received' => $totalReceived,
                //     'amount' => $totalAmount,
                // ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error fetching purchase orders: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0
            ], 500);
        }
    }
   
    public function fetchPurchaseOrdersAllProduct(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Input Parameters
            $sortField = $request->input('sort_field', 'date');
            $sortOrder = $request->input('sort_order', 'asc');
            $limit = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = $request->input('search');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Valid sort fields
            $validSortFields = ['order_no', 'oa_no', 'date', 'supplier', 'product_name', 'qty', 'received', 'price', 'amount'];
            if (!in_array($sortField, $validSortFields)) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invalid sort field.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0
                ], 422);
            }

            // Query with eager loading
            $query = PurchaseOrderProductsModel::with([
                    'purchaseOrder:id,purchase_order_no,oa_no,purchase_order_date,supplier_id',
                    'purchaseOrder.supplier:id,name',
                    'product:id,name'
                ])
                ->where('company_id', $companyId);

            // Filter by date via purchaseOrder relation
            if ($startDate || $endDate) {
                $query->whereHas('purchaseOrder', function($q) use ($startDate, $endDate) {
                    if ($startDate) $q->where('purchase_order_date', '>=', $startDate);
                    if ($endDate)   $q->where('purchase_order_date', '<=', $endDate);
                });
            }

            // Fetch records
            $records = $query
                ->select('purchase_order_id', 'product_id', 'quantity', 'received', 'price', 'amount')
                ->get()
                ->map(function ($item) {
                    return [
                        'order_no'     => optional($item->purchaseOrder)->purchase_order_no,
                        'oa_no'        => optional($item->purchaseOrder)->oa_no,
                        'date'         => optional($item->purchaseOrder)->purchase_order_date,
                        'supplier'     => optional($item->purchaseOrder->supplier)->name,
                        'product_name' => optional($item->product)->name, // Include product name!
                        'qty'          => (float) $item->quantity,
                        'received'     => (float) $item->received,
                        'price'        => (float) $item->price,
                        'amount'       => (float) $item->amount,
                    ];
                })->toArray();

            // Apply search filter
            if (!empty($search)) {
                $records = array_filter($records, function ($item) use ($search) {
                    return stripos($item['order_no'], $search) !== false ||
                        stripos($item['oa_no'], $search) !== false ||
                        stripos($item['supplier'], $search) !== false ||
                        stripos($item['product_name'], $search) !== false;
                });
            }

            // Sort
            usort($records, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc'
                    ? $a[$sortField] <=> $b[$sortField]
                    : $b[$sortField] <=> $a[$sortField];
            });

            $totalRecords = count($records);

            // Totals (before pagination)
            $totalQty = array_sum(array_column($records, 'qty'));
            $totalReceived = array_sum(array_column($records, 'received'));
            $totalAmount = array_sum(array_column($records, 'amount'));

            // Pagination
            $paginated = array_slice($records, $offset, $limit);

            // Subtotals (current page)
            $subQty = array_sum(array_column($paginated, 'qty'));
            $subReceived = array_sum(array_column($paginated, 'received'));
            $subAmount = array_sum(array_column($paginated, 'amount'));

            $subTotalRow = [
                'order_no' => '',
                'oa_no' => '',
                'date' => '',
                'supplier' => 'SubTotal - ',
                'product_name' => '',
                'qty' => $subQty,
                'received' => $subReceived,
                'price' => '',
                'amount' => $subAmount
            ];

            $totalRow = [
                'order_no' => '',
                'oa_no' => '',
                'date' => '',
                'supplier' => 'Total -',
                'product_name' => '',
                'qty' => $totalQty,
                'received' => $totalReceived,
                'price' => '',
                'amount' => $totalAmount
            ];

            $paginated[] = $subTotalRow;
            $paginated[] = $totalRow;

            // Final Response
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error fetching purchase orders: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0
            ], 500);
        }
    }
}
