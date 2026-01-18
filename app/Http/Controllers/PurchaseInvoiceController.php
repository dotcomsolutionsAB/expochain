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
use App\Models\CounterModel;
use App\Models\SalesInvoiceProductsModel;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;
use Auth;
use DB;
use NumberFormatter;
use DateTime;


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
            'products.*.gross' => 'required|numeric|min:0',
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

        // Handle purchase invoice number logic
        $counterController = new CounterController();
        $sendRequest = Request::create('/counter/fetch', 'GET', [
            'name'       => 'purchase_invoice',
            'company_id' => Auth::user()->company_id,
        ]);

        $response         = $counterController->view($sendRequest);
        $decodedResponse  = json_decode($response->getContent(), true);

        if ($decodedResponse['code'] === 200) {
            $data              = $decodedResponse['data'];
            $get_customer_type = $data[0]['type'];
        }

        if (isset($get_customer_type) && $get_customer_type == "auto") {
            $purchase_invoice_no = $decodedResponse['data'][0]['prefix'] .
                str_pad($decodedResponse['data'][0]['next_number'], 3, '0', STR_PAD_LEFT) .
                $decodedResponse['data'][0]['postfix'];
        } else {
            $purchase_invoice_no = $request->input('purchase_invoice_no');
        }

        $exists = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
            ->where('purchase_invoice_no', $purchase_invoice_no)
            ->exists();


        if ($exists) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'The combination of company_id and purchase_invoice_number must be unique.',
                'data'    => [],
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
            'purchase_invoice_no' => $purchase_invoice_no,
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
                'gross' => $product['gross'],
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

        // increment the `next_number` by 1 for purchase_invoice
        CounterModel::where('name', 'purchase_invoice')
            ->where('company_id', Auth::user()->company_id)
            ->increment('next_number');

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
    public function view_purchase_invoice(Request $request, $id = null)
    {
        // Get filter inputs
        $supplierIdRaw         = $request->input('supplier_id');          // "5" or "5,12"
        $nameRaw               = $request->input('name');                 // "Expo Chain,Bearing"
        $purchaseInvoiceNoRaw  = $request->input('purchase_invoice_no');  // "PI/2025/001,PI/2025/010"
        $purchaseInvoiceIdRaw  = $request->input('purchase_invoice_id');  // "101,205,309" -> id column
        $oaNoRaw               = $request->input('oa_no');                // "OA/1,OA/2"
        $refNoRaw              = $request->input('ref_no');               // "REF1,REF2"
        $purchaseInvoiceDate   = $request->input('purchase_invoice_date'); // exact date
        $dateFrom              = $request->input('date_from');            // "2025-01-01"
        $dateTo                = $request->input('date_to');              // "2025-12-31"
        $productIds            = $request->input('product_ids');          // "11,15,20"
        $limit                 = $request->input('limit', 10);
        $offset                = $request->input('offset', 0);

        // Base query
        $query = PurchaseInvoiceModel::with([
                'products' => function ($query) {
                    $query->select(
                        'purchase_invoice_id',
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
                        'gross',
                        'channel',
                        'godown',
                        'returned',
                        'sold'
                    );
                },
                'addons' => function ($query) {
                    $query->select(
                        'purchase_invoice_id',
                        'name',
                        'amount',
                        'tax',
                        'hsn',
                        'cgst',
                        'sgst',
                        'igst'
                    );
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
                'purchase_invoice_no',
                'purchase_invoice_date',
                DB::raw('DATE_FORMAT(purchase_invoice_date, "%d-%m-%Y") as purchase_invoice_date_formatted'),
                'oa_no',
                'ref_no',
                'template',
                'user',
                'cgst',
                'sgst',
                'igst',
                'total',
                'gross',
                'round_off'
            )
            ->where('company_id', Auth::user()->company_id);

        // 🔹 1) Single Purchase Invoice by ID (route param)
        if ($id) {
            $purchaseInvoice = $query->where('id', $id)->first();

            if (!$purchaseInvoice) {
                return response()->json([
                    'code'    => 200,
                    'success' => false,
                    'message' => 'Purchase Invoice not found!',
                    'data'    => null,
                ], 200);
            }

            // Format date
            $purchaseInvoice->purchase_invoice_date = $purchaseInvoice->purchase_invoice_date_formatted;
            unset($purchaseInvoice->purchase_invoice_date_formatted);

            // Amount in words + total formatting
            $purchaseInvoice->amount_in_words = $this->convertNumberToWords($purchaseInvoice->total);
            $purchaseInvoice->total = is_numeric($purchaseInvoice->total)
                ? number_format((float) $purchaseInvoice->total, 2)
                : $purchaseInvoice->total;

            // User / contact
            $purchaseInvoice->user = $purchaseInvoice->get_user
                ? ['id' => $purchaseInvoice->get_user->id, 'name' => $purchaseInvoice->get_user->name]
                : ['id' => null, 'name' => 'Unknown'];
            $purchaseInvoice->contact_person = $purchaseInvoice->user;
            unset($purchaseInvoice->get_user);

            // Template
            $purchaseInvoice->template = $purchaseInvoice->get_template
                ? ['id' => $purchaseInvoice->get_template->id, 'name' => $purchaseInvoice->get_template->name]
                : ['id' => null, 'name' => 'Unknown'];
            unset($purchaseInvoice->get_template);

            // Products / addons – clean FK
            $purchaseInvoice->products->transform(
                fn ($product) => collect($product)->except(['purchase_invoice_id'])
            );
            $purchaseInvoice->addons->transform(
                fn ($addon) => collect($addon)->except(['purchase_invoice_id'])
            );

            // Supplier: only state
            if ($purchaseInvoice->supplier) {
                $state = optional($purchaseInvoice->supplier->addresses->first())->state;
                $purchaseInvoice->supplier = ['state' => $state];
            } else {
                $purchaseInvoice->supplier = null;
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Purchase Invoice fetched successfully!',
                'data'    => $purchaseInvoice,
            ], 200);
        }

        // 🔹 2) Filters for list

        // supplier_id: "5,12"
        if (!empty($supplierIdRaw)) {
            $supplierIds = array_filter(array_map('intval', explode(',', $supplierIdRaw)));
            if (!empty($supplierIds)) {
                $query->whereIn('supplier_id', $supplierIds);
            }
        }

        // name: "Expo Chain,Bearing Stores"
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

        // purchase_invoice_no: "PI/2025/001,PI/2025/010"
        if (!empty($purchaseInvoiceNoRaw)) {
            $piNos = array_filter(array_map('trim', explode(',', $purchaseInvoiceNoRaw)));
            if (!empty($piNos)) {
                $query->where(function ($q) use ($piNos) {
                    foreach ($piNos as $no) {
                        $q->orWhere('purchase_invoice_no', 'LIKE', '%' . $no . '%');
                    }
                });
            }
        }

        // 🔹 NEW: purchase_invoice_id: "101,205,309" (id column)
        if (!empty($purchaseInvoiceIdRaw)) {
            $piIds = array_filter(array_map('intval', explode(',', $purchaseInvoiceIdRaw)));
            if (!empty($piIds)) {
                $query->whereIn('id', $piIds);
            }
        }

        // 🔹 NEW: oa_no: "OA/2025/001,OA/2025/050"
        if (!empty($oaNoRaw)) {
            $oaNos = array_filter(array_map('trim', explode(',', $oaNoRaw)));
            if (!empty($oaNos)) {
                $query->where(function ($q) use ($oaNos) {
                    foreach ($oaNos as $no) {
                        $q->orWhere('oa_no', 'LIKE', '%' . $no . '%');
                    }
                });
            }
        }

        // 🔹 NEW: ref_no: "REF/123,REF/789"
        if (!empty($refNoRaw)) {
            $refNos = array_filter(array_map('trim', explode(',', $refNoRaw)));
            if (!empty($refNos)) {
                $query->where(function ($q) use ($refNos) {
                    foreach ($refNos as $no) {
                        $q->orWhere('ref_no', 'LIKE', '%' . $no . '%');
                    }
                });
            }
        }

        // Date filters:
        // Priority: date_from/date_to range, else single purchase_invoice_date
        if ($dateFrom && $dateTo) {
            $query->whereBetween('purchase_invoice_date', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->whereDate('purchase_invoice_date', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->whereDate('purchase_invoice_date', '<=', $dateTo);
        } elseif ($purchaseInvoiceDate) {
            $query->whereDate('purchase_invoice_date', $purchaseInvoiceDate);
        }

        // product_ids: "11,15,20"
        if (!empty($productIds)) {
            $productIdArray = array_filter(array_map('intval', explode(',', $productIds)));
            if (!empty($productIdArray)) {
                $query->whereHas('products', function ($q) use ($productIdArray) {
                    $q->whereIn('product_id', $productIdArray);
                });
            }
        }

        // 3) Count before pagination
        $totalRecords = $query->count();

        // 4) Order + pagination
        $query->orderBy('purchase_invoice_date', 'desc')
            ->offset($offset)
            ->limit($limit);

        $get_purchase_invoices = $query->get();

        if ($get_purchase_invoices->isEmpty()) {
            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'No Purchase Invoices found!',
                'data'          => [],
                'count'         => 0,
                'total_records' => 0,
            ], 200);
        }

        // 5) Transform list
        $get_purchase_invoices->transform(function ($invoice) {
            $invoice->purchase_invoice_date = $invoice->purchase_invoice_date_formatted;
            unset($invoice->purchase_invoice_date_formatted);

            $invoice->amount_in_words = $this->convertNumberToWords($invoice->total);
            $invoice->total = is_numeric($invoice->total)
                ? number_format((float) $invoice->total, 2)
                : $invoice->total;

            $invoice->user = $invoice->get_user
                ? ['id' => $invoice->get_user->id, 'name' => $invoice->get_user->name]
                : ['id' => null, 'name' => 'Unknown'];
            $invoice->contact_person = $invoice->user;
            unset($invoice->get_user);

            $invoice->template = $invoice->get_template
                ? ['id' => $invoice->get_template->id, 'name' => $invoice->get_template->name]
                : ['id' => null, 'name' => 'Unknown'];
            unset($invoice->get_template);

            if ($invoice->supplier) {
                $state = optional($invoice->supplier->addresses->first())->state;
                $invoice->supplier = ['state' => $state];
            } else {
                $invoice->supplier = null;
            }

            $invoice->products->transform(
                fn ($product) => collect($product)->except(['purchase_invoice_id'])
            );
            $invoice->addons->transform(
                fn ($addon) => collect($addon)->except(['purchase_invoice_id'])
            );

            return $invoice;
        });

        // 6) Response
        return response()->json([
            'code'          => 200,
            'success'       => true,
            'message'       => 'Purchase Invoices fetched successfully!',
            'data'          => $get_purchase_invoices,
            'count'         => $get_purchase_invoices->count(),
            'total_records' => $totalRecords,
        ], 200);
    }

    // update
    public function edit_purchase_invoice(Request $request, $id)
    {
        $request->validate([
            // Purchase Invoice Fields
            'supplier_id'          => 'required|integer|exists:t_suppliers,id',
            'name'                 => 'nullable|string', // no need exists here; we trust supplier_id
            'purchase_invoice_no'  => 'required|string|max:255',
            'purchase_invoice_date'=> 'required|date_format:Y-m-d',
            'oa_no'                => 'required|string|max:50',
            'ref_no'               => 'required|string|max:50',
            'template'             => 'required|integer|exists:t_pdf_template,id',
            'cgst'                 => 'nullable|numeric|min:0',
            'sgst'                 => 'nullable|numeric|min:0',
            'igst'                 => 'nullable|numeric|min:0',
            'total'                => 'required|numeric|min:0',
            'gross'                => 'required|numeric|min:0',
            'round_off'            => 'required|numeric',
        
            // Product Details (Array Validation)
            'products'                     => 'required|array',
            'products.*.product_id'        => 'required|integer|exists:t_products,id',
            'products.*.product_name'      => 'required|string|max:255',
            'products.*.description'       => 'nullable|string',
            'products.*.quantity'          => 'required|integer|min:1',
            'products.*.unit'              => 'required|string|max:20',
            'products.*.price'             => 'required|numeric|min:0',
            'products.*.discount'          => 'nullable|numeric|min:0',
            'products.*.discount_type'     => 'required|in:percentage,value',
            'products.*.hsn'               => 'required|string|max:20',
            'products.*.tax'               => 'required|numeric|min:0',
            'products.*.cgst'              => 'nullable|numeric|min:0',
            'products.*.sgst'              => 'nullable|numeric|min:0',
            'products.*.igst'              => 'nullable|numeric|min:0',
            'products.*.amount'            => 'nullable|numeric|min:0',
            'products.*.gross'             => 'required|numeric|min:0',
            'products.*.channel'           => 'nullable|exists:t_channels,id',
            'products.*.godown'            => 'nullable|exists:t_godown,id',

            // for add-ons
            'addons'                => 'nullable|array',
            'addons.*.name'         => 'required|string|max:255',
            'addons.*.amount'       => 'required|numeric|min:0',
            'addons.*.tax'          => 'nullable|numeric|min:0',
            'addons.*.hsn'          => 'nullable|string|max:255',
            'addons.*.cgst'         => 'nullable|numeric|min:0',
            'addons.*.sgst'         => 'nullable|numeric|min:0',
            'addons.*.igst'         => 'nullable|numeric|min:0',
        ]);

        // Ensure invoice belongs to current company
        $purchaseInvoice = PurchaseInvoiceModel::where('id', $id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$purchaseInvoice) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Purchase Invoice not found.',
                'data'    => [],
            ], 404);
        }

        // Optional: enforce uniqueness of purchase_invoice_no within company, excluding current invoice
        $exists = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
            ->where('purchase_invoice_no', $request->input('purchase_invoice_no'))
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'The combination of company_id and purchase_invoice_no must be unique.',
                'data'    => [],
            ], 422);
        }
        
        // Header update
        $purchaseInvoiceUpdated = $purchaseInvoice->update([
            'supplier_id'          => $request->input('supplier_id'),
            'name'                 => $request->input('name'),
            'purchase_invoice_no'  => $request->input('purchase_invoice_no'),
            'purchase_invoice_date'=> $request->input('purchase_invoice_date'),
            'oa_no'                => $request->input('oa_no'),
            'ref_no'               => $request->input('ref_no'),
            'template'             => $request->input('template'),
            'user'                 => Auth::user()->id,
            'cgst'                 => $request->input('cgst'),
            'sgst'                 => $request->input('sgst'),
            'igst'                 => $request->input('igst'),
            'total'                => $request->input('total'),
            'gross'                => $request->input('gross'),
            'round_off'            => $request->input('round_off'),
        ]);

        // ---------- Products ----------
        $products         = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
                ->where('product_id', $productData['product_id'])
                ->first();

            $commonData = [
                'product_name' => $productData['product_name'],
                'description'  => $productData['description'],
                'quantity'     => $productData['quantity'],
                'unit'         => $productData['unit'],
                'price'        => $productData['price'],
                'discount'     => $productData['discount'],
                'discount_type'=> $productData['discount_type'],
                'hsn'          => $productData['hsn'],
                'tax'          => $productData['tax'],
                'cgst'         => $productData['cgst'],
                'sgst'         => $productData['sgst'],
                'igst'         => $productData['igst'],
                'amount'       => $productData['amount'],
                'gross'        => $productData['gross'],
                'channel'      => $productData['channel'] ?? null,
                'godown'       => $productData['godown'] ?? null,
            ];

            if ($existingProduct) {
                // Update existing product
                $existingProduct->update($commonData);
            } else {
                // Add new product
                PurchaseInvoiceProductsModel::create(array_merge($commonData, [
                    'purchase_invoice_id' => $id,
                    'company_id'          => Auth::user()->company_id,
                    'product_id'          => $productData['product_id'],
                ]));
            }
        }

        // Delete products that are no longer present
        $productsDeleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
            ->whereNotIn('product_id', $requestProductIDs)
            ->delete();

        // ---------- Addons ----------
        $addons          = $request->input('addons', []); // default []
        $requestAddonIDs = [];

        foreach ($addons as $addonData) {
            $requestAddonIDs[] = $addonData['name'];

            $existingAddon = PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
                ->where('name', $addonData['name'])
                ->first();

            $addonCommon = [
                'amount' => $addonData['amount'],
                'tax'    => $addonData['tax'],
                'hsn'    => $addonData['hsn'],
                'cgst'   => $addonData['cgst'],
                'sgst'   => $addonData['sgst'],
                'igst'   => $addonData['igst'],
            ];

            if ($existingAddon) {
                $existingAddon->update($addonCommon);
            } else {
                PurchaseInvoiceAddonsModel::create(array_merge($addonCommon, [
                    'purchase_invoice_id' => $id,
                    'company_id'          => Auth::user()->company_id,
                    'name'                => $addonData['name'],
                ]));
            }
        }

        // Delete removed addons (if any)
        PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
            ->when(!empty($requestAddonIDs), function ($q) use ($requestAddonIDs) {
                $q->whereNotIn('name', $requestAddonIDs);
            })
            ->when(empty($requestAddonIDs), function ($q) {
                // if addons is empty array or not sent, delete all
                $q->delete();
            });

        // Refresh model for response
        $purchaseInvoice = $purchaseInvoice->fresh()->makeHidden(['created_at', 'updated_at']);

        return ($purchaseInvoiceUpdated || $productsDeleted)
            ? response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Purchase Invoice and products updated successfully!',
                'data'    => $purchaseInvoice,
            ], 200)
            : response()->json([
                'code'    => 304,
                'success' => false,
                'message' => 'No changes detected.',
                'data'    => $purchaseInvoice,
            ], 304);
    }

    // public function edit_purchase_invoice(Request $request, $id)
    // {
    //     $request->validate([
    //         // Purchase Invoice Fields
    //         'supplier_id' => 'required|integer|exists:t_suppliers,id',
    //         'name' => 'nullable|string|exists:t_suppliers,name',
    //         'purchase_invoice_no' => 'required|string|max:255',
    //         'purchase_invoice_date' => 'required|date',
    //         'oa_no' => 'required|string|max:50',
    //         'ref_no' => 'required|string|max:50',
    //         'template' => 'required|integer|exists:t_pdf_template,id',
    //         'cgst' => 'nullable|numeric|min:0',
    //         'sgst' => 'nullable|numeric|min:0',
    //         'igst' => 'nullable|numeric|min:0',
    //         'total' => 'required|numeric|min:0',
    //         'gross' => 'required|numeric|min:0',
    //         'round_off' => 'required|numeric',
        
    //         // Product Details (Array Validation)
    //         'products' => 'required|array',
    //         'products.*.product_id' => 'required|integer|exists:t_products,id',
    //         'products.*.product_name' => 'required|string|max:255',
    //         'products.*.description' => 'nullable|string',
    //         'products.*.quantity' => 'required|integer|min:1',
    //         'products.*.unit' => 'required|string|max:20',
    //         'products.*.price' => 'required|numeric|min:0',
    //         'products.*.discount' => 'nullable|numeric|min:0',
    //         'products.*.discount_type' => 'required|in:percentage,value',
    //         'products.*.hsn' => 'required|string|max:20',
    //         'products.*.tax' => 'required|numeric|min:0',
    //         'products.*.cgst' => 'nullable|numeric|min:0',
    //         'products.*.sgst' => 'nullable|numeric|min:0',
    //         'products.*.igst' => 'nullable|numeric|min:0',
    //         'products.*.amount' => 'nullable|numeric|min:0',
    //         'products.*.channel' => 'nullable|exists:t_channels,id',
    //         'products.*.godown' => 'nullable|exists:t_godown,id',

    //         // for add-ons
    //         'addons' => 'nullable|array',
    //         'addons.*.name' => 'required|string|max:255',
    //         'addons.*.amount' => 'required|numeric|min:0',
    //         'addons.*.tax' => 'nullable|numeric|min:0',
    //         'addons.*.hsn' => 'nullable|string|max:255',
    //         'addons.*.cgst' => 'nullable|numeric|min:0',
    //         'addons.*.sgst' => 'nullable|numeric|min:0',
    //         'addons.*.igst' => 'nullable|numeric|min:0',
    //     ]);

    //     $purchaseInvoice = PurchaseInvoiceModel::where('id', $id)->first();

    //     // $exists = PurchaseInvoiceModel::where('company_id', Auth::user()->company_id)
    //     //     ->where('purchase_invoice_number', $request->input('purchase_invoice_no'))
    //     //     ->exists();

    //     // if ($exists) {
    //     //     return response()->json([
    //     //         'code' => 422,
    //     //         'success' => true,
    //     //         'error' => 'The combination of company_id and purchase_invoice_number must be unique.',
    //     //     ], 422);
    //     // }
        
    //     $purchaseInvoiceUpdated = $purchaseInvoice->update([
    //         'supplier_id' => $request->input('supplier_id'),
    //         'name' => $request->input('name'),
    //         'purchase_invoice_no' => $request->input('purchase_invoice_no'),
    //         'purchase_invoice_date' => $request->input('purchase_invoice_date'),
    //         'oa_no' => $request->input('oa_no'),
    //         'ref_no' => $request->input('ref_no'),
    //         'template' => $request->input('template'),
    //         'user' => Auth::user()->id,
    //         'cgst' => $request->input('cgst'),
    //         'sgst' => $request->input('sgst'),
    //         'igst' => $request->input('igst'),
    //         'total' => $request->input('total'),
    //         'gross' => $request->input('gross'),
    //         'round_off' => $request->input('round_off'),
    //     ]);

    //     $products = $request->input('products');
    //     $requestProductIDs = [];

    //     foreach ($products as $productData) {
    //         $requestProductIDs[] = $productData['product_id'];

    //         $existingProduct = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
    //                                                     ->where('product_id', $productData['product_id'])
    //                                                     ->first();

    //         if ($existingProduct) {
    //             // Update existing product
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
    //                 'godown' => $productData['godown'],
    //             ]);
    //         } else {
    //             // Add new product
    //             PurchaseInvoiceProductsModel::create([
    //                 'purchase_invoice_id' => $id,
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
    //                 'godown' => $productData['godown'],
    //             ]);
    //         }
    //     }

    //     $productsDeleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
    //                                                 ->whereNotIn('product_id', $requestProductIDs)
    //                                                 ->delete();

    //     $addons = $request->input('addons');
    //     $requestAddonIDs = [];

    //     foreach ($addons as $addonData) {
    //         $requestAddonIDs[] = $addonData['name'];

    //         $existingAddon = PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
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
    //             PurchaseInvoiceAddonsModel::create([
    //                 'purchase_invoice_id' => $id,
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

    //     PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
    //                                 ->whereNotIn('name', $requestAddonIDs)
    //                                 ->delete();

    //     unset($purchaseInvoice['created_at'], $purchaseInvoice['updated_at']);

    //     return ($purchaseInvoiceUpdated || $productsDeleted)
    //         ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Invoice and products updated successfully!', 'data' => $purchaseInvoice], 200)
    //         : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    // }

    public function delete_purchase_invoice($id)
    {
        $company_id = Auth::user()->company_id;

        // Ensure invoice belongs to logged-in user's company
        $purchase_invoice = PurchaseInvoiceModel::where('id', $id)
            ->where('company_id', $company_id)
            ->first();

        if (!$purchase_invoice) {
            return response()->json([
                'code'    => 404,
                'success' => false,
                'message' => 'Purchase Invoice not found.',
            ], 404);
        }

        // Delete related products
        $products_deleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)
            ->where('company_id', $company_id)
            ->delete();

        // Delete related addons
        $addons_deleted = PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
            ->where('company_id', $company_id)
            ->delete();

        // Delete the purchase invoice header
        $purchase_invoice_deleted = $purchase_invoice->delete();

        // We only really need header delete to succeed; product/addon count can be 0
        if ($purchase_invoice_deleted) {
            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Purchase Invoice and related products/addons deleted successfully!',
            ], 200);
        }

        return response()->json([
            'code'    => 400,
            'success' => false,
            'message' => 'Failed to delete Purchase Invoice.',
        ], 400);
    }

    // public function delete_purchase_invoice($id)
    // {
    //     $purchase_invoice = PurchaseInvoiceModel::find($id);

    //     $company_id = Auth::user()->company_id;

    //     if (!$purchase_invoice) {
    //         return response()->json(['code' => 404, 'success' => false, 'message' => 'Purchase Invoice not found.'], 404);
    //     }

    //     // Delete related products first
    //     $products_deleted = PurchaseInvoiceProductsModel::where('purchase_invoice_id', $id)->delete();

    //     $delete_products_addons = PurchaseInvoiceAddonsModel::where('purchase_invoice_id', $id)
    //                                                     ->where('company_id', $company_id)
    //                                                     ->delete();

    //     // Delete the purchase invoice
    //     $purchase_invoice_deleted = $purchase_invoice->delete();

    //     return ($products_deleted && $purchase_invoice_deleted && $delete_products_addons)
    //         ? response()->json(['code' => 200,'success' => true, 'message' => 'Purchase Invoice and related products deleted successfully!'], 200)
    //         : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Purchase Invoice.'], 400);
    // }

    // public function importPurchaseInvoices()
    // {
    //     ini_set('max_execution_time', 600); // Increase execution time
    //     ini_set('memory_limit', '1024M');   // Optimize memory usage

    //     // Truncate Purchase Invoice and related tables before import
    //     PurchaseInvoiceModel::truncate();
    //     PurchaseInvoiceProductsModel::truncate();
    //     PurchaseInvoiceAddonsModel::truncate();

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/purchase_invoice.php';

    //     // Fetch data from the external URL
    //     try {
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');
    //     if (empty($data)) {
    //         return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
    //     }

    //     $batchSize = 500; // Optimal batch size for insert
    //     $purchaseInvoicesBatch = [];
    //     $productsBatch = [];
    //     $addonsBatch = [];
    //     $successfulInserts = 0;
    //     $errors = [];

    //     // **Step 1️⃣: Fetch Existing Data in Memory**
    //     $existingSuppliers = SuppliersModel::pluck('id', 'name')->toArray();
    //     $existingProducts = ProductsModel::pluck('id', 'name')->toArray();

    //     foreach ($data as $record) {
    //         // Decode JSON fields
    //         $itemsData = json_decode($record['items'] ?? '{}', true);
    //         $taxData = json_decode($record['tax'] ?? '{}', true);
    //         $addonsData = json_decode($record['addons'] ?? '{}', true);

    //         // Get supplier ID
    //         $supplierId = $existingSuppliers[$record['supplier']] ?? null;
    //         if (!$supplierId) {
    //             $errors[] = [
    //                 'record' => $record,
    //                 'error' => 'Supplier not found: ' . $record['supplier']
    //             ];
    //             continue;
    //         }

    //         // Prepare purchase invoice data
    //         $purchaseInvoicesBatch[] = [
    //             'company_id' => Auth::user()->company_id,
    //             'supplier_id' => $supplierId,
    //             'name' => $record['supplier'] ?? null,
    //             'purchase_invoice_no' => $record['pi_no'] ?? null,
    //             'purchase_invoice_date' => !empty($record['pi_date']) ? date('Y-m-d', strtotime($record['pi_date'])) : null,
    //             'oa_no' => $record['oa_no'] ?? null,
    //             'ref_no' => $record['reference_no'] ?? null,
    //             'template' => json_decode($record['pdf_template'], true)['id'] ?? 0,
    //             'user' => Auth::user()->id,
    //             'cgst' => $taxData['cgst'] ?? 0,
    //             'sgst' => $taxData['sgst'] ?? 0,
    //             'igst' => $taxData['igst'] ?? 0,
    //             'total' => $record['total'] ?? null,
    //             // gross will be updated later after processing items
    //             'gross' => 0,
    //             'round_off' => 0,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ];
    //     }

    //     // **2️⃣ Batch Insert Purchase Invoices and Fetch IDs**
    //     foreach (array_chunk($purchaseInvoicesBatch, $batchSize) as $chunk) {
    //         PurchaseInvoiceModel::insert($chunk);
    //     }

    //     // Fetch newly inserted IDs
    //     $purchaseInvoiceIds = PurchaseInvoiceModel::whereIn('purchase_invoice_no', array_column($purchaseInvoicesBatch, 'purchase_invoice_no'))
    //         ->pluck('id', 'purchase_invoice_no')
    //         ->toArray();

    //     // **3️⃣ Insert Products and Addons**
    //     foreach ($data as $record) {
    //         $purchaseInvoiceId = $purchaseInvoiceIds[$record['pi_no']] ?? null;

    //         if (!$purchaseInvoiceId) {
    //             continue;
    //         }

    //         $itemsData = json_decode($record['items'] ?? '{}', true);
    //         $addonsData = json_decode($record['addons'] ?? '{}', true);

    //         $gross = 0;

    //         if (!empty($itemsData['product'])) {
    //             foreach ($itemsData['product'] as $index => $productName) {
    //                 $qty = isset($itemsData['quantity'][$index]) ? (float) $itemsData['quantity'][$index] : 0;
    //                 $price = isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0;
    //                 $gross += $qty * $price;
    //             }
    //         }

    //         $roundoff = isset($addonsData['roundoff']) ? (float) $addonsData['roundoff'] : 0;

    //         // Now update invoice with correct gross and roundoff

    //         if ($purchaseInvoiceId) {
    //             PurchaseInvoiceModel::where('id', $purchaseInvoiceId)->update([
    //                 'gross' => $gross,
    //                 'round_off' => $roundoff
    //             ]);
    //         }

    //         // **Process Items (Products)**
    //         if (!empty($itemsData['product'])) {
    //             foreach ($itemsData['product'] as $index => $productName) {
    //                 $productId = $existingProducts[$productName] ?? null;

    //                 if (!$productId) {
    //                     $errors[] = [
    //                         'record' => $record,
    //                         'error' => "Product not found: {$productName}"
    //                     ];
    //                     continue;
    //                 }

    //                 $productsBatch[] = [
    //                     'purchase_invoice_id' => $purchaseInvoiceId, // Assign parent ID
    //                     'company_id' => Auth::user()->company_id,
    //                     'product_id' => $productId,
    //                     'product_name' => $productName,
    //                     'description' => $itemsData['desc'][$index] ?? '',
    //                     'quantity' => $itemsData['quantity'][$index] ?? 0,
    //                     'unit' => $itemsData['unit'][$index] ?? '',
    //                     'price' => isset($itemsData['price'][$index]) && $itemsData['price'][$index] !== '' ? (float)$itemsData['price'][$index] : 0,
    //                     'discount' => isset($itemsData['discount'][$index]) && $itemsData['discount'][$index] !== ''
    //                     ? (round((float)$itemsData['discount'][$index], 2) + (round((float)$itemsData['discount'][$index], 2) < (float)$itemsData['discount'][$index] ? 0.01 : 0))
    //                     : 0,
    //                     'discount_type' => "percentage",
    //                     'hsn' => $itemsData['hsn'][$index] ?? '',
    //                     'tax' => (float)($itemsData['tax'][$index] ?? 0),
    //                     'cgst' => (float)($itemsData['cgst'][$index] ?? 0),
    //                     'sgst' => (float)($itemsData['sgst'][$index] ?? 0),
    //                     'igst' => (float)($itemsData['igst'][$index] ?? 0),
    //                     'amount' => (
    //                         (isset($itemsData['quantity'][$index]) ? (float) $itemsData['quantity'][$index] : 0.0) *
    //                         (
    //                             (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0) -
    //                             (
    //                                 ((isset($itemsData['discount'][$index]) ? (float) $itemsData['discount'][$index] : 0.0) *
    //                                 (isset($itemsData['price'][$index]) ? (float) $itemsData['price'][$index] : 0.0)) / 100
    //                             )
    //                         )
    //                     ) + (
    //                         (isset($itemsData['cgst'][$index]) ? (float) $itemsData['cgst'][$index] : 0.0) +
    //                         (isset($itemsData['sgst'][$index]) ? (float) $itemsData['sgst'][$index] : 0.0) +
    //                         (isset($itemsData['igst'][$index]) ? (float) $itemsData['igst'][$index] : 0.0)
    //                     ),
    //                     'channel' => array_key_exists('channel', $itemsData) && isset($itemsData['channel'][$index]) 
    //                             ? (
    //                                 is_numeric($itemsData['channel'][$index]) 
    //                                     ? (float)$itemsData['channel'][$index] 
    //                                     : (
    //                                         strtolower($itemsData['channel'][$index]) === 'standard' ? 1 :
    //                                         (strtolower($itemsData['channel'][$index]) === 'non-standard' ? 2 :
    //                                         (strtolower($itemsData['channel'][$index]) === 'cbs' ? 3 : null))
    //                                     )
    //                             ) 
    //                             : null,

    //                     'godown' => isset($itemsData['place'][$index])
    //                     ? (
    //                         strtoupper(trim($itemsData['place'][$index])) === 'OFFICE' ? 1 :
    //                         (strtoupper(trim($itemsData['place'][$index])) === 'KUSHTIA' ? 2 :
    //                         (strtoupper(trim($itemsData['place'][$index])) === 'ANKURHATI' ? 3 : null))
    //                     )
    //                     : null,
    //                     'sold' => (float)($itemsData['instock'][$index] ?? 0),
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ];
    //             }
    //         }

    //         // **Process Addons**
    //         if (!empty($addonsData)) {
    //             foreach ($addonsData as $name => $values) {
    //                 $addonsBatch[] = [
    //                     'purchase_invoice_id' => $purchaseInvoiceId, // Assign parent ID
    //                     'company_id' => Auth::user()->company_id,
    //                     'name' => $name,
    //                     'amount' => (float)($values['cgst'] ?? 0) + (float)($values['sgst'] ?? 0) + (float)($values['igst'] ?? 0),
    //                     'tax' => 18,
    //                     'hsn' => $values['hsn'] ?? '',
    //                     'cgst' => (float)($values['cgst'] ?? 0),
    //                     'sgst' => (float)($values['sgst'] ?? 0),
    //                     'igst' => (float)($values['igst'] ?? 0),
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ];
    //             }
    //         }

    //         $successfulInserts++;

    //         // **Batch Insert when batch size is reached**
    //         if (count($productsBatch) >= $batchSize) {
    //             PurchaseInvoiceProductsModel::insert($productsBatch);
    //             $productsBatch = [];
    //         }
    //         if (count($addonsBatch) >= $batchSize) {
    //             PurchaseInvoiceAddonsModel::insert($addonsBatch);
    //             $addonsBatch = [];
    //         }
    //     }

    //     // **Insert remaining data**
    //     if (!empty($productsBatch)) {
    //         PurchaseInvoiceProductsModel::insert($productsBatch);
    //     }
    //     if (!empty($addonsBatch)) {
    //         PurchaseInvoiceAddonsModel::insert($addonsBatch);
    //     }

    //     // **Return response**
    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Purchase invoices import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }
    
    // new
    public function importPurchaseInvoices()
    {
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('memory_limit', '1024M');   // Optimize memory usage

        // Truncate Purchase Invoice and related tables before import
        PurchaseInvoiceModel::truncate();
        PurchaseInvoiceProductsModel::truncate();
        PurchaseInvoiceAddonsModel::truncate();
        DB::statement("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode,'NO_ZERO_DATE',''),'NO_ZERO_IN_DATE','')");
        DB::statement("SET SESSION sql_mode = REPLACE(@@sql_mode,'STRICT_TRANS_TABLES','')");

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

        // NOTE: new shape is { meta: {...}, data: [ ...records... ] }
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
        $existingProducts  = ProductsModel::pluck('id', 'name')->toArray();

        // Build the purchase invoice rows (parent) first
        foreach ($data as $record) {
            // New response fields
            $itemsArray  = $record['items'] ?? [];                 // array of item objects
            $taxObj      = $record['tax'] ?? [];                   // object { cgst, sgst, igst }
            $addonsObj   = $record['addons'] ?? [];                // object with nested (freight, pf, roundoff, etc.)
            $templateObj = $record['pdf_template'] ?? [];          // object { id, name }

            // Get supplier ID  (ALLOW NULL)
            $supplierName = $record['supplier'] ?? null;
            $supplierId   = $supplierName ? ($existingSuppliers[$supplierName] ?? null) : null;

            if (!$supplierId && $supplierName) {
                // log a warning but DO NOT skip
                $errors[] = [
                    'record'  => $record,
                    'warning' => 'Supplier not found, saved with supplier_id=null: ' . $supplierName
                ];
            }

            // Prefer API total_gross if present; else sum item.gross; else sum qty*price
            $apiTotalGross = isset($record['total_gross']) ? round((float)$record['total_gross'], 2) : null;
            if ($apiTotalGross === null) {
                $tmpGross = 0.0;
                foreach ($itemsArray as $it) {
                    if (isset($it['gross']) && $it['gross'] !== '') {
                        $tmpGross += (float)$it['gross'];
                    } else {
                        $q = isset($it['quantity']) ? (float)$it['quantity'] : 0.0;
                        $p = isset($it['price'])    ? (float)$it['price']    : 0.0;
                        $tmpGross += $q * $p;
                    }
                }
                $apiTotalGross = $tmpGross;
            }

            $purchaseInvoicesBatch[] = [
                'company_id'            => Auth::user()->company_id,
                'supplier_id'           => $supplierId,                     // <-- can be NULL now
                'name'                  => $supplierName ?? 'Unnamed Supplier',
                'purchase_invoice_no'   => $record['pi_no'] ?? null,
                // 'purchase_invoice_date' => !empty($record['pi_date']) ? date('Y-m-d', strtotime($record['pi_date'])) : null,
                'purchase_invoice_date' => (function($v){ $v=trim((string)($v??'')); return ($v===''||$v==='0')?'0000-00-00':$v; })($record['pi_date']??null),
                'oa_no'                 => $record['oa_no'] ?? null,
                'ref_no'                => $record['reference_no'] ?? null,
                'template'              => isset($templateObj['id']) ? (int)$templateObj['id'] : 0,
                'user'                  => Auth::user()->id,
                'cgst'                  => isset($taxObj['cgst']) ? (float)$taxObj['cgst'] : 0.0,
                'sgst'                  => isset($taxObj['sgst']) ? (float)$taxObj['sgst'] : 0.0,
                'igst'                  => isset($taxObj['igst']) ? (float)$taxObj['igst'] : 0.0,
                'total'                 => isset($record['total']) ? (float)$record['total'] : null,
                'gross'                 => $apiTotalGross,  // will keep; we also update once more below just in case
                'round_off'             => 0,               // updated later from addons.roundoff
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
        }

        // **2️⃣ Batch Insert Purchase Invoices and Fetch IDs**
        foreach (array_chunk($purchaseInvoicesBatch, $batchSize) as $chunk) {
            PurchaseInvoiceModel::insert($chunk);
        }

        // Fetch newly inserted IDs (maps by non-null purchase_invoice_no)
        $purchaseInvoiceIds = PurchaseInvoiceModel::whereIn(
            'purchase_invoice_no',
            array_filter(array_column($purchaseInvoicesBatch, 'purchase_invoice_no'))
        )->pluck('id', 'purchase_invoice_no')->toArray();

        // **3️⃣ Insert Products and Addons**
        foreach ($data as $record) {
            $piNo = $record['pi_no'] ?? null;
            $purchaseInvoiceId = $piNo ? ($purchaseInvoiceIds[$piNo] ?? null) : null;
            if (!$purchaseInvoiceId) {
                continue; // cannot attach lines if we can't map the parent
            }

            $itemsArray  = $record['items'] ?? [];
            $addonsObj   = $record['addons'] ?? [];

            // Prefer API total_gross; fallback as above
            $gross = isset($record['total_gross']) ? (float)$record['total_gross'] : null;
            if ($gross === null) {
                $gross = 0.0;
                foreach ($itemsArray as $it) {
                    if (isset($it['gross']) && $it['gross'] !== '') {
                        $gross += (float)$it['gross'];
                    } else {
                        $q = isset($it['quantity']) ? (float)$it['quantity'] : 0.0;
                        $p = isset($it['price'])    ? (float)$it['price']    : 0.0;
                        $gross += $q * $p;
                    }
                }
            }

            // roundoff now comes from addons.roundoff (string/number)
            $roundoff = 0.0;
            if (isset($addonsObj['roundoff']) && $addonsObj['roundoff'] !== '') {
                $roundoff = (float)$addonsObj['roundoff'];
            }

            // Update invoice gross & round_off
            PurchaseInvoiceModel::where('id', $purchaseInvoiceId)->update([
                'gross'     => round($gross, 2),
                'round_off' => $roundoff,
            ]);

            // **Process Items (Products) — product may be NULL**
            if (!empty($itemsArray)) {
                foreach ($itemsArray as $item) {
                    $productName = $item['product'] ?? null;
                    // keep the line even if name is empty (stores amounts/taxes)
                    $productId = $productName ? ($existingProducts[$productName] ?? null) : null;

                    if (!$productId && $productName) {
                        // soft warning, DO NOT skip
                        $errors[] = [
                            'record'  => $record,
                            'warning' => "Product not found, saved with product_id=null: {$productName}"
                        ];
                    }

                    $qty   = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
                    $price = isset($item['price'])    ? (float)$item['price']    : 0.0;

                    // Discount percent rounding to 2 decimals (keep upward tweak)
                    $discRaw = isset($item['discount']) && $item['discount'] !== '' ? (float)$item['discount'] : 0.0;
                    $discRounded = round($discRaw, 2);
                    if ($discRounded < $discRaw) {
                        $discRounded += 0.01;
                    }

                    $Item_gross = isset($item['gross'])
                                ? round((float)$item['gross'], 2)
                                : round($qty * ($price - (($discRounded * $price) / 100)), 2);

                    $lineCgst = isset($item['cgst']) ? (float)$item['cgst'] : 0.0;
                    $lineSgst = isset($item['sgst']) ? (float)$item['sgst'] : 0.0;
                    $lineIgst = isset($item['igst']) ? (float)$item['igst'] : 0.0;
                    $lineAmount = round($Item_gross + $lineCgst + $lineSgst + $lineIgst, 2);

                    $productsBatch[] = [
                        'purchase_invoice_id' => $purchaseInvoiceId,
                        'company_id'          => Auth::user()->company_id,
                        'product_id'          => $productId,                 // <-- can be NULL now
                        'product_name'        => $productName ?? '',
                        'description'         => $item['desc'] ?? '',
                        'quantity'            => $qty,
                        'unit'                => $item['unit'] ?? '',
                        'price'               => $price,
                        'discount'            => $discRounded,
                        'discount_type'       => "percentage",
                        'hsn'                 => $item['hsn'] ?? '',
                        'tax'                 => isset($item['tax']) ? (float)$item['tax'] : 0.0,
                        'cgst'                => $lineCgst,
                        'sgst'                => $lineSgst,
                        'igst'                => $lineIgst,
                        'amount'              => $lineAmount,
                        'gross'               => $Item_gross,
                        'channel'             => array_key_exists('channel', $item)
                            ? (
                                is_numeric($item['channel'])
                                    ? (float)$item['channel']
                                    : (
                                        strtolower((string)$item['channel']) === 'standard'      ? 1 :
                                        (strtolower((string)$item['channel']) === 'non-standard' ? 2 :
                                        (strtolower((string)$item['channel']) === 'cbs'          ? 3 : null))
                                    )
                            )
                            : null,
                        'godown'              => isset($item['place'])
                            ? (
                                strtoupper(trim((string)$item['place'])) === 'OFFICE'     ? 1 :
                                (strtoupper(trim((string)$item['place'])) === 'KUSHTIA'   ? 2 :
                                (strtoupper(trim((string)$item['place'])) === 'ANKURHATI' ? 3 : null))
                            )
                            : null,
                        'sold'                => isset($item['instock']) ? (float)$item['instock'] : 0.0,
                        'returned'          => isset($item['returned']) ? (float)$item['returned'] : 0.0,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];

                    // Batch insert products as we go
                    if (count($productsBatch) >= $batchSize) {
                        PurchaseInvoiceProductsModel::insert($productsBatch);
                        $productsBatch = [];
                    }
                }
            }

            // **Process Addons** — skip roundoff as a row
            if (!empty($addonsObj)) {
                foreach ($addonsObj as $name => $values) {
                    if (strtolower($name) === 'roundoff') continue;

                    $valCgst = is_array($values) && array_key_exists('cgst', $values) ? (float)$values['cgst'] : 0.0;
                    $valSgst = is_array($values) && array_key_exists('sgst', $values) ? (float)$values['sgst'] : 0.0;
                    $valIgst = is_array($values) && array_key_exists('igst', $values) ? (float)$values['igst'] : 0.0;
                    $valHsn  = is_array($values) && array_key_exists('hsn',  $values) ? (string)$values['hsn'] : '';

                    $addonsBatch[] = [
                        'purchase_invoice_id' => $purchaseInvoiceId,
                        'company_id'          => Auth::user()->company_id,
                        'name'                => $name,
                        'amount'              => $valCgst + $valSgst + $valIgst, // preserve behavior
                        'tax'                 => 18,
                        'hsn'                 => $valHsn,
                        'cgst'                => $valCgst,
                        'sgst'                => $valSgst,
                        'igst'                => $valIgst,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];

                    if (count($addonsBatch) >= $batchSize) {
                        PurchaseInvoiceAddonsModel::insert($addonsBatch);
                        $addonsBatch = [];
                    }
                }
            }

            $successfulInserts++;
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
            'code'    => 200,
            'success' => true,
            'message' => "Purchase invoices import completed with $successfulInserts successful inserts.",
            'errors'  => $errors,
        ], 200);
    }

    // export purchase invoice report
    public function exportPurchaseInvoiceReport(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // 🔹 Pagination controls (optional)
            $limit  = (int) $request->input('limit', 0);   // 0 = no limit
            $offset = (int) $request->input('offset', 0);  // default 0

            // 🔹 Date filters
            $purchaseInvoiceDate = $request->input('purchase_invoice_date');

            $startDate = $request->filled('date_from')
                ? Carbon::parse($request->date_from)->startOfDay()
                : null;

            $endDate = $request->filled('date_to')
                ? Carbon::parse($request->date_to)->endOfDay()
                : null;

            // 🔹 Optional filters (comma-separated)

            // supplier_id: "216,1"
            $supplierIds = $request->filled('supplier_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->supplier_id))))
                : null;

            // name: "KAMALESH BANERJEE,JK FENNER INDIA LTD"
            $nameArray = $request->filled('name')
                ? array_filter(array_map('trim', explode(',', $request->name)))
                : null;

            // purchase_invoice_no: "PI/1002/2025,PI/1001/2025"
            $piNos = $request->filled('purchase_invoice_no')
                ? array_filter(array_map('trim', explode(',', $request->purchase_invoice_no)))
                : null;

            // purchase_invoice_id: "101,205,309"  (id column)
            $purchaseInvoiceIds = $request->filled('purchase_invoice_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->purchase_invoice_id))))
                : null;

            // oa_no: "OA/2025/078,OA/2025/079"
            $oaNos = $request->filled('oa_no')
                ? array_filter(array_map('trim', explode(',', $request->oa_no)))
                : null;

            // ref_no: "REF/PO/4458, REF/PO/4459"
            $refNos = $request->filled('ref_no')
                ? array_filter(array_map('trim', explode(',', $request->ref_no)))
                : null;

            // product_ids: "11,15,20"
            $productIds = $request->filled('product_ids')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->product_ids))))
                : null;

            // group_id: "1,2"
            $groupIds = $request->filled('group_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->group_id))))
                : null;

            // category_id: ""
            $categoryIds = $request->filled('category_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->category_id))))
                : null;

            // sub_category_id: ""
            $subCategoryIds = $request->filled('sub_category_id')
                ? array_filter(array_map('intval', array_map('trim', explode(',', $request->sub_category_id))))
                : null;

            // 🔹 Build query with relations and filters
            $query = PurchaseInvoiceProductsModel::with([
                    'purchaseInvoice.supplier:id,name',
                    'product.groupRelation:id,name',
                ])
                ->whereHas('purchaseInvoice', function ($q) use (
                    $companyId,
                    $startDate,
                    $endDate,
                    $purchaseInvoiceDate,
                    $supplierIds,
                    $nameArray,
                    $piNos,
                    $purchaseInvoiceIds,
                    $oaNos,
                    $refNos
                ) {
                    $q->where('company_id', $companyId);

                    // Date filters: range > single date
                    if ($startDate && $endDate) {
                        $q->whereBetween('purchase_invoice_date', [$startDate, $endDate]);
                    } elseif ($startDate) {
                        $q->whereDate('purchase_invoice_date', '>=', $startDate);
                    } elseif ($endDate) {
                        $q->whereDate('purchase_invoice_date', '<=', $endDate);
                    } elseif ($purchaseInvoiceDate) {
                        $q->whereDate('purchase_invoice_date', $purchaseInvoiceDate);
                    }

                    if (!empty($supplierIds)) {
                        $q->whereIn('supplier_id', $supplierIds);
                    }

                    if (!empty($nameArray)) {
                        $q->where(function ($q2) use ($nameArray) {
                            foreach ($nameArray as $name) {
                                $q2->orWhere('name', 'LIKE', '%' . $name . '%');
                            }
                        });
                    }

                    if (!empty($piNos)) {
                        $q->where(function ($q2) use ($piNos) {
                            foreach ($piNos as $no) {
                                $q2->orWhere('purchase_invoice_no', 'LIKE', '%' . $no . '%');
                            }
                        });
                    }

                    if (!empty($purchaseInvoiceIds)) {
                        $q->whereIn('id', $purchaseInvoiceIds);
                    }

                    if (!empty($oaNos)) {
                        $q->where(function ($q2) use ($oaNos) {
                            foreach ($oaNos as $no) {
                                $q2->orWhere('oa_no', 'LIKE', '%' . $no . '%');
                            }
                        });
                    }

                    if (!empty($refNos)) {
                        $q->where(function ($q2) use ($refNos) {
                            foreach ($refNos as $no) {
                                $q2->orWhere('ref_no', 'LIKE', '%' . $no . '%');
                            }
                        });
                    }
                });

            // 🔹 Product-related filters
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

            // 🔹 Order + optional pagination
            $query->orderBy('id'); // stable order on product rows

            if ($limit > 0) {
                $query->skip($offset)->take($limit);
            }

            $items = $query->get();

            // Filter valid entries (safety, though whereHas already enforces it)
            $filtered = $items->filter(fn ($item) => $item->purchaseInvoice !== null);

            // Prepare export data
            $exportData = [];
            $sn = 1;
            foreach ($filtered as $item) {
                $exportData[] = [
                    'SN'        => $sn++,
                    'Supplier'  => optional($item->purchaseInvoice->supplier)->name ?? 'N/A',
                    'Invoice'   => $item->purchaseInvoice->purchase_invoice_no,
                    'Date'      => Carbon::parse($item->purchaseInvoice->purchase_invoice_date)->format('d-m-Y'),
                    'Item Name' => $item->product_name,
                    'Group'     => optional(optional($item->product)->groupRelation)->name ?? 'N/A',
                    'Quantity'  => $item->quantity,
                    'Unit'      => $item->unit,
                    'Price'     => $item->price,
                    'Discount'  => $item->discount,
                    'Amount'    => $item->amount,
                    'Added On'  => Carbon::parse($item->created_at)->format('d-m-Y H:i'),
                ];
            }

            // ✅ No data = 200 + empty data (not error)
            if (empty($exportData)) {
                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'No purchase invoice products found for the given filters.',
                    'data'    => [],
                ], 200);
            }

            // Generate file details
            $fileName     = 'purchase_invoices_export_' . now()->format('Ymd_His') . '.xlsx';
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
                        'SN',
                        'Supplier',
                        'Invoice',
                        'Date',
                        'Item Name',
                        'Group',
                        'Quantity',
                        'Unit',
                        'Price',
                        'Discount',
                        'Amount',
                        'Added On',
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


    // public function exportPurchaseInvoiceReport(Request $request)
    // {
    //     try {
    //         $companyId = Auth::user()->company_id;
    //         $startDate = Carbon::parse($request->start_date)->startOfDay();
    //         $endDate = Carbon::parse($request->end_date)->endOfDay();

    //         // Parse filters
    //         // $supplierIds = $request->filled('supplier_id') ? explode(',', $request->supplier_id) : null;
    //         $supplierIds = $request->filled('supplier_id') 
    //         ? array_map('intval', array_map('trim', explode(',', $request->supplier_id))) 
    //         : null;
    //         //$productIds = $request->filled('product_id') ? explode(',', $request->product_id) : null;
    //         $productIds = $request->filled('product_id') 
    //         ? array_map('intval', array_map('trim', explode(',', $request->product_id))) 
    //         : null;
    //         //$groupIds = $request->filled('group_id') ? explode(',', $request->group_id) : null;
    //         $groupIds = $request->filled('group_id') 
    //         ? array_map('intval', array_map('trim', explode(',', $request->group_id))) 
    //         : null;
    //         //$categoryIds = $request->filled('category_id') ? explode(',', $request->category_id) : null;
    //         $categoryIds = $request->filled('category_id') 
    //         ? array_map('intval', array_map('trim', explode(',', $request->category_id))) 
    //         : null;
    //         //$subCategoryIds = $request->filled('sub_category_id') ? explode(',', $request->sub_category_id) : null;
    //         $subCategoryIds = $request->filled('sub_category_id') 
    //         ? array_map('intval', array_map('trim', explode(',', $request->sub_category_id))) 
    //         : null;

    //         // Load invoice products with filters
    //         // $query = PurchaseInvoiceProductsModel::with([
    //         //     'purchaseInvoice' => function ($q) use ($companyId, $startDate, $endDate, $supplierIds) {
    //         //         $q->where('company_id', $companyId)
    //         //         ->whereBetween('purchase_invoice_date', [$startDate, $endDate]);

    //         //         if ($supplierIds) {
    //         //             $q->whereIn('supplier_id', $supplierIds);
    //         //         }

    //         //         $q->with('supplier:id,name');
    //         //     },
    //         //     'product' => function ($q) use ($groupIds, $categoryIds, $subCategoryIds) {
    //         //         $q->with('groupRelation:id,name');

    //         //         if ($groupIds) {
    //         //             $q->whereIn('group', $groupIds);
    //         //         }
    //         //         if ($categoryIds) {
    //         //             $q->whereIn('category', $categoryIds);
    //         //         }
    //         //         if ($subCategoryIds) {
    //         //             $q->whereIn('sub_category', $subCategoryIds);
    //         //         }
    //         //     }
    //         // ]);
    //         $query = PurchaseInvoiceProductsModel::with([
    //             'purchaseInvoice.supplier:id,name',
    //             'product.groupRelation:id,name'
    //         ])
    //         ->whereHas('purchaseInvoice', function ($q) use ($companyId, $startDate, $endDate, $supplierIds) {
    //             $q->where('company_id', $companyId)
    //               ->whereBetween('purchase_invoice_date', [$startDate, $endDate]);
            
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

    //          $items = $query->get();

    //         // Filter valid entries
    //         $filtered = $items->filter(fn ($item) => $item->purchaseInvoice !== null);

    //         // Prepare export data
    //         $exportData = [];
    //         $sn = 1;
    //         foreach ($filtered as $item) {
    //             $exportData[] = [
    //                 'SN' => $sn++,
    //                 'Supplier' => $item->purchaseInvoice->supplier->name ?? 'N/A',
    //                 'Invoice' => $item->purchaseInvoice->purchase_invoice_no,
    //                 'Date' => Carbon::parse($item->purchaseInvoice->purchase_invoice_date)->format('d-m-Y'),
    //                 'Item Name' => $item->product_name,
    //                 // 'Group' => $item->product->groupRelation->name ?? 'N/A',
    //                 'Group' => optional(optional($item->product)->groupRelation)->name ?? 'N/A',
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
    //                 'message' => 'No purchase invoice products found for the selected range.'
    //             ]);
    //         }

    //         // Generate file details
    //         $fileName = 'purchase_invoices_export_' . now()->format('Ymd_His') . '.xlsx';
    //         $relativePath = 'purchase_invoices_report/' . $fileName;

    //         // Store Excel file
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
    //                     'SN', 'Supplier', 'Invoice', 'Date', 'Item Name', 'Group',
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
                ->select('purchase_invoice_id', 'product_id', 'quantity', 'sold', 'price', 'amount', 'godown')
                ->get()
                ->map(function ($item) {
                    return [
                        'invoice'   => optional($item->purchaseInvoice)->purchase_invoice_no,
                        'oa'        => optional($item->purchaseInvoice)->oa_no,
                        'date'      => optional($item->purchaseInvoice)->purchase_invoice_date,
                        'supplier'  => optional($item->purchaseInvoice->supplier)->name,
                        'qty'       => $item->quantity,
                        'in_stock'  => $item->sold,
                        'price'     => number_format((float)$item->price, 2, '.', ''),
                        'amount'    => number_format((float)$item->amount, 2, '.', ''),
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

            $subTotalRow = [
                'invoice' => '',
                'oa' => '',
                'date' => '',
                'supplier' => 'SubTotal - ',
                'qty' => $subQty,
                'in_stock' => $subStock,
                'price' => number_format($subPrice, 2, '.', ''),
                'amount' => number_format($subAmount, 2, '.', ''),
                'place' => '',
            ];

            $totalRow = [
                'invoice' => '',
                'oa' => '',
                'date' => '',
                'supplier' => 'Total -',
                'qty' => $totalQty,
                'in_stock' => $totalStock,
                'price' => number_format($totalPrice, 2, '.', ''),
                'amount' => number_format($totalAmount, 2, '.', ''),
                'place' => '',
            ];

            $paginated[] = $subTotalRow;
            $paginated[] = $totalRow;

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
                // 'sub_total' => [
                //     'qty' => $subQty,
                //     'in_stock' => $subStock,
                //     'price' => $subPrice,
                //     'amount' => $subAmount,
                // ],
                // 'total' => [
                //     'qty' => $totalQty,
                //     'in_stock' => $totalStock,
                //     'price' => $totalPrice,
                //     'amount' => $totalAmount,
                // ]
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

    public function fetchPurchasesAllProduct(Request $request)
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

            $validFields = ['invoice', 'oa', 'date', 'supplier', 'product_name', 'qty', 'in_stock', 'price', 'amount', 'place'];
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

            // Query with eager loading
            $query = PurchaseInvoiceProductsModel::with([
                    'purchaseInvoice:id,purchase_invoice_no,purchase_invoice_date,oa_no,supplier_id',
                    'purchaseInvoice.supplier:id,name',
                    'product:id,name',
                    'godownRelation:id,name'
                ])
                ->where('company_id', $companyId);

            // Apply date filter via purchaseInvoice relation
            if ($startDate || $endDate) {
                $query->whereHas('purchaseInvoice', function ($q) use ($startDate, $endDate) {
                    if ($startDate) $q->where('purchase_invoice_date', '>=', $startDate);
                    if ($endDate) $q->where('purchase_invoice_date', '<=', $endDate);
                });
            }

            // Get all records (no product_id filtering)
            $rawRecords = $query
                ->select('purchase_invoice_id', 'product_id', 'quantity', 'sold', 'price', 'amount', 'godown')
                ->get()
                ->map(function ($item) {
                    return [
                        'invoice'      => optional($item->purchaseInvoice)->purchase_invoice_no,
                        'oa'           => optional($item->purchaseInvoice)->oa_no,
                        'date'         => optional($item->purchaseInvoice)->purchase_invoice_date,
                        'supplier'     => optional($item->purchaseInvoice->supplier)->name,
                        'product_name' => optional($item->product)->name,
                        'qty'          => $item->quantity,
                        'in_stock'     => $item->sold,
                        'price'        => number_format((float)$item->price, 2, '.', ''),
                        'amount'       => number_format((float)$item->amount, 2, '.', ''),
                        'place'        => optional($item->godownRelation)->name ?? '-',
                    ];
                })->toArray();

            // Apply search filter if any
            if (!empty($search)) {
                $rawRecords = array_filter($rawRecords, function ($item) use ($search) {
                    return stripos($item['invoice'], $search) !== false ||
                        stripos($item['supplier'], $search) !== false ||
                        stripos($item['product_name'], $search) !== false;
                });
            }

            // Sort records
            usort($rawRecords, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc'
                    ? $a[$sortField] <=> $b[$sortField]
                    : $b[$sortField] <=> $a[$sortField];
            });

            $totalRecords = count($rawRecords);

            // Totals
            $totalQty = array_sum(array_column($rawRecords, 'qty'));
            $totalStock = array_sum(array_column($rawRecords, 'in_stock'));
            $totalPrice = array_sum(array_column($rawRecords, 'price'));
            $totalAmount = array_sum(array_column($rawRecords, 'amount'));

            // Pagination
            $paginated = array_slice($rawRecords, $offset, $limit);

            // Subtotals
            $subQty = array_sum(array_column($paginated, 'qty'));
            $subStock = array_sum(array_column($paginated, 'in_stock'));
            $subPrice = array_sum(array_column($paginated, 'price'));
            $subAmount = array_sum(array_column($paginated, 'amount'));

            $subTotalRow = [
                'invoice' => '',
                'oa' => '',
                'date' => '',
                'supplier' => 'SubTotal - ',
                'product_name' => '',
                'qty' => $subQty,
                'in_stock' => $subStock,
                'price' => number_format($subPrice, 2, '.', ''),
                'amount' => number_format($subAmount, 2, '.', ''),
                'place' => '',
            ];

            $totalRow = [
                'invoice' => '',
                'oa' => '',
                'date' => '',
                'supplier' => 'Total -',
                'product_name' => '',
                'qty' => $totalQty,
                'in_stock' => $totalStock,
                'price' => number_format($totalPrice, 2, '.', ''),
                'amount' => number_format($totalAmount, 2, '.', ''),
                'place' => '',
            ];

            $paginated[] = $subTotalRow;
            $paginated[] = $totalRow;

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
                'message' => 'Error fetching purchases: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0
            ], 500);
        }
    }

}
