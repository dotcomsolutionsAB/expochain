<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Models\ProductsModel;
use App\Models\GroupModel;
use App\Models\CategoryModel;
use App\Models\SubCategoryModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderProductsModel;
use App\Models\PurchaseReturnProductsModel;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\AssemblyOperationProductsModel;
use App\Models\StockTransferProductsModel;
use App\Models\GodownModel;
use App\Models\QuotationsModel;
use App\Models\FinancialYearModel;
use App\Models\ClientsModel;
use App\Models\SuppliersModel;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use App\Exports\DashboardStockExport;
use App\Models\OpeningStockModel;
use DB;
use Auth;
use Illuminate\Support\Facades\Schema;
use App\Exports\ClientWiseYearlySalesSummaryExport;

class HelperController extends Controller
{

    // dashboard
    public function dashboard(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // ---- Inputs ----
            $limit          = (int) $request->input('limit', 50);
            $offset         = (int) $request->input('offset', 0);
            $filterGroup    = $request->input('group');
            $filterCategory = $request->input('category');
            $filterSubCat   = $request->input('sub_category');
            $filterAlias    = $request->input('alias'); // single or CSV
            $search         = $request->input('search');
            $sortBy         = $request->input('sort_by', 'name');   // name, group, category, sub_category, alias
            $sortOrder      = $request->input('sort_order', 'asc'); // asc or desc
            $stockLevelReq  = $request->input('stock_level');       // critical|sufficient|excessive|null

            // Normalize stock_level
            $validLevels = ['critical','sufficient','excessive'];
            $stockLevel  = in_array(strtolower((string) $stockLevelReq), $validLevels, true)
                ? strtolower($stockLevelReq)
                : null;

            // Hex map
            $levelHex = [
                'critical'   => 'FFCDD2',
                'sufficient' => 'B3E5FC',
                'excessive'  => 'C8E6C9',
            ];

            // ---- Base products query (no pagination) ----
            $baseQuery = ProductsModel::with([
                    'groupRelation:id,name',
                    'categoryRelation:id,name',
                    'subCategoryRelation:id,name'
                ])
                ->where('company_id', $companyId);

            // Search
            if (!empty($search)) {
                $baseQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('alias', 'like', "%{$search}%");
                });
            }

            // Filters
            if (!empty($filterGroup)) {
                $groupIds = array_filter(explode(',', $filterGroup));
                $baseQuery->whereIn('group', $groupIds);
            }
            if (!empty($filterCategory)) {
                $catIds = array_filter(explode(',', $filterCategory));
                $baseQuery->whereIn('category', $catIds);
            }
            if (!empty($filterSubCat)) {
                $subCatIds = array_filter(explode(',', $filterSubCat));
                $baseQuery->whereIn('sub_category', $subCatIds);
            }
            if (!empty($filterAlias)) {
                $aliasValues = array_map('trim', array_filter(explode(',', $filterAlias)));
                if (!empty($aliasValues)) {
                    $baseQuery->whereIn('alias', $aliasValues);
                }
            }

            // Sorting
            $sortable = ['name','group','category','sub_category','alias'];
            $sortCol  = in_array($sortBy, $sortable, true) ? $sortBy : 'name';
            $sortDir  = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

            // --- Subquery of filtered product IDs (no pagination yet) ---
            $filteredIdsSub = (clone $baseQuery)->select('id');

            // --- Gather aliases for filtered products ---
            $aliasList = (clone $baseQuery)->distinct()->pluck('alias'); // aliases present after filters

            // --- Total quantity per PRODUCT (for per-row total_quantity) ---
            $totalQtyByProduct = ClosingStockModel::where('company_id', $companyId)
                ->whereIn('product_id', $filteredIdsSub)
                ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            // --- Total quantity per ALIAS (for group-level stock_level) ---
            // join closing_stock -> products to sum by alias
            $aliasQty = DB::table((new ClosingStockModel)->getTable().' as cs')
                ->join((new ProductsModel)->getTable().' as p', 'p.id', '=', 'cs.product_id')
                ->where('p.company_id', $companyId)
                ->when(!empty($aliasList), fn($q) => $q->whereIn('p.alias', $aliasList))
                ->select('p.alias', DB::raw('SUM(cs.quantity) as total_qty'))
                ->groupBy('p.alias')
                ->pluck('total_qty', 'alias'); // alias => total qty across products

            // --- Effective SI thresholds per alias ---
            // Prefer pb_alias=1 thresholds; if none, fallback to MAX(si1), MAX(si2) among that alias
            $pbSi = ProductsModel::where('company_id', $companyId)
                ->when(!empty($aliasList), fn($q) => $q->whereIn('alias', $aliasList))
                ->where('pb_alias', 1)
                ->select('alias','si1','si2')
                ->get()
                ->keyBy('alias'); // alias => model (si1, si2)

            $maxSi = ProductsModel::where('company_id', $companyId)
                ->when(!empty($aliasList), fn($q) => $q->whereIn('alias', $aliasList))
                ->select('alias', DB::raw('MAX(si1) as max_si1'), DB::raw('MAX(si2) as max_si2'))
                ->groupBy('alias')
                ->get()
                ->keyBy('alias'); // alias => { max_si1, max_si2 }

            $effectiveSiByAlias = [];
            foreach ($aliasList as $alias) {
                if (isset($pbSi[$alias])) {
                    $effectiveSiByAlias[$alias] = [
                        'si1' => (float) $pbSi[$alias]->si1,
                        'si2' => (float) $pbSi[$alias]->si2,
                    ];
                } elseif (isset($maxSi[$alias])) {
                    $effectiveSiByAlias[$alias] = [
                        'si1' => (float) $maxSi[$alias]->max_si1,
                        'si2' => (float) $maxSi[$alias]->max_si2,
                    ];
                } else {
                    $effectiveSiByAlias[$alias] = ['si1' => 0.0, 'si2' => 0.0];
                }
            }

            // --- Alias-level stock_level ---
            $levelByAlias = [];
            foreach ($aliasList as $alias) {
                $qty = (float) ($aliasQty[$alias] ?? 0);
                $esi1 = (float) ($effectiveSiByAlias[$alias]['si1'] ?? 0);
                $esi2 = (float) ($effectiveSiByAlias[$alias]['si2'] ?? 0);

                if ($qty < $esi1) {
                    $levelByAlias[$alias] = 'critical';
                } elseif ($qty <= $esi2) {
                    $levelByAlias[$alias] = 'sufficient';
                } else {
                    $levelByAlias[$alias] = 'excessive';
                }
            }

            // --- Apply stock_level filter at alias level (if requested) ---
            if ($stockLevel !== null) {
                $aliasesMatchingLevel = array_keys(array_filter($levelByAlias, fn ($lvl) => $lvl === $stockLevel));
                // Ensure we don't break when empty
                $baseQuery->whereIn('alias', !empty($aliasesMatchingLevel) ? $aliasesMatchingLevel : ['__none__']);
            }

            // ----- Counts after all filters -----
            $countQuery    = (clone $baseQuery);
            $totalProducts = $countQuery->count();

            // ----- Pagination query (include si1/si2 for per-row display) -----
            $pageQuery = (clone $baseQuery)
                ->select('id','name','alias','group','category','sub_category','unit','si1','si2')
                ->orderBy($sortCol, $sortDir)
                ->offset($offset)
                ->limit($limit);

            $products = $pageQuery->get();

            // Godowns (for stock_by_godown)
            $godowns = GodownModel::where('company_id', $companyId)->select('id','name')->get();

            // Product IDs on this page
            $pageProductIds = $products->pluck('id');

            // Closing stock grouped (for stock_by_godown on page)
            $closingStockPage = ClosingStockModel::where('company_id', $companyId)
                ->whereIn('product_id', $pageProductIds)
                ->get()
                ->groupBy('product_id');

            // Precompute sum(value) per product for the page
            $stockValueByProduct = ClosingStockModel::where('company_id', $companyId)
                ->whereIn('product_id', $pageProductIds)
                ->select('product_id', DB::raw('SUM(value) as total_value'))
                ->groupBy('product_id')
                ->pluck('total_value', 'product_id');

            // Pending purchase orders
            $pendingPurchase = PurchaseOrderModel::where('company_id', $companyId)
                ->where('status', 'pending')
                ->with('products:id')
                ->get()
                ->flatMap(fn ($order) => $order->products->pluck('id'))
                ->countBy();

            // Pending sales orders
            $pendingSales = SalesOrderModel::where('company_id', $companyId)
                ->where('status', 'pending')
                ->with('products:id')
                ->get()
                ->flatMap(fn ($order) => $order->products->pluck('id'))
                ->countBy();

            // Transform page rows (alias-level stock classification)
            $productsTransformed = $products->map(function ($product) use (
                $closingStockPage, $godowns, $pendingPurchase, $pendingSales,
                $stockValueByProduct, $levelHex, $totalQtyByProduct, $aliasQty, $effectiveSiByAlias, $levelByAlias
            ) {
                $stockData = [];
                $productTotalQty = 0;

                $rows = $closingStockPage->get($product->id, collect())->keyBy('godown_id');

                foreach ($godowns as $godown) {
                    $qty = (float) ($rows->get($godown->id)->quantity ?? 0);
                    $stockData[] = [
                        'godown_id'   => $godown->id,
                        'godown_name' => $godown->name,
                        'quantity'    => $qty,
                    ];
                    $productTotalQty += $qty;
                }

                // Alias-level info
                $alias = $product->alias;
                $aliasTotalQty = (float) ($aliasQty[$alias] ?? 0.0);
                $esi1 = (float) ($effectiveSiByAlias[$alias]['si1'] ?? 0.0);
                $esi2 = (float) ($effectiveSiByAlias[$alias]['si2'] ?? 0.0);
                $level = $levelByAlias[$alias] ?? 'sufficient';

                return [
                    'id'                  => $product->id,
                    'name'                => $product->name,
                    'alias'               => $alias,
                    'group'               => optional($product->groupRelation)->name,
                    'category'            => optional($product->categoryRelation)->name,
                    'sub_category'        => optional($product->subCategoryRelation)->name,
                    'unit'                => $product->unit,
                    'si1'                 => (float) ($product->si1 ?? 0), // product's own
                    'si2'                 => (float) ($product->si2 ?? 0), // product's own
                    'effective_si1'       => $esi1,                        // alias-level used for classification
                    'effective_si2'       => $esi2,                        // alias-level used for classification
                    'stock_level'         => $level,                       // alias-level
                    'stock_level_hex'     => $levelHex[$level] ?? null,
                    'stock_by_godown'     => $stockData,
                    'total_quantity'      => $productTotalQty,             // product-level total
                    'alias_total_quantity'=> $aliasTotalQty,               // alias-level total
                    'stock_value'         => (float) ($stockValueByProduct[$product->id] ?? 0.0),
                    'pending_po'          => (int) ($pendingPurchase[$product->id] ?? 0),
                    'pending_so'          => (int) ($pendingSales[$product->id] ?? 0),
                ];
            });

            // ---- Grand total stock value across ALL filtered products (respect stock_level alias filter if present) ----
            $filteredIds = (clone $baseQuery)->pluck('id');
            $totalStockValue = $filteredIds->isNotEmpty()
                ? (float) ClosingStockModel::where('company_id', $companyId)
                    ->whereIn('product_id', $filteredIds)
                    ->sum('value')
                : 0.0;

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Fetched successfully',
                'data'    => [
                    'count'             => $products->count(),
                    'total_records'     => $totalProducts,
                    'total_stock_value' => round($totalStockValue),
                    'limit'             => $limit,
                    'offset'            => $offset,
                    'stock_level'       => $stockLevel ?? null, // echo applied level (alias-based)
                    'alias_filter'      => $filterAlias ?? null,
                    'records'           => $productsTransformed,
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

    public function exportDashboardExcel(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id ?? null;
            if (!$companyId) {
                return response()->json([
                    'code'    => 401,
                    'success' => false,
                    'message' => 'Unauthorized.',
                    'data'    => []
                ], 401);
            }

            // Ensure export directory exists on public disk
            $directory = 'exports';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            // File name & path
            $fileName = 'Stock_Dashboard_' . now()->format('Ymd_His') . '.xlsx';
            $relativePath = $directory . '/' . $fileName; // storage/app/public/exports/...
            
            // Save to disk (public)
            Excel::store(new DashboardStockExport($request->all()), $relativePath, 'public');

            // Public URL (requires `php artisan storage:link` done once)
            $publicUrl = Storage::disk('public')->url($relativePath);  // e.g. /storage/exports/Stock_Dashboard_20251027_1120.xlsx
            $absoluteUrl = url($publicUrl);                            // full URL

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Export generated successfully.',
                'data'    => [
                    'file_name'    => $fileName,
                    'file_url' => $absoluteUrl
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

    public function setProductAsPbAlias($productId)
    {
        try {
            $auth = Auth::user();
            if (!$auth) {
                return response()->json([
                    'code' => 401,
                    'success' => false,
                    'message' => 'Unauthorized.',
                    'data' => []
                ], 401);
            }

            $product = ProductsModel::where('company_id', $auth->company_id)
                ->find($productId);

            if (!$product) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Product not found.',
                    'data' => []
                ], 404);
            }

            // Step 1: Reset pb_alias = 0 for all products in the same alias group
            ProductsModel::where('company_id', $auth->company_id)
                ->where('alias', $product->alias)
                ->update(['pb_alias' => 0]);

            // Step 2: Set pb_alias = 1 for the selected product
            $product->pb_alias = 1;
            $product->save();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => "Product '{$product->name}' has been set as PB Alias for alias group '{$product->alias}'.",
                'data' => [
                    'product_id' => $product->id,
                    'alias_group' => $product->alias,
                    'pb_alias' => $product->pb_alias
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong.',
                'data' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    public function getSummary()
    {
        $companyId = Auth::user()->company_id;

        // Total count
        $totalCount = PurchaseInvoiceModel::where('company_id', $companyId)->count();

        // Date-wise total sum grouped by purchase_invoice_date
        $dateWiseTotal = PurchaseInvoiceModel::where('company_id', $companyId)
            ->selectRaw('DATE(purchase_invoice_date) as date, SUM(total) as total_sum')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Fetched successfully",
            'total_count' => $totalCount,
            'date_wise_total' => $dateWiseTotal
        ]);
    }

    public function fyWisePurchaseTotals(Request $request)
    {
        try {
            $auth = auth()->user();
            if (!$auth) {
                return response()->json([
                    'code' => 401,
                    'success' => false,
                    'message' => 'Unauthorized.',
                    'data' => []
                ], 401);
            }

            // Validate dates
            $validated = $request->validate([
                'start_date' => 'required|date_format:Y-m-d',
                'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
            ]);

            $companyId = $auth->company_id;
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate   = Carbon::parse($validated['end_date'])->endOfDay();

            // Masters ordered by order_by
            $groups = GroupModel::where('company_id', $companyId)
                ->orderBy('order_by', 'asc')
                ->get(['id','name','order_by'])
                ->keyBy('id');

            $categories = CategoryModel::where('company_id', $companyId)
                ->orderBy('order_by', 'asc')
                ->get(['id','name','order_by'])
                ->keyBy('id');

            $subCategories = SubCategoryModel::where('company_id', $companyId)
                ->orderBy('order_by', 'asc')
                ->get(['id','name','order_by'])
                ->keyBy('id');

            // Products
            $products = ProductsModel::where('company_id', $companyId)
                ->select('id','group','category','sub_category')
                ->get()
                ->keyBy('id');

            $productIds = $products->keys();

            // Stock qty by product
            $stockQtyByProduct = ClosingStockModel::where('company_id', $companyId)
                ->whereIn('product_id', $productIds)
                ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('product_id')
                ->pluck('total_qty', 'product_id');

            // Invoices data
            $purchaseData = PurchaseInvoiceProductsModel::with('purchaseInvoice')
                ->whereIn('product_id', $productIds)
                ->whereHas('purchaseInvoice', function ($q) use ($companyId, $startDate, $endDate) {
                    $q->where('company_id', $companyId)
                    ->whereBetween('purchase_invoice_date', [$startDate, $endDate]);
                })->get();

            $salesData = SalesInvoiceProductsModel::with('salesInvoice')
                ->whereIn('product_id', $productIds)
                ->whereHas('salesInvoice', function ($q) use ($companyId, $startDate, $endDate) {
                    $q->where('company_id', $companyId)
                    ->whereBetween('sales_invoice_date', [$startDate, $endDate]);
                })->get();

            // Result container
            $result = [];

            // Helper: normalize ID (treat null/empty/"0" as 0 int)
            $norm = fn($v) => (is_numeric($v) && (int)$v > 0) ? (int)$v : 0;

            // Helper: ensure buckets exist
            $ensureBucket = function ($groupId, $categoryId, $subCategoryId) use (&$result, $groups, $categories, $subCategories, $norm) {
                $gid = $norm($groupId);
                $cid = $norm($categoryId);
                $sid = $norm($subCategoryId);

                if (!isset($result[$gid])) {
                    $result[$gid] = [
                        'group_id'       => $gid,
                        'group_name'     => $groups[$gid]->name ?? 'Unknown',
                        'total_stock'    => 0,
                        'total_sales'    => 0,
                        'total_purchase' => 0,
                        'total_profit'   => 0,
                        'categories'     => []
                    ];
                }

                if (!isset($result[$gid]['categories'][$cid])) {
                    $result[$gid]['categories'][$cid] = [
                        'category_id'    => $cid,
                        'category_name'  => $categories[$cid]->name ?? 'Unknown',
                        'total_stock'    => 0,
                        'total_sales'    => 0,
                        'total_purchase' => 0,
                        'total_profit'   => 0,
                        'sub_categories' => []
                    ];
                }

                if (!isset($result[$gid]['categories'][$cid]['sub_categories'][$sid])) {
                    $result[$gid]['categories'][$cid]['sub_categories'][$sid] = [
                        'sub_category_id'   => $sid,
                        'sub_category_name' => $subCategories[$sid]->name ?? 'Unknown',
                        'total_stock'       => 0,
                        'total_sales'       => 0,
                        'total_purchase'    => 0,
                        'total_profit'      => 0
                    ];
                }
            };

            // Initialize ordered buckets based on existing masters & products
            foreach ($groups as $groupId => $group) {
                $catIds = $products->where('group', $groupId)->pluck('category')->map($norm)->unique()->filter();
                $catsOrdered = $categories->only($catIds->toArray())->sortBy('order_by');

                if ($catsOrdered->isEmpty()) {
                    $ensureBucket($groupId, 0, 0);
                    continue;
                }

                foreach ($catsOrdered as $categoryId => $category) {
                    $subIds = $products->where('group', $groupId)
                                    ->where('category', $categoryId)
                                    ->pluck('sub_category')->map($norm)->unique()->filter();
                    $subsOrdered = $subCategories->only($subIds->toArray())->sortBy('order_by');

                    if ($subsOrdered->isEmpty()) {
                        $ensureBucket($groupId, $categoryId, 0);
                        continue;
                    }

                    foreach ($subsOrdered as $subId => $sub) {
                        $ensureBucket($groupId, $categoryId, $subId);
                    }
                }
            }

            // PURCHASE totals
            foreach ($purchaseData as $item) {
                $p = $products[$item->product_id] ?? null;
                if (!$p) continue;

                $gid = $norm($p->group); $cid = $norm($p->category); $sid = $norm($p->sub_category);
                $ensureBucket($gid, $cid, $sid);

                $amount = (float) ($item->amount ?? 0);
                $result[$gid]['total_purchase'] += $amount;
                $result[$gid]['categories'][$cid]['total_purchase'] += $amount;
                $result[$gid]['categories'][$cid]['sub_categories'][$sid]['total_purchase'] += $amount;
            }

            // SALES & PROFIT totals
            foreach ($salesData as $item) {
                $p = $products[$item->product_id] ?? null;
                if (!$p) continue;

                $gid = $norm($p->group); $cid = $norm($p->category); $sid = $norm($p->sub_category);
                $ensureBucket($gid, $cid, $sid);

                $amount = (float) ($item->amount ?? 0);
                $profit = (float) ($item->profit ?? 0);

                $result[$gid]['total_sales']   += $amount;
                $result[$gid]['total_profit']  += $profit;

                $result[$gid]['categories'][$cid]['total_sales']  += $amount;
                $result[$gid]['categories'][$cid]['total_profit'] += $profit;

                $result[$gid]['categories'][$cid]['sub_categories'][$sid]['total_sales']  += $amount;
                $result[$gid]['categories'][$cid]['sub_categories'][$sid]['total_profit'] += $profit;
            }

            // STOCK totals (qty)
            foreach ($products as $productId => $p) {
                $gid = $norm($p->group); $cid = $norm($p->category); $sid = $norm($p->sub_category);
                $ensureBucket($gid, $cid, $sid);

                $qty = (float) ($stockQtyByProduct[$productId] ?? 0);
                $result[$gid]['total_stock'] += $qty;
                $result[$gid]['categories'][$cid]['total_stock'] += $qty;
                $result[$gid]['categories'][$cid]['sub_categories'][$sid]['total_stock'] += $qty;
            }

            // ---- SAFE ORDERING (prevents "Undefined array key \"\"") ----
            $safeOrderIdx = function($map, $id, $fallbackName = 'Unknown') {
                // If ID missing/zero or master not found, push to bottom; second key for stable tiebreaker
                $order = (is_int($id) && $id > 0 && isset($map[$id]) && isset($map[$id]->order_by))
                    ? (int)$map[$id]->order_by
                    : PHP_INT_MAX;
                $name  = (is_int($id) && $id > 0 && isset($map[$id]) && isset($map[$id]->name))
                    ? $map[$id]->name
                    : $fallbackName;
                return [$order, $name];
            };

            $ordered = [];
            foreach ($groups->sortBy('order_by') as $gid => $g) {
                if (!isset($result[$gid])) continue;
                $ordered[$gid] = $result[$gid];

                // sort categories; handle missing/empty IDs safely
                $ordered[$gid]['categories'] = collect($ordered[$gid]['categories'])
                    ->sortBy(function ($cat, $cid) use ($categories, $safeOrderIdx) {
                        $cid = (is_numeric($cid) ? (int)$cid : 0);
                        return $safeOrderIdx($categories, $cid, $cat['category_name'] ?? 'Unknown');
                    })
                    ->all();

                // sort subcategories under each category; safe
                foreach ($ordered[$gid]['categories'] as $cid => $cat) {
                    $ordered[$gid]['categories'][$cid]['sub_categories'] = collect($cat['sub_categories'])
                        ->sortBy(function ($sub, $sid) use ($subCategories, $safeOrderIdx) {
                            $sid = (is_numeric($sid) ? (int)$sid : 0);
                            return $safeOrderIdx($subCategories, $sid, $sub['sub_category_name'] ?? 'Unknown');
                        })
                        ->all();
                }
            }

            // Round & reindex
            $data = array_values(array_map(function ($group) {
                $group['categories'] = array_values(array_map(function ($cat) {
                    $cat['sub_categories'] = array_values(array_map(function ($sub) {
                        foreach (['total_stock','total_sales','total_purchase','total_profit'] as $k) {
                            $sub[$k] = round($sub[$k]);
                        }
                        return $sub;
                    }, $cat['sub_categories']));

                    foreach (['total_stock','total_sales','total_purchase','total_profit'] as $k) {
                        $cat[$k] = round($cat[$k]);
                    }
                    return $cat;
                }, $group['categories']));

                foreach (['total_stock','total_sales','total_purchase','total_profit'] as $k) {
                    $group[$k] = round($group[$k]);
                }
                return $group;
            }, $ordered));

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'FY-wise purchase totals fetched successfully.',
                'data'    => [
                    'start_date' => $startDate->toDateString(),
                    'end_date'   => $endDate->toDateString(),
                    'groups'     => $data
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => $ve->errors(),
                'data'    => []
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

    //purchase vs sales barchart
    public function getMonthlyPurchaseSalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Parse start and end dates
            $startDate = Carbon::parse($request->start_date)->startOfMonth();
            $endDate = Carbon::parse($request->end_date)->endOfMonth();

            $months = [];
            $purchaseTotals = [];
            $salesTotals = [];
            $purchaseInvoiceCounts = [];
            $salesInvoiceCounts = [];

            $current = $startDate->copy();

            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();
                $monthName = $current->format('F Y');

                // // Purchase Invoice IDs for this month
                // $purchaseInvoiceIds = PurchaseInvoiceModel::where('company_id', $companyId)
                //     ->whereBetween('purchase_invoice_date', [$monthStart, $monthEnd])
                //     ->pluck('id');

                // // Sales Invoice IDs for this month
                // $salesInvoiceIds = SalesInvoiceModel::where('company_id', $companyId)
                //     ->whereBetween('sales_invoice_date', [$monthStart, $monthEnd])
                //     ->pluck('id');

                // // Sum of amounts for purchase and sales
                // $purchaseTotal = PurchaseInvoiceProductsModel::whereIn('purchase_invoice_id', $purchaseInvoiceIds)->sum('amount');
                // $salesTotal = SalesInvoiceProductsModel::whereIn('sales_invoice_id', $salesInvoiceIds)->sum('amount');

                // // Invoice counts
                // $purchaseCount = $purchaseInvoiceIds->count();
                // $salesCount = $salesInvoiceIds->count();

                // // Append results
                // $months[] = $monthName;
                // $purchaseTotals[] = round($purchaseTotal, 2);
                // $salesTotals[] = round($salesTotal, 2);
                // $purchaseInvoiceCounts[] = $purchaseCount;
                // $salesInvoiceCounts[] = $salesCount;

                // // Move to next month
                // $current->addMonth();

                // Purchase Aggregates
                $purchaseStats = DB::table('t_purchase_invoice as pi')
                ->join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
                ->where('pi.company_id', $companyId)
                ->whereBetween('pi.purchase_invoice_date', [$monthStart, $monthEnd])
                ->selectRaw('SUM(pip.amount) as total, COUNT(DISTINCT pi.id) as invoice_count')
                ->first();

                // Sales Aggregates
                $salesStats = DB::table('t_sales_invoice as si')
                    ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                    ->where('si.company_id', $companyId)
                    ->whereBetween('si.sales_invoice_date', [$monthStart, $monthEnd])
                    ->selectRaw('SUM(sip.amount) as total, COUNT(DISTINCT si.id) as invoice_count')
                    ->first();

                // Populate results
                $months[] = $monthName;
                $purchaseTotals[] = round($purchaseStats->total ?? 0, 2);
                $salesTotals[] = round($salesStats->total ?? 0, 2);
                $purchaseInvoiceCounts[] = $purchaseStats->invoice_count ?? 0;
                $salesInvoiceCounts[] = $salesStats->invoice_count ?? 0;

                $current->addMonth();

            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Purchase vs sales barchart fetched successfully!',
                'data' => [
                    'month' => $months,
                    'purchase_total' => $purchaseTotals,
                    'sales_total' => $salesTotals,
                    'purchase_invoice_count' => $purchaseInvoiceCounts,
                    'sales_invoice_count' => $salesInvoiceCounts,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while processing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // product wise profit
    public function getProductWiseSalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Dates
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::minValue();
            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfDay();

            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);

            // Sorting
            $sortBy  = strtolower($request->input('sort_by', 'amount')); // name | profit | amount
            $sortDir = strtolower($request->input('sort_dir', 'desc'));  // asc | desc
            $sortDir = in_array($sortDir, ['asc','desc']) ? $sortDir : 'desc';

            // 1) Filtered sales invoice IDs
            $salesInvoiceIds = SalesInvoiceModel::where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$startDate, $endDate])
                ->pluck('id');

            // 2) Aggregate totals per product (no pagination yet)
            $allProducts = SalesInvoiceProductsModel::select('product_id', 'product_name')
                ->where('company_id', $companyId)
                ->whereIn('sales_invoice_id', $salesInvoiceIds)
                ->selectRaw('SUM(amount) as total_amount, SUM(profit) as total_profit')
                ->groupBy('product_id', 'product_name')
                ->get();

            // Flatten and round
            $flat = $allProducts->map(function ($p) {
                return [
                    'product_id'   => $p->product_id,
                    'product_name' => $p->product_name ?: 'Unknown',
                    'total_amount' => round((float)$p->total_amount),
                    'total_profit' => round((float)$p->total_profit),
                ];
            })->values()->toArray();

            // 3) Sort
            switch ($sortBy) {
                case 'name':
                    usort($flat, function ($a, $b) use ($sortDir) {
                        $cmp = strcasecmp($a['product_name'], $b['product_name']);
                        return $sortDir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
                case 'profit':
                    usort($flat, function ($a, $b) use ($sortDir) {
                        $cmp = $a['total_profit'] <=> $b['total_profit'];
                        return $sortDir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
                case 'amount':
                default:
                    usort($flat, function ($a, $b) use ($sortDir) {
                        $cmp = $a['total_amount'] <=> $b['total_amount'];
                        return $sortDir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
            }

            // 4) Totals before pagination
            $grandTotalAmount = round(array_sum(array_column($flat, 'total_amount')));
            $grandTotalProfit = round(array_sum(array_column($flat, 'total_profit')));
            $totalRecords     = count($flat);

            // 5) Pagination AFTER sorting
            $pageRows = array_slice($flat, $offset, $limit);
            $count    = count($pageRows);

            // 6) Subtotals for current page
            $subTotalAmount = round(array_sum(array_column($pageRows, 'total_amount')));
            $subTotalProfit = round(array_sum(array_column($pageRows, 'total_profit')));

            // 7) Append Sub-total and Total rows
            $pageRows[] = [
                'product_id'   => '',
                'product_name' => 'Sub-total - ',
                'total_amount' => $subTotalAmount,
                'total_profit' => $subTotalProfit,
            ];
            $pageRows[] = [
                'product_id'   => '',
                'product_name' => 'Total - ',
                'total_amount' => $grandTotalAmount,
                'total_profit' => $grandTotalProfit,
            ];

            // 8) Response
            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'Product-wise sales summary fetched successfully!',
                'data'          => $pageRows,
                'count'         => $count,          // 👈 number of rows in this page (before summary rows)
                'total_records' => $totalRecords,   // total unique products before pagination
                'limit'         => $limit,
                'offset'        => $offset,
                'sorted_by'     => $sortBy,
                'sort_dir'      => $sortDir,
                'period'        => [
                    'from' => $startDate->toDateTimeString(),
                    'to'   => $endDate->toDateTimeString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching product-wise sales summary.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    //client wise profit
    public function getClientWiseSalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Parse dates
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::minValue();
            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfDay();

            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);

            // Sorting parameters
            $sortBy  = strtolower($request->input('sort_by', 'amount')); // name | profit | amount
            $sortDir = strtolower($request->input('sort_dir', 'desc'));  // asc | desc
            $sortDir = in_array($sortDir, ['asc','desc']) ? $sortDir : 'desc';

            // Fetch all invoices within the date range
            $invoices = SalesInvoiceModel::with('products:id,sales_invoice_id,profit,amount', 'client:id,name')
                ->select('id', 'client_id', 'sales_invoice_date')
                ->where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$startDate, $endDate])
                ->get();

            // Aggregate totals by client
            $result = [];
            foreach ($invoices as $invoice) {
                $clientId = $invoice->client_id;
                if (!$clientId) continue;

                $profitSum = $invoice->products->sum('profit');
                $amountSum = $invoice->products->sum('amount');
                $clientName = $invoice->client->name ?? 'Unknown';

                if (!isset($result[$clientId])) {
                    $result[$clientId] = [
                        'client_id'    => $clientId,
                        'client_name'  => $clientName,
                        'total_profit' => 0,
                        'total_amount' => 0,
                    ];
                }

                $result[$clientId]['total_profit'] += $profitSum;
                $result[$clientId]['total_amount'] += $amountSum;
            }

            // Flatten and round
            $flatResult = array_values(array_map(function ($item) {
                return [
                    'client_id'    => $item['client_id'],
                    'client_name'  => $item['client_name'],
                    'total_profit' => round($item['total_profit']),
                    'total_amount' => round($item['total_amount']),
                ];
            }, $result));

            // Sort logic
            switch ($sortBy) {
                case 'name':
                    usort($flatResult, function ($a, $b) use ($sortDir) {
                        $cmp = strcasecmp($a['client_name'], $b['client_name']);
                        return $sortDir === 'asc' ? $cmp : -$cmp;
                    });
                    break;

                case 'profit':
                    usort($flatResult, function ($a, $b) use ($sortDir) {
                        $cmp = $a['total_profit'] <=> $b['total_profit'];
                        return $sortDir === 'asc' ? $cmp : -$cmp;
                    });
                    break;

                case 'amount':
                default:
                    usort($flatResult, function ($a, $b) use ($sortDir) {
                        $cmp = $a['total_amount'] <=> $b['total_amount'];
                        return $sortDir === 'asc' ? $cmp : -$cmp;
                    });
                    break;
            }

            // Grand totals before pagination
            $totalProfit = round(array_sum(array_column($flatResult, 'total_profit')));
            $totalAmount = round(array_sum(array_column($flatResult, 'total_amount')));
            $totalRecords = count($flatResult);

            // Pagination
            $pageRows = array_slice($flatResult, $offset, $limit);
            $count = count($pageRows);

            // Subtotals for current page
            $subTotalProfit = round(array_sum(array_column($pageRows, 'total_profit')));
            $subTotalAmount = round(array_sum(array_column($pageRows, 'total_amount')));

            // Append subtotal and total rows
            $pageRows[] = [
                'client_id'    => '',
                'client_name'  => 'Sub-total - ',
                'total_profit' => $subTotalProfit,
                'total_amount' => $subTotalAmount,
            ];
            $pageRows[] = [
                'client_id'    => '',
                'client_name'  => 'Total - ',
                'total_profit' => $totalProfit,
                'total_amount' => $totalAmount,
            ];

            // Final response
            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'Client wise profit fetched successfully!',
                'data'          => $pageRows,
                'count'         => $count,          // number of data rows on this page
                'total_records' => $totalRecords,   // total unique clients
                'limit'         => $limit,
                'offset'        => $offset,
                'sorted_by'     => $sortBy,
                'sort_dir'      => $sortDir,
                'period'        => [
                    'from' => $startDate->toDateTimeString(),
                    'to'   => $endDate->toDateTimeString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while calculating client-wise sales summary.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getCommissionReport(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Parse date range
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::minValue();
            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfDay();

            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);

            // Sorting
            $sortBy  = strtolower($request->input('sort_by', 'date')); // date | invoice | client | amount | commission
            $sortDir = strtolower($request->input('sort_dir', 'desc'));
            $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

            // Base query
            $query = SalesInvoiceModel::with('client:id,name')
                ->where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$startDate, $endDate])
                ->whereNotNull('commission')
                ->where('commission', '>', 0)
                ->select('id', 'sales_invoice_no', 'sales_invoice_date', 'client_id', 'total', 'commission');

            // Sorting logic
            switch ($sortBy) {
                case 'invoice':
                    $query->orderBy('sales_invoice_no', $sortDir);
                    break;
                case 'client':
                    $query->join('t_clients as c', 'c.id', '=', 't_sales_invoice.client_id')
                        ->orderBy('c.name', $sortDir)
                        ->select('t_sales_invoice.*');
                    break;
                case 'amount':
                    $query->orderBy('total', $sortDir);
                    break;
                case 'commission':
                    $query->orderBy('commission', $sortDir);
                    break;
                case 'date':
                default:
                    $query->orderBy('sales_invoice_date', $sortDir);
                    break;
            }

            // Clone for totals
            $allData = (clone $query)->get();

            // Grand totals (before pagination)
            $grandTotalAmt = round($allData->sum('total'));
            $grandTotalComm = round($allData->sum('commission'));

            // Pagination
            $rows = $query->offset($offset)->limit($limit)->get();
            $count = $rows->count();
            $totalRecords = $allData->count();

            // Subtotals (paginated)
            $subTotalAmt = round($rows->sum('total'));
            $subTotalComm = round($rows->sum('commission'));

            // Transform output
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'id'          => $row->id,
                    'date'        => Carbon::parse($row->sales_invoice_date)->format('d-m-Y'),
                    'invoice_no'  => $row->sales_invoice_no,
                    'client_name' => $row->client->name ?? 'Unknown',
                    'amount'      => round($row->total),
                    'commission'  => round($row->commission),
                ];
            }

            // Add subtotal + total rows
            $data[] = [
                'id'          => '',
                'date'        => '',
                'invoice_no'  => '',
                'client_name' => 'Sub-total - ',
                'amount'      => $subTotalAmt,
                'commission'  => $subTotalComm,
            ];
            $data[] = [
                'id'          => '',
                'date'        => '',
                'invoice_no'  => '',
                'client_name' => 'Total - ',
                'amount'      => $grandTotalAmt,
                'commission'  => $grandTotalComm,
            ];

            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'Commission report fetched successfully!',
                'data'          => $data,
                'count'         => $count,
                'total_records' => $totalRecords,
                'limit'         => $limit,
                'offset'        => $offset,
                'sorted_by'     => $sortBy,
                'sort_dir'      => $sortDir,
                'period'        => [
                    'from' => $startDate->toDateString(),
                    'to'   => $endDate->toDateString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching commission report.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getCashSalesDetails(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Date range
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::minValue();
            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfDay();

            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);

            // Sorting
            $sortBy  = strtolower($request->input('sort_by', 'date')); // date | invoice | client | amount
            $sortDir = strtolower($request->input('sort_dir', 'desc')); // asc | desc
            $sortDir = in_array($sortDir, ['asc','desc']) ? $sortDir : 'desc';

            // Base query: only cash invoices
            $table = (new SalesInvoiceModel)->getTable(); // t_sales_invoice

            $query = SalesInvoiceModel::with('client:id,name')
                ->where("$table.company_id", $companyId)
                ->whereBetween("$table.sales_invoice_date", [$startDate, $endDate])
                ->where("$table.cash", 1)
                ->select("$table.id", "$table.sales_invoice_no", "$table.sales_invoice_date", "$table.client_id", "$table.total");

            // Sorting logic
            switch ($sortBy) {
                case 'invoice':
                    $query->orderBy("$table.sales_invoice_no", $sortDir);
                    break;
                case 'client':
                    // Join clients for sorting by name
                    $query->leftJoin('t_clients as c', "c.id", '=', "$table.client_id")
                        ->orderBy('c.name', $sortDir)
                        ->select("$table.*");
                    break;
                case 'amount':
                    $query->orderBy("$table.total", $sortDir);
                    break;
                case 'date':
                default:
                    $query->orderBy("$table.sales_invoice_date", $sortDir);
                    break;
            }

            // Clone for full totals (before pagination)
            $allData = (clone $query)->get();

            $grandTotalAmount = round($allData->sum('total'), 2);
            $totalRecords     = $allData->count();

            // Apply pagination
            $rows  = $query->offset($offset)->limit($limit)->get();
            $count = $rows->count();

            // Page subtotal
            $subTotalAmount = round($rows->sum('total'), 2);

            // Transform rows
            $data = [];
            foreach ($rows as $row) {
                $clientName = $row->client->name ?? null;
                if (!$clientName || trim($clientName) === '') {
                    $clientName = 'CASH';
                }

                $data[] = [
                    'id'          => $row->id,
                    'date'        => Carbon::parse($row->sales_invoice_date)->format('d-m-Y'),
                    'invoice_no'  => $row->sales_invoice_no,
                    'client_name' => $clientName,
                    'amount'      => round((float)$row->total, 2),
                ];
            }

            // Append Sub-total and Total rows
            $data[] = [
                'id'          => '',
                'date'        => '',
                'invoice_no'  => '',
                'client_name' => 'Sub-total - ',
                'amount'      => $subTotalAmount,
            ];
            $data[] = [
                'id'          => '',
                'date'        => '',
                'invoice_no'  => '',
                'client_name' => 'Total - ',
                'amount'      => $grandTotalAmount,
            ];

            return response()->json([
                'code'          => 200,
                'success'       => true,
                'message'       => 'Cash details fetched successfully!',
                'data'          => $data,
                'count'         => $count,
                'total_records' => $totalRecords,
                'limit'         => $limit,
                'offset'        => $offset,
                'sorted_by'     => $sortBy,
                'sort_dir'      => $sortDir,
                'period'        => [
                    'from' => $startDate->toDateString(),
                    'to'   => $endDate->toDateString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching cash details.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getProductWiseYearlySalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            if ($limit <= 0)  $limit = 10;
            if ($limit > 500) $limit = 500;

            // Sorting
            $sortByRaw = (string) $request->input('sort_by', 'name'); // default name
            $sortDir   = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            // Search by product name
            $searchKey = (string) $request->input('search_key', '');

            // Financial year IDs (comma-separated)
            $providedIds = collect(explode(',', (string)$request->input('years', '')))
                ->filter(fn($id) => is_numeric(trim($id)))
                ->map(fn($id) => (int) trim($id))
                ->unique()
                ->values();

            // Load FYs (ascending by start_date)
            $fysQuery = FinancialYearModel::query()->where('company_id', $companyId);
            if ($providedIds->isNotEmpty()) {
                $fysQuery->whereIn('id', $providedIds);
            } else {
                // Default to latest 4 FYs if none provided
                $fysQuery->orderBy('start_date', 'desc')->limit(4);
            }
            $fys = $fysQuery->get()->sortBy('start_date')->values();

            if ($fys->isEmpty()) {
                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'No financial years found for the given IDs.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0,
                ], 200);
            }

            // Build FY label map (YY-YY) & ranges
            $selectedFyIds   = $fys->pluck('id')->all();
            $fyLabelsOrdered = []; // ["21-22","22-23",...], oldest -> newest
            $fyIdToLabel     = []; // id => "YY-YY"
            $minStart = null; $maxEnd = null;

            foreach ($fys as $fy) {
                $start = $fy->start_date instanceof Carbon ? $fy->start_date : Carbon::parse($fy->start_date);
                $end   = $fy->end_date   instanceof Carbon ? $fy->end_date   : Carbon::parse($fy->end_date);

                $sy = (int)$start->format('y');
                $ey = (int)$end->format('y');
                $label = sprintf('%02d-%02d', $sy, $ey);

                $fyLabelsOrdered[]    = $label;
                $fyIdToLabel[$fy->id] = $label;

                $minStart = $minStart ? $minStart->min($start) : $start;
                $maxEnd   = $maxEnd   ? $maxEnd->max($end)     : $end;
            }

            // Aggregate product totals by FY (date-range join)
            $rows = DB::table('t_sales_invoice as si')
                ->join('t_sales_invoice_products as sip', function ($j) use ($companyId) {
                    $j->on('sip.sales_invoice_id', '=', 'si.id')
                    ->where('sip.company_id', '=', $companyId);
                })
                ->join('t_financial_year as fy', function ($j) {
                    $j->on('si.sales_invoice_date', '>=', 'fy.start_date')
                    ->on('si.sales_invoice_date', '<=', 'fy.end_date');
                })
                ->where('si.company_id', $companyId)
                ->whereIn('fy.id', $selectedFyIds)
                ->whereBetween('si.sales_invoice_date', [$minStart, $maxEnd])
                // optional search by product_name (in the aggregated set)
                ->when($searchKey !== '', function ($q) use ($searchKey) {
                    $q->where('sip.product_name', 'like', '%'.$searchKey.'%');
                })
                ->groupBy('sip.product_id', 'sip.product_name', 'fy.id')
                ->select([
                    'sip.product_id',
                    'sip.product_name',
                    'fy.id as fy_id',
                    DB::raw('ROUND(COALESCE(SUM(sip.amount), 0), 2) as total_amount'),
                    DB::raw('ROUND(COALESCE(SUM(sip.profit), 0), 2) as total_profit'),
                ])
                ->get();

            // Pivot per product -> FY label
            // product_id => ['name'=>..., 'amounts'=>[label=>float], 'profits'=>[label=>float]]
            $byProduct = [];
            foreach ($rows as $r) {
                $pid = (int)$r->product_id;
                $lbl = $fyIdToLabel[$r->fy_id] ?? null;
                if ($lbl === null) continue;

                if (!isset($byProduct[$pid])) {
                    $byProduct[$pid] = [
                        'name'    => $r->product_name ?: 'Unknown',
                        'amounts' => [],
                        'profits' => [],
                    ];
                }
                $byProduct[$pid]['amounts'][$lbl] = (float)$r->total_amount;
                $byProduct[$pid]['profits'][$lbl] = (float)$r->total_profit;
            }

            // ---- Sorting (same patterns as client-wise) ----
            $norm = strtolower(trim($sortByRaw));
            $norm = str_replace(['%20','%2F'], ' ', $norm);
            $norm = preg_replace('/\s+/', ' ', $norm);

            $sortType = 'name';  // 'name' | 'amount' | 'profit' | 'percentage'
            $sortLabel = null;   // FY label "YY-YY" for amount/profit

            if (in_array($norm, ['percentage', 'percentage (amount)', 'percentage_amount', 'pct', 'growth'], true)) {
                $sortType = 'percentage';
            } elseif (preg_match('/^(amount|profit)[\s:_-]*([0-9]{2}-[0-9]{2})$/i', $norm, $m)) {
                $sortType  = strtolower($m[1]);
                $sortLabel = $m[2];
            } elseif (in_array($norm, ['amount','profit'], true)) {
                $sortType  = $norm;
                $sortLabel = end($fyLabelsOrdered); // default to latest FY
            } elseif (in_array($norm, ['name','product','product_name'], true)) {
                $sortType = 'name';
            } else {
                // also support exact headers like "Amount 24-25"
                if (preg_match('/^(amount|profit)\s+([0-9]{2}-[0-9]{2})$/i', $sortByRaw, $m2)) {
                    $sortType  = strtolower($m2[1]);
                    $sortLabel = $m2[2];
                } else {
                    $sortType = 'name';
                }
            }

            $latestLabel = end($fyLabelsOrdered);
            $prevLabel   = count($fyLabelsOrdered) >= 2 ? $fyLabelsOrdered[count($fyLabelsOrdered)-2] : null;

            $list = [];
            foreach ($byProduct as $pid => $data) {
                $keyVal = null;
                if ($sortType === 'name') {
                    $keyVal = strtolower($data['name']);
                } elseif ($sortType === 'amount') {
                    $label  = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['amounts'][$label] ?? 0.0);
                } elseif ($sortType === 'profit') {
                    $label  = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['profits'][$label] ?? 0.0);
                } elseif ($sortType === 'percentage') {
                    if ($prevLabel) {
                        $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                        $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                        if ($prev == 0.0 && $curr > 0.0)       $keyVal = 100.0;
                        elseif ($prev > 0.0 && $curr == 0.0)   $keyVal = -100.0;
                        elseif ($prev > 0.0)                   $keyVal = (($curr - $prev) / $prev) * 100.0;
                        else                                    $keyVal = 0.0;
                    } else {
                        $keyVal = 0.0;
                    }
                }

                $list[] = ['pid' => $pid, 'data' => $data, 'key' => $keyVal];
            }

            // Sort then paginate
            usort($list, function ($a, $b) use ($sortType, $sortDir) {
                if ($a['key'] == $b['key']) {
                    return strcasecmp($a['data']['name'], $b['data']['name']);
                }
                if ($sortDir === 'asc')  return ($a['key'] < $b['key']) ? -1 : 1;
                return ($a['key'] > $b['key']) ? -1 : 1;
            });

            $totalRecords = count($list);
            $pageSlice    = array_slice($list, $offset, $limit);
            $count        = count($pageSlice);

            // Format numbers to max 2 decimals (string)
            $fmt2 = fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');

            // Build table rows
            $table = [];
            $sn = $offset + 1;
            foreach ($pageSlice as $item) {
                $data = $item['data'];
                $row = [
                    'sn'   => $sn++,
                    'name' => $data['name'],
                ];

                foreach ($fyLabelsOrdered as $lbl) {
                    $row["Amount {$lbl}"] = $fmt2($data['amounts'][$lbl] ?? 0.0);
                    $row["Profit {$lbl}"] = $fmt2($data['profits'][$lbl] ?? 0.0);
                }

                // Percentage (Amount): latest vs previous
                $pct = '0 %';
                if ($prevLabel) {
                    $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                    $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                    if ($prev == 0.0 && $curr > 0.0)       $pct = '100 %';
                    elseif ($prev > 0.0 && $curr == 0.0)   $pct = '-100 %';
                    elseif ($prev > 0.0) {
                        $growth = (($curr - $prev) / $prev) * 100.0;
                        $pct = $fmt2($growth) . ' %';
                    }
                }
                $row['Percentage (Amount)'] = $pct;

                $table[] = $row;
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Product-wise yearly sales summary fetched successfully!',
                'data'    => [
                    'years' => $fyLabelsOrdered,   // ["21-22","22-23","23-24","24-25"]
                    'rows'  => $table,
                    'sort'  => [
                        'sort_by'  => $sortByRaw,
                        'sort_dir' => $sortDir,
                    ],
                ],
                'count'         => $count,
                'total_records' => $totalRecords,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching product-wise yearly summary.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function exportProductWiseYearlySalesSummaryExcel(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // ---- Inputs (same options as JSON endpoint) ----
            $searchKey = (string) $request->input('search_key', '');
            $sortByRaw = (string) $request->input('sort_by', 'name');
            $sortDir   = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            // Years (comma-separated IDs); default to latest 4 FYs
            $providedIds = collect(explode(',', (string)$request->input('years', '')))
                ->filter(fn($id) => is_numeric(trim($id)))
                ->map(fn($id) => (int) trim($id))
                ->unique()
                ->values();

            $fysQuery = FinancialYearModel::query()->where('company_id', $companyId);
            if ($providedIds->isNotEmpty()) {
                $fysQuery->whereIn('id', $providedIds);
            } else {
                $fysQuery->orderBy('start_date', 'desc')->limit(4);
            }
            $fys = $fysQuery->get()->sortBy('start_date')->values();

            if ($fys->isEmpty()) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No financial years found for the given IDs.',
                ], 404);
            }

            // ---- FY labels (YY-YY), ranges ----
            $selectedFyIds   = $fys->pluck('id')->all();
            $fyLabelsOrdered = [];
            $fyIdToLabel     = [];
            $minStart = null; $maxEnd = null;

            foreach ($fys as $fy) {
                $start = $fy->start_date instanceof Carbon ? $fy->start_date : Carbon::parse($fy->start_date);
                $end   = $fy->end_date   instanceof Carbon ? $fy->end_date   : Carbon::parse($fy->end_date);

                $sy = (int)$start->format('y');
                $ey = (int)$end->format('y');
                $label = sprintf('%02d-%02d', $sy, $ey);

                $fyLabelsOrdered[]    = $label;
                $fyIdToLabel[$fy->id] = $label;

                $minStart = $minStart ? $minStart->min($start) : $start;
                $maxEnd   = $maxEnd   ? $maxEnd->max($end)     : $end;
            }

            // ---- Aggregate product totals by FY (date-range join) ----
            $rows = DB::table('t_sales_invoice as si')
                ->join('t_sales_invoice_products as sip', function ($j) use ($companyId) {
                    $j->on('sip.sales_invoice_id', '=', 'si.id')
                    ->where('sip.company_id', '=', $companyId);
                })
                ->join('t_financial_year as fy', function ($j) {
                    $j->on('si.sales_invoice_date', '>=', 'fy.start_date')
                    ->on('si.sales_invoice_date', '<=', 'fy.end_date');
                })
                ->where('si.company_id', $companyId)
                ->whereIn('fy.id', $selectedFyIds)
                ->whereBetween('si.sales_invoice_date', [$minStart, $maxEnd])
                ->when($searchKey !== '', function ($q) use ($searchKey) {
                    $q->where('sip.product_name', 'like', '%'.$searchKey.'%');
                })
                ->groupBy('sip.product_id', 'sip.product_name', 'fy.id')
                ->select([
                    'sip.product_id',
                    'sip.product_name',
                    'fy.id as fy_id',
                    DB::raw('ROUND(COALESCE(SUM(sip.amount), 0), 2) as total_amount'),
                    DB::raw('ROUND(COALESCE(SUM(sip.profit), 0), 2) as total_profit'),
                ])
                ->get();

            // ---- Pivot per product ----
            // product_id => ['name'=>..., 'amounts'=>[label=>float], 'profits'=>[label=>float]]
            $byProduct = [];
            foreach ($rows as $r) {
                $pid = (int)$r->product_id;
                $lbl = $fyIdToLabel[$r->fy_id] ?? null;
                if ($lbl === null) continue;

                if (!isset($byProduct[$pid])) {
                    $byProduct[$pid] = [
                        'name'    => $r->product_name ?: 'Unknown',
                        'amounts' => [],
                        'profits' => [],
                    ];
                }
                $byProduct[$pid]['amounts'][$lbl] = (float)$r->total_amount;
                $byProduct[$pid]['profits'][$lbl] = (float)$r->total_profit;
            }

            // ---- Sorting (name / amount FY / profit FY / percentage) ----
            $norm = strtolower(trim($sortByRaw));
            $norm = str_replace(['%20','%2F'], ' ', $norm);
            $norm = preg_replace('/\s+/', ' ', $norm);

            $sortType = 'name';
            $sortLabel = null;
            if (in_array($norm, ['percentage','percentage (amount)','percentage_amount','pct','growth'], true)) {
                $sortType = 'percentage';
            } elseif (preg_match('/^(amount|profit)[\s:_-]*([0-9]{2}-[0-9]{2})$/i', $norm, $m)) {
                $sortType = strtolower($m[1]);  // amount|profit
                $sortLabel = $m[2];              // YY-YY
            } elseif (in_array($norm, ['amount','profit'], true)) {
                $sortType = $norm;
                $sortLabel = end($fyLabelsOrdered);
            } elseif (in_array($norm, ['name','product','product_name'], true)) {
                $sortType = 'name';
            } else {
                if (preg_match('/^(amount|profit)\s+([0-9]{2}-[0-9]{2})$/i', $sortByRaw, $m2)) {
                    $sortType  = strtolower($m2[1]);
                    $sortLabel = $m2[2];
                } else {
                    $sortType  = 'name';
                }
            }

            $latestLabel = end($fyLabelsOrdered);
            $prevLabel   = count($fyLabelsOrdered) >= 2 ? $fyLabelsOrdered[count($fyLabelsOrdered)-2] : null;

            $list = [];
            foreach ($byProduct as $pid => $data) {
                $keyVal = null;
                if ($sortType === 'name') {
                    $keyVal = strtolower($data['name']);
                } elseif ($sortType === 'amount') {
                    $label  = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['amounts'][$label] ?? 0.0);
                } elseif ($sortType === 'profit') {
                    $label  = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['profits'][$label] ?? 0.0);
                } elseif ($sortType === 'percentage') {
                    if ($prevLabel) {
                        $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                        $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                        if ($prev == 0.0 && $curr > 0.0)       $keyVal = 100.0;
                        elseif ($prev > 0.0 && $curr == 0.0)   $keyVal = -100.0;
                        elseif ($prev > 0.0)                   $keyVal = (($curr - $prev) / $prev) * 100.0;
                        else                                    $keyVal = 0.0;
                    } else {
                        $keyVal = 0.0;
                    }
                }

                $list[] = ['data' => $data, 'key' => $keyVal];
            }

            usort($list, function ($a, $b) use ($sortDir) {
                if ($a['key'] == $b['key']) return strcasecmp($a['data']['name'], $b['data']['name']);
                return $sortDir === 'asc'
                    ? (($a['key'] < $b['key']) ? -1 : 1)
                    : (($a['key'] > $b['key']) ? -1 : 1);
            });

            // ---- Build Excel headings + rows (percentage number, no “%”) ----
            $headings = ['SN', 'Name'];
            foreach ($fyLabelsOrdered as $lbl) {
                $headings[] = "Amount {$lbl}";
                $headings[] = "Profit {$lbl}";
            }
            $headings[] = 'Percentage (Amount)';

            $fmt2 = fn($v) => round((float)$v, 2);

            $excelRows = [];
            $sn = 1;
            foreach ($list as $item) {
                $data = $item['data'];
                $row = [$sn++, $data['name']];
                foreach ($fyLabelsOrdered as $lbl) {
                    $row[] = $fmt2($data['amounts'][$lbl] ?? 0.0);
                    $row[] = $fmt2($data['profits'][$lbl] ?? 0.0);
                }
                // percentage latest vs previous
                $pct = 0.0;
                if ($prevLabel) {
                    $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                    $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                    if ($prev == 0.0 && $curr > 0.0)       $pct = 100.0;
                    elseif ($prev > 0.0 && $curr == 0.0)   $pct = -100.0;
                    elseif ($prev > 0.0)                   $pct = (($curr - $prev) / $prev) * 100.0;
                }
                $row[] = $fmt2($pct); // numeric, no symbol
                $excelRows[] = $row;
            }

            // ---- Save to storage/app/public/uploads & return URL ----
            $disk = 'public';
            $directory = 'uploads';
            if (!Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->makeDirectory($directory);
            }

            $fileName = 'ProductWise_Yearly_Summary_' . now()->format('Ymd_His') . '.xlsx';
            $relativePath = $directory . '/' . $fileName;

            // Column styling indices
            $percentageColIndex = 2 + (count($fyLabelsOrdered) * 2) + 1; // 1-based
            $numericCols = [];
            for ($i = 3; $i < $percentageColIndex; $i++) $numericCols[] = $i; // amount/profit cols
            $numericCols[] = $percentageColIndex; // percentage col

            $stored = Excel::store(
                new ClientWiseYearlySalesSummaryExport($excelRows, $headings, $percentageColIndex, $numericCols),
                $relativePath,
                $disk
            );

            if (!$stored) {
                return response()->json([
                    'code' => 500,
                    'success' => false,
                    'message' => 'Excel could not be saved.',
                ], 500);
            }

            $publicUrl = Storage::disk($disk)->url($relativePath);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Excel exported successfully!',
                'data' => [
                    'url'       => asset($publicUrl),
                    'file_name' => $fileName,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while exporting product-wise Excel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getClientWiseYearlySalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Pagination
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            if ($limit <= 0)  $limit = 10;
            if ($limit > 500) $limit = 500;

            // Sorting
            $sortByRaw = (string) $request->input('sort_by', 'name');
            $sortDir   = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            // Selected FY IDs (comma-separated)
            $providedIds = collect(explode(',', (string)$request->input('years', '')))
                ->filter(fn($id) => is_numeric(trim($id)))
                ->map(fn($id) => (int) trim($id))
                ->unique()
                ->values();

            // Load FYs for this company; default to latest 4 if none provided
            $fysQuery = FinancialYearModel::query()->where('company_id', $companyId);
            if ($providedIds->isNotEmpty()) {
                $fysQuery->whereIn('id', $providedIds);
            } else {
                $fysQuery->orderBy('start_date', 'desc')->limit(4);
            }
            $fys = $fysQuery->get()->sortBy('start_date')->values();

            if ($fys->isEmpty()) {
                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'No financial years found for the given IDs.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0,
                ], 200);
            }

            // Must have at least 2 FYs for the "last 2 years" filter
            if ($fys->count() < 2) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'At least two financial years are required to apply the last-2-years filter.',
                ], 422);
            }

            // Build FY label map
            $selectedFyIds   = $fys->pluck('id')->all();
            $fyLabelsOrdered = []; // ["21-22","22-23",...], oldest -> newest
            $fyIdToLabel     = []; // id => "YY-YY"
            $minStart = null; $maxEnd = null;

            foreach ($fys as $fy) {
                $start = $fy->start_date instanceof Carbon ? $fy->start_date : Carbon::parse($fy->start_date);
                $end   = $fy->end_date   instanceof Carbon ? $fy->end_date   : Carbon::parse($fy->end_date);
                $sy = (int)$start->format('y');
                $ey = (int)$end->format('y');
                $label = sprintf('%02d-%02d', $sy, $ey);

                $fyLabelsOrdered[] = $label;
                $fyIdToLabel[$fy->id] = $label;

                $minStart = $minStart ? $minStart->min($start) : $start;
                $maxEnd   = $maxEnd   ? $maxEnd->max($end)     : $end;
            }

            // Aggregate totals per client per selected FY (join by date range)
            $rows = DB::table('t_sales_invoice as si')
                ->join('t_sales_invoice_products as sip', 'sip.sales_invoice_id', '=', 'si.id')
                ->join('t_clients as c', 'c.id', '=', 'si.client_id')
                ->join('t_financial_year as fy', function ($j) {
                    $j->on('si.sales_invoice_date', '>=', 'fy.start_date')
                    ->on('si.sales_invoice_date', '<=', 'fy.end_date');
                })
                ->where('si.company_id', $companyId)
                ->whereIn('fy.id', $selectedFyIds)
                ->whereBetween('si.sales_invoice_date', [$minStart, $maxEnd])
                ->groupBy('si.client_id', 'c.name', 'fy.id')
                ->select([
                    'si.client_id',
                    'c.name as client_name',
                    'fy.id as fy_id',
                    DB::raw('ROUND(COALESCE(SUM(sip.amount), 0), 2) as total_amount'),
                    DB::raw('ROUND(COALESCE(SUM(sip.profit), 0), 2) as total_profit'),
                ])
                ->get();

            // Pivot per client -> FY label
            $byClient = []; // client_id => ['name'=>..., 'amounts'=>[label=>float], 'profits'=>[label=>float]]
            foreach ($rows as $r) {
                $cid = (int)$r->client_id;
                $lbl = $fyIdToLabel[$r->fy_id] ?? null;
                if ($lbl === null) continue;

                if (!isset($byClient[$cid])) {
                    $byClient[$cid] = [
                        'name'    => $r->client_name ?: 'Unknown',
                        'amounts' => [],
                        'profits' => [],
                    ];
                }
                $byClient[$cid]['amounts'][$lbl] = (float)$r->total_amount;
                $byClient[$cid]['profits'][$lbl] = (float)$r->total_profit;
            }

            // ---- FILTER: only keep clients with Amount > 0 in EACH of last two FYs ----
            $latestLabel = $fyLabelsOrdered[count($fyLabelsOrdered)-1];
            $prevLabel   = $fyLabelsOrdered[count($fyLabelsOrdered)-2];

            $byClient = array_filter($byClient, function ($data) use ($latestLabel, $prevLabel) {
                $currAmt = (float)($data['amounts'][$latestLabel] ?? 0.0);
                $prevAmt = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                return ($currAmt > 0) && ($prevAmt > 0);
            });

            // Sorting preps
            $norm = strtolower(trim($sortByRaw));
            $norm = str_replace(['%20','%2F'], ' ', $norm);
            $norm = preg_replace('/\s+/', ' ', $norm);

            $sortType = 'name'; $sortLabel = null;
            if (in_array($norm, ['percentage', 'percentage (amount)', 'percentage_amount', 'pct', 'growth'], true)) {
                $sortType = 'percentage';
            } elseif (preg_match('/^(amount|profit)[\s:_-]*([0-9]{2}-[0-9]{2})$/i', $norm, $m)) {
                $sortType = strtolower($m[1]); // amount|profit
                $sortLabel = $m[2];            // YY-YY
            } elseif (in_array($norm, ['amount','profit'], true)) {
                $sortType = $norm;
                $sortLabel = $latestLabel;     // default to latest year if no label specified
            } elseif (in_array($norm, ['name','client','client_name'], true)) {
                $sortType = 'name';
            }

            // Build list with sort keys
            $byClientList = [];
            foreach ($byClient as $cid => $data) {
                $keyVal = 0;
                if ($sortType === 'name') {
                    $keyVal = strtolower($data['name']);
                } elseif ($sortType === 'amount') {
                    $label = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['amounts'][$label] ?? 0.0);
                } elseif ($sortType === 'profit') {
                    $label = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['profits'][$label] ?? 0.0);
                } elseif ($sortType === 'percentage') {
                    $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                    $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                    if ($prev == 0.0 && $curr > 0.0)       $keyVal = 100.0;
                    elseif ($prev > 0.0 && $curr == 0.0)   $keyVal = -100.0;
                    elseif ($prev > 0.0)                   $keyVal = (($curr - $prev) / $prev) * 100.0;
                    else                                    $keyVal = 0.0;
                }
                $byClientList[] = ['cid' => $cid, 'data' => $data, 'key' => $keyVal];
            }

            // Sort (then tie-break by name)
            usort($byClientList, function ($a, $b) use ($sortDir) {
                if ($a['key'] == $b['key']) {
                    return strcasecmp($a['data']['name'], $b['data']['name']);
                }
                if ($sortDir === 'asc')  return ($a['key'] < $b['key']) ? -1 : 1;
                return ($a['key'] > $b['key']) ? -1 : 1;
            });

            // Pagination counts
            $totalRecords = count($byClientList);
            $pageSlice    = array_slice($byClientList, $offset, $limit);
            $count        = count($pageSlice);

            // Max 2-decimal string formatting
            $fmt2 = fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');

            // Build rows
            $table = [];
            $sn = $offset + 1;
            foreach ($pageSlice as $item) {
                $data = $item['data'];
                $row = [
                    'sn'   => $sn++,
                    'name' => $data['name'],
                ];
                foreach ($fyLabelsOrdered as $lbl) {
                    $row["Amount {$lbl}"] = $fmt2($data['amounts'][$lbl] ?? 0.0);
                    $row["Profit {$lbl}"] = $fmt2($data['profits'][$lbl] ?? 0.0);
                }
                // Percentage (Amount) latest vs previous
                $pct = '0 %';
                $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                if     ($prev == 0.0 && $curr > 0.0) $pct = '100 %';
                elseif ($prev > 0.0 && $curr == 0.0) $pct = '-100 %';
                elseif ($prev > 0.0)                 $pct = $fmt2((($curr - $prev) / $prev) * 100.0) . ' %';

                $row['Percentage (Amount)'] = $pct;
                $table[] = $row;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client-wise yearly sales summary fetched successfully!',
                'data' => [
                    'years' => $fyLabelsOrdered,
                    'rows'  => $table,
                    'sort'  => [
                        'sort_by'  => $sortByRaw,
                        'sort_dir' => $sortDir,
                    ],
                ],
                'count' => $count,
                'total_records' => $totalRecords,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching yearly summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportClientWiseYearlySalesSummaryExcel(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // ---- Financial years (comma-separated ids) ----
            $providedIds = collect(explode(',', (string)$request->input('years', '')))
                ->filter(fn($id) => is_numeric(trim($id)))
                ->map(fn($id) => (int) trim($id))
                ->unique()
                ->values();

            $fysQuery = FinancialYearModel::query()->where('company_id', $companyId);
            if ($providedIds->isNotEmpty()) {
                $fysQuery->whereIn('id', $providedIds);
            } else {
                // default to latest 4 FYs
                $fysQuery->orderBy('start_date', 'desc')->limit(4);
            }
            $fys = $fysQuery->get()->sortBy('start_date')->values();

            if ($fys->isEmpty()) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No financial years found for the given IDs.',
                ], 404);
            }
            if ($fys->count() < 2) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'At least two financial years are required to apply the last-2-years filter.',
                ], 422);
            }

            // ---- Build FY label map (YY-YY), ranges ----
            $selectedFyIds   = $fys->pluck('id')->all();
            $fyLabelsOrdered = []; // oldest -> newest
            $fyIdToLabel     = [];
            $minStart = null; $maxEnd = null;

            foreach ($fys as $fy) {
                $start = $fy->start_date instanceof Carbon ? $fy->start_date : Carbon::parse($fy->start_date);
                $end   = $fy->end_date   instanceof Carbon ? $fy->end_date   : Carbon::parse($fy->end_date);

                $sy = (int)$start->format('y');
                $ey = (int)$end->format('y');
                $label = sprintf('%02d-%02d', $sy, $ey);

                $fyLabelsOrdered[] = $label;
                $fyIdToLabel[$fy->id] = $label;

                $minStart = $minStart ? $minStart->min($start) : $start;
                $maxEnd   = $maxEnd   ? $maxEnd->max($end)     : $end;
            }

            // ---- Aggregate totals for selected FYs (date-range join) ----
            $rows = DB::table('t_sales_invoice as si')
                ->join('t_sales_invoice_products as sip', 'sip.sales_invoice_id', '=', 'si.id')
                ->join('t_clients as c', 'c.id', '=', 'si.client_id')
                ->join('t_financial_year as fy', function ($j) {
                    $j->on('si.sales_invoice_date', '>=', 'fy.start_date')
                    ->on('si.sales_invoice_date', '<=', 'fy.end_date');
                })
                ->where('si.company_id', $companyId)
                ->whereIn('fy.id', $selectedFyIds)
                ->whereBetween('si.sales_invoice_date', [$minStart, $maxEnd])
                ->groupBy('si.client_id', 'c.name', 'fy.id')
                ->select([
                    'si.client_id',
                    'c.name as client_name',
                    'fy.id as fy_id',
                    DB::raw('ROUND(COALESCE(SUM(sip.amount), 0), 2) as total_amount'),
                    DB::raw('ROUND(COALESCE(SUM(sip.profit), 0),  2) as total_profit'),
                ])
                ->get();

            // ---- Pivot per client -> FY label ----
            $byClient = []; // client_id => ['name'=>..., 'amounts'=>[], 'profits'=>[]]
            foreach ($rows as $r) {
                $cid = (int)$r->client_id;
                $lbl = $fyIdToLabel[$r->fy_id] ?? null;
                if ($lbl === null) continue;

                if (!isset($byClient[$cid])) {
                    $byClient[$cid] = [
                        'name'    => $r->client_name ?: 'Unknown',
                        'amounts' => [],
                        'profits' => [],
                    ];
                }
                $byClient[$cid]['amounts'][$lbl] = (float)$r->total_amount;
                $byClient[$cid]['profits'][$lbl] = (float)$r->total_profit;
            }

            // ---- Filter: Amount > 0 in each of the last two FYs ----
            $latestLabel = $fyLabelsOrdered[count($fyLabelsOrdered)-1];
            $prevLabel   = $fyLabelsOrdered[count($fyLabelsOrdered)-2];

            $byClient = array_filter($byClient, function ($data) use ($latestLabel, $prevLabel) {
                $currAmt = (float)($data['amounts'][$latestLabel] ?? 0.0);
                $prevAmt = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                return ($currAmt > 0) && ($prevAmt > 0);
            });

            if (empty($byClient)) {
                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'No clients found with positive amounts in last 2 years.',
                    'data' => [],
                ], 200);
            }

            // ---- Sorting (same options as API) ----
            $sortByRaw = (string) $request->input('sort_by', 'name');
            $sortDir   = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $norm = strtolower(trim($sortByRaw));
            $norm = str_replace(['%20','%2F'], ' ', $norm);
            $norm = preg_replace('/\s+/', ' ', $norm);

            $sortType = 'name'; $sortLabel = null;
            if (in_array($norm, ['percentage', 'percentage (amount)', 'percentage_amount', 'pct', 'growth'], true)) {
                $sortType = 'percentage';
            } elseif (preg_match('/^(amount|profit)[\s:_-]*([0-9]{2}-[0-9]{2})$/i', $norm, $m)) {
                $sortType  = strtolower($m[1]); // amount|profit
                $sortLabel = $m[2];             // YY-YY
            } elseif (in_array($norm, ['amount','profit'], true)) {
                $sortType  = $norm;
                $sortLabel = $latestLabel;
            } elseif (in_array($norm, ['name','client','client_name'], true)) {
                $sortType = 'name';
            }

            $list = [];
            foreach ($byClient as $cid => $data) {
                $keyVal = 0;
                if ($sortType === 'name') {
                    $keyVal = strtolower($data['name']);
                } elseif ($sortType === 'amount') {
                    $label  = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['amounts'][$label] ?? 0.0);
                } elseif ($sortType === 'profit') {
                    $label  = $sortLabel ?: $latestLabel;
                    $keyVal = (float)($data['profits'][$label] ?? 0.0);
                } elseif ($sortType === 'percentage') {
                    $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                    $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                    if ($prev == 0.0 && $curr > 0.0)       $keyVal = 100.0;
                    elseif ($prev > 0.0 && $curr == 0.0)   $keyVal = -100.0;
                    elseif ($prev > 0.0)                   $keyVal = (($curr - $prev) / $prev) * 100.0;
                    else                                    $keyVal = 0.0;
                }
                $list[] = ['data' => $data, 'key' => $keyVal];
            }

            usort($list, function ($a, $b) use ($sortDir) {
                if ($a['key'] == $b['key']) return strcasecmp($a['data']['name'], $b['data']['name']);
                return $sortDir === 'asc'
                    ? (($a['key'] < $b['key']) ? -1 : 1)
                    : (($a['key'] > $b['key']) ? -1 : 1);
            });

            // ---- Build headings & Excel rows (percentage number, no '%') ----
            $headings = ['SN', 'Name'];
            foreach ($fyLabelsOrdered as $lbl) {
                $headings[] = "Amount {$lbl}";
                $headings[] = "Profit {$lbl}";
            }
            $headings[] = 'Percentage (Amount)';

            $fmt2 = fn($v) => round((float)$v, 2);

            $excelRows = [];
            $sn = 1;
            foreach ($list as $item) {
                $data = $item['data'];
                $row = [$sn++, $data['name']];
                foreach ($fyLabelsOrdered as $lbl) {
                    $row[] = $fmt2($data['amounts'][$lbl] ?? 0.0);
                    $row[] = $fmt2($data['profits'][$lbl] ?? 0.0);
                }
                $curr = (float)($data['amounts'][$latestLabel] ?? 0.0);
                $prev = (float)($data['amounts'][$prevLabel]   ?? 0.0);
                $pct  = 0.0;
                if ($prev == 0.0 && $curr > 0.0)       $pct = 100.0;
                elseif ($prev > 0.0 && $curr == 0.0)   $pct = -100.0;
                elseif ($prev > 0.0)                   $pct = (($curr - $prev) / $prev) * 100.0;
                $row[] = $fmt2($pct); // numeric, no symbol
                $excelRows[] = $row;
            }

            // ---- Save to storage/app/public/uploads and return URL ----
            $disk = 'public';
            $directory = 'uploads';
            if (!Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->makeDirectory($directory);
            }

            $fileName = 'ClientWise_Yearly_Summary_' . now()->format('Ymd_His') . '.xlsx';
            $relativePath = $directory . '/' . $fileName;

            // Determine numeric column indices for right align + number format, and percentage column for color
            $percentageColIndex = 2 + (count($fyLabelsOrdered) * 2) + 1; // 1-based
            $numericCols = [];
            for ($i = 3; $i < $percentageColIndex; $i++) $numericCols[] = $i; // Amount/Profit cols
            $numericCols[] = $percentageColIndex; // Percentage col

            $stored = Excel::store(
                new ClientWiseYearlySalesSummaryExport($excelRows, $headings, $percentageColIndex, $numericCols),
                $relativePath,
                $disk
            );

            if (!$stored) {
                return response()->json([
                    'code' => 500,
                    'success' => false,
                    'message' => 'Excel could not be saved.',
                ], 500);
            }

            // Public URL (requires: php artisan storage:link)
            $publicUrl = Storage::disk($disk)->url($relativePath);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Excel exported successfully!',
                'data' => [
                    'url' => asset($publicUrl),
                    'file_name' => $fileName,
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while exporting Excel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTradingSummary(Request $request)
    {
        $auth = Auth::user();
        if (!$auth) {
            return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized','data'=>[]], 401);
        }

        try {
            $companyId = $auth->company_id;
            $tz = 'Asia/Kolkata';
            $now = \Carbon\Carbon::now($tz);

            // -------- Inputs --------
            $dateFrom = $request->input('date_from');
            $dateTo   = $request->input('date_to');
            $fyId     = $request->input('financial_year_id'); // used for dates and for stock-year

            // -------- Resolve period --------
            if ($fyId) {
                $fy = FinancialYearModel::where('company_id', $companyId)
                    ->where('id', $fyId)
                    ->firstOrFail();

                $start = \Carbon\Carbon::parse($fy->start_date, $tz)->startOfDay();
                $end   = \Carbon\Carbon::parse($fy->end_date, $tz)->endOfDay();
            } elseif ($dateFrom && $dateTo) {
                $start = \Carbon\Carbon::parse($dateFrom, $tz)->startOfDay();
                $end   = \Carbon\Carbon::parse($dateTo, $tz)->endOfDay();
                $fy    = null; // no FY row in this path
            } else {
                // Default: current FY (Apr 1 -> Mar 31)
                $fyYear = (int)$now->format('Y');
                if ((int)$now->format('m') < 4) $fyYear -= 1;
                $start = \Carbon\Carbon::create($fyYear, 4, 1, 0, 0, 0, $tz);
                $end   = \Carbon\Carbon::create($fyYear + 1, 3, 31, 23, 59, 59, $tz);
                $fy    = null;
            }

            // -------- Helpers --------
            $formatInr0 = function ($n): string {
                $n = (int) round((float)$n);
                $neg = $n < 0 ? '-' : '';
                $n = abs($n);
                $s = (string)$n;
                if (strlen($s) > 3) {
                    $s = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3)) . ',' . substr($s, -3);
                }
                return $neg.$s;
            };

            $sumExpr = function (\Illuminate\Database\Query\Builder $qb, string $expr, string $alias = 's'): float {
                $val = $qb->selectRaw("$expr AS $alias")->value($alias);
                return $val ? (float)$val : 0.0;
            };

            // ex-tax from header: total - (cgst+sgst+igst)
            $headerExTaxExpr = function (string $p = '') {
                $p = $p ? $p.'.' : '';
                return "
                    SUM(
                        COALESCE({$p}total,0)
                        - (COALESCE({$p}cgst,0)+COALESCE({$p}sgst,0)+COALESCE({$p}igst,0))
                    )
                ";
            };

            // -------- SALES (header-based, ex-tax) --------
            $salesExTax = $sumExpr(
                DB::table('t_sales_invoice')
                    ->where('company_id', $companyId)
                    ->whereBetween('sales_invoice_date', [$start->toDateTimeString(), $end->toDateTimeString()]),
                $headerExTaxExpr()
            );

            // -------- PURCHASE (header-based, ex-tax) --------
            $purchaseExTax = $sumExpr(
                DB::table('t_purchase_invoice')
                    ->where('company_id', $companyId)
                    ->whereBetween('purchase_invoice_date', [$start->toDateTimeString(), $end->toDateTimeString()]),
                $headerExTaxExpr()
            );

            // -------- DEBIT NOTE (header-based, ex-tax) --------
            $debitNote = $sumExpr(
                DB::table('t_debit_note')
                    ->where('company_id', $companyId)
                    ->whereBetween('debit_note_date', [$start->toDateTimeString(), $end->toDateTimeString()]),
                $headerExTaxExpr()
            );

            // -------- CREDIT NOTE (header-based, ex-tax) --------
            $creditNote = $sumExpr(
                DB::table('t_credit_note')
                    ->where('company_id', $companyId)
                    ->whereBetween('credit_note_date', [$start->toDateTimeString(), $end->toDateTimeString()]),
                $headerExTaxExpr()
            );

            // -------- Opening & Closing Stock (from FY table; fallback for closing) --------
            $openingStock = 0.0;
            $closingStock = 0.0;
            $stockSource  = ['opening' => 'n/a', 'closing' => 'n/a'];

            if ($fyId) {
                // Prefer values directly from the FY row
                $openingStock = (float) ($fy->opening_stock ?? 0);
                $stockSource['opening'] = 't_financial_year.opening_stock';

                $closingFromFY = $fy->closing_stock ?? null;
                if ($closingFromFY === null || $closingFromFY === '') {
                    // Fallback to sum of t_closing_stock.value for this FY id
                    $closingStock = (float) DB::table('t_closing_stock')
                        ->where('company_id', $companyId)
                        ->where('year', $fyId) // year stores the financial_year_id
                        ->sum(DB::raw('COALESCE(value,0)'));
                    $stockSource['closing'] = 't_closing_stock(sum value by year=financial_year_id)';
                } else {
                    $closingStock = (float) $closingFromFY;
                    $stockSource['closing'] = 't_financial_year.closing_stock';
                }
            } else {
                // No FY id provided → leave both as 0 (or implement your old heuristics if needed)
                $openingStock = 0.0;
                $closingStock = 0.0;
                $stockSource['opening'] = 'none';
                $stockSource['closing'] = 'none';
            }

            // -------- Sales-Invoice-wise Profit (from line table) --------
            $salesInvoiceIds = DB::table('t_sales_invoice')
                ->where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->pluck('id');

            $salesInvoiceWiseProfit = (float) DB::table('t_sales_invoice_products')
                ->whereIn('sales_invoice_id', $salesInvoiceIds)
                ->sum(DB::raw('COALESCE(profit,0)'));

            // -------- Trading Profit (without tax) --------
            // Closing + Sales − Opening − Purchase + Debit Note − Credit Note
            $tradingProfit = $closingStock + $salesExTax - $openingStock - $purchaseExTax + $debitNote - $creditNote;

            // -------- Round everything (no decimals) --------
            $closingStockI  = (int) round($closingStock);
            $salesExTaxI    = (int) round($salesExTax);
            $openingStockI  = (int) round($openingStock);
            $purchaseExTaxI = (int) round($purchaseExTax);
            $debitNoteI     = (int) round($debitNote);
            $creditNoteI    = (int) round($creditNote);
            $tradingProfitI = (int) round($tradingProfit);
            $siwProfitI     = (int) round($salesInvoiceWiseProfit);
            $diffI          = (int) round($tradingProfitI - $siwProfitI);

            $payload = [
                'period' => [
                    'from' => $start->toDateTimeString(),
                    'to'   => $end->toDateTimeString(),
                ],
                'stock_source' => $stockSource, // for transparency/debug
                'stock_year_used' => $fyId ?? null,
                'figures_without_tax' => [
                    'closing_stock' => ['value' => $closingStockI,  'pretty' => 'Rs. '.$formatInr0($closingStockI)],
                    'sales'         => ['value' => $salesExTaxI,    'pretty' => 'Rs. '.$formatInr0($salesExTaxI)],
                    'opening_stock' => ['value' => $openingStockI,  'pretty' => 'Rs. '.$formatInr0($openingStockI)],
                    'purchase'      => ['value' => $purchaseExTaxI, 'pretty' => 'Rs. '.$formatInr0($purchaseExTaxI)],
                    'debit_note'    => ['value' => $debitNoteI,     'pretty' => 'Rs. '.$formatInr0($debitNoteI)],
                    'credit_note'   => ['value' => $creditNoteI,    'pretty' => 'Rs. '.$formatInr0($creditNoteI)],
                ],
                'profit' => [
                    'trading_formula' => [
                        'value'   => $tradingProfitI,
                        'pretty'  => 'Rs. '.$formatInr0($tradingProfitI),
                        'formula' => 'Closing + Sales − Opening − Purchase + Debit Note − Credit Note',
                    ],
                    'sales_invoice_wise' => [
                        'value'      => $siwProfitI,
                        'pretty'     => 'Rs. '.$formatInr0($siwProfitI),
                        'definition' => 'SUM(profit) from t_sales_invoice_products',
                    ],
                    'difference' => [
                        'value'  => $diffI,
                        'pretty' => 'Rs. '.$formatInr0($diffI),
                    ],
                ],
            ];

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Trading summary generated successfully.',
                'data' => $payload,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to compute trading summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // sales vs sales barchart
    public function getMonthlySalesSummary(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Parse start and end dates from the request
            $startDate = Carbon::parse($request->start_date)->startOfMonth();
            $endDate = Carbon::parse($request->end_date)->endOfMonth();

            // Initialize empty arrays to store results
            $months = [];
            $salesTotals = [];
            $previousSalesTotals = [];

            // Generate months between start and end dates
            $current = $startDate->copy();

            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();
                $monthName = $current->format('F'); // e.g., "January 2022"

                // Query sales for this month
                $salesStats = DB::table('t_sales_invoice as si')
                    ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                    ->where('si.company_id', $companyId)
                    ->whereBetween('si.sales_invoice_date', [$monthStart, $monthEnd])
                    ->selectRaw('SUM(sip.amount) as total, COUNT(DISTINCT si.id) as invoice_count')
                    ->first();

                // If no data found for this month, default to 0
                $salesTotal = $salesStats->total ?? 0;
                
                // Query previous year sales for this month
                $previousMonthStart = $monthStart->copy()->subYear();
                $previousMonthEnd = $monthEnd->copy()->subYear();
                $previousSalesStats = DB::table('t_sales_invoice as si')
                    ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                    ->where('si.company_id', $companyId)
                    ->whereBetween('si.sales_invoice_date', [$previousMonthStart, $previousMonthEnd])
                    ->selectRaw('SUM(sip.amount) as total')
                    ->first();

                $previousSalesTotal = $previousSalesStats->total ?? 0;

                // Populate results for the current month
                $months[] = $monthName;
                $salesTotals[] = round($salesTotal); // If no sales, default to 0
                $previousSalesTotals[] = round($previousSalesTotal);

                // Move to the next month
                $current->addMonth();
            }

            // Return the monthly sales data
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly sales summary fetched successfully!',
                'data' => [
                    'month' => $months,
                    'sales_total' => $salesTotals,
                    'previous_sales_total' => $previousSalesTotals,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while processing data: ' . $e->getMessage(),
            ], 500);
        }
    }

    // sales vs sales graph
    public function getMonthlyCumulativeSalesSummary(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Parse the start and end dates from the request
            $startDate = Carbon::parse($request->input('start_date'))->startOfMonth();
            $endDate = Carbon::parse($request->input('end_date'))->endOfMonth();

            // SQL query to get monthly sales
            $sales = SalesInvoiceModel::join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                ->where('si.company_id', $companyId)
                ->whereBetween('si.sales_invoice_date', [$startDate, $endDate])
                ->selectRaw('
                    MONTH(si.sales_invoice_date) as month,
                    YEAR(si.sales_invoice_date) as year,
                    SUM(sip.amount) as monthly_sales_amount
                ')
                ->from('t_sales_invoice as si') // Alias the SalesInvoiceModel table as 'si'
                ->groupBy(DB::raw('YEAR(si.sales_invoice_date), MONTH(si.sales_invoice_date)')) // Group by year and month
                ->orderBy(DB::raw('YEAR(si.sales_invoice_date), MONTH(si.sales_invoice_date)')) // Order by year and month
                ->get();

            // Query for previous year sales (same months, previous year)
            $prevStart = $startDate->copy()->subYear();
            $prevEnd = $endDate->copy()->subYear();

            $prevSales = SalesInvoiceModel::join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                ->where('si.company_id', $companyId)
                ->whereBetween('si.sales_invoice_date', [$prevStart, $prevEnd])
                ->selectRaw('
                    MONTH(si.sales_invoice_date) as month,
                    YEAR(si.sales_invoice_date) as year,
                    SUM(sip.amount) as monthly_sales_amount
                ')
                ->from('t_sales_invoice as si')
                ->groupBy(DB::raw('YEAR(si.sales_invoice_date), MONTH(si.sales_invoice_date)'))
                ->orderBy(DB::raw('YEAR(si.sales_invoice_date), MONTH(si.sales_invoice_date)'))
                ->get();

            // Map sales by month for both current and previous years
            $salesByMonth = [];
            foreach ($sales as $sale) {
                $salesByMonth[$sale->month] = $sale->monthly_sales_amount;
            }
            $prevSalesByMonth = [];
            foreach ($prevSales as $sale) {
                $prevSalesByMonth[$sale->month] = $sale->monthly_sales_amount;
            }

            // Build the final arrays
            $months = [];
            $cumulativeSales = [];
            $previousCumulativeSales = [];

            // Initialize variables to calculate cumulative sales
            $cumulative = 0;
            $prevCumulative = 0;

            // Generate month numbers in range (to cover missing months as zero)
            $period = Carbon::parse($startDate)->monthsUntil($endDate->copy()->endOfMonth());

            foreach ($period as $dt) {
                $monthNum = $dt->month;
                $monthName = $dt->format('F'); // No year as you requested

                // For current year
                $monthSales = isset($salesByMonth[$monthNum]) ? $salesByMonth[$monthNum] : 0;
                $cumulative += $monthSales;

                // For previous year
                $prevMonthSales = isset($prevSalesByMonth[$monthNum]) ? $prevSalesByMonth[$monthNum] : 0;
                $prevCumulative += $prevMonthSales;

                $months[] = $monthName;
                $cumulativeSales[] = round($cumulative);
                $previousCumulativeSales[] = round($prevCumulative);
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly cumulative sales fetched successfully.',
                'data' => [
                    'month' => $months,
                    'cumulative_sales_amount' => $cumulativeSales,
                    'previous_cumulative_sales_amount' => $previousCumulativeSales,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while processing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // purchase vs purchase barchart
    public function getMonthlyPurchaseSummary(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Parse start and end dates from the request
            $startDate = Carbon::parse($request->start_date)->startOfMonth();
            $endDate   = Carbon::parse($request->end_date)->endOfMonth();

            // Initialize arrays to store results
            $months = [];
            $purchaseTotals = [];
            $previousPurchaseTotals = [];

            // Generate months between start and end dates
            $current = $startDate->copy();

            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd   = $current->copy()->endOfMonth();
                $monthName  = $current->format('F'); // e.g., "January"

                // Query purchases for this month
                $purchaseStats = DB::table('t_purchase_invoice as pi')
                    ->join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
                    ->where('pi.company_id', $companyId)
                    ->whereBetween('pi.purchase_invoice_date', [$monthStart, $monthEnd])
                    ->selectRaw('SUM(pip.amount) as total, COUNT(DISTINCT pi.id) as invoice_count')
                    ->first();

                $purchaseTotal = $purchaseStats->total ?? 0;

                // Previous year same month range
                $prevMonthStart = $monthStart->copy()->subYear();
                $prevMonthEnd   = $monthEnd->copy()->subYear();

                $prevPurchaseStats = DB::table('t_purchase_invoice as pi')
                    ->join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
                    ->where('pi.company_id', $companyId)
                    ->whereBetween('pi.purchase_invoice_date', [$prevMonthStart, $prevMonthEnd])
                    ->selectRaw('SUM(pip.amount) as total')
                    ->first();

                $prevPurchaseTotal = $prevPurchaseStats->total ?? 0;

                // Populate results
                $months[] = $monthName;
                $purchaseTotals[] = round($purchaseTotal);
                $previousPurchaseTotals[] = round($prevPurchaseTotal);

                $current->addMonth();
            }

            // Return the monthly purchase data
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly purchase summary fetched successfully!',
                'data' => [
                    'month' => $months,
                    'purchase_total' => $purchaseTotals,
                    'previous_purchase_total' => $previousPurchaseTotals,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while processing data: ' . $e->getMessage(),
            ], 500);
        }
    }

    // purchase cumulative vs previous cumulative
    public function getMonthlyCumulativePurchaseSummary(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Parse range
            $startDate = Carbon::parse($request->input('start_date'))->startOfMonth();
            $endDate   = Carbon::parse($request->input('end_date'))->endOfMonth();

            // Current period monthly totals
            $purchases = PurchaseInvoiceModel::join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
                ->where('pi.company_id', $companyId)
                ->whereBetween('pi.purchase_invoice_date', [$startDate, $endDate])
                ->selectRaw('
                    MONTH(pi.purchase_invoice_date) as month,
                    YEAR(pi.purchase_invoice_date) as year,
                    SUM(pip.amount) as monthly_purchase_amount
                ')
                ->from('t_purchase_invoice as pi')
                ->groupBy(DB::raw('YEAR(pi.purchase_invoice_date), MONTH(pi.purchase_invoice_date)'))
                ->orderBy(DB::raw('YEAR(pi.purchase_invoice_date), MONTH(pi.purchase_invoice_date)'))
                ->get();

            // Previous year same months
            $prevStart = $startDate->copy()->subYear();
            $prevEnd   = $endDate->copy()->subYear();

            $prevPurchases = PurchaseInvoiceModel::join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
                ->where('pi.company_id', $companyId)
                ->whereBetween('pi.purchase_invoice_date', [$prevStart, $prevEnd])
                ->selectRaw('
                    MONTH(pi.purchase_invoice_date) as month,
                    YEAR(pi.purchase_invoice_date) as year,
                    SUM(pip.amount) as monthly_purchase_amount
                ')
                ->from('t_purchase_invoice as pi')
                ->groupBy(DB::raw('YEAR(pi.purchase_invoice_date), MONTH(pi.purchase_invoice_date)'))
                ->orderBy(DB::raw('YEAR(pi.purchase_invoice_date), MONTH(pi.purchase_invoice_date)'))
                ->get();

            // Map to month => amount
            $purchaseByMonth = [];
            foreach ($purchases as $row) {
                $purchaseByMonth[$row->month] = $row->monthly_purchase_amount;
            }
            $prevPurchaseByMonth = [];
            foreach ($prevPurchases as $row) {
                $prevPurchaseByMonth[$row->month] = $row->monthly_purchase_amount;
            }

            // Build cumulative arrays
            $months = [];
            $cumulativePurchase = [];
            $previousCumulativePurchase = [];

            $cum = 0;
            $prevCum = 0;

            $period = Carbon::parse($startDate)->monthsUntil($endDate->copy()->endOfMonth());

            foreach ($period as $dt) {
                $m = $dt->month;
                $months[] = $dt->format('F');

                $curr = $purchaseByMonth[$m] ?? 0;
                $prev = $prevPurchaseByMonth[$m] ?? 0;

                $cum     += $curr;
                $prevCum += $prev;

                $cumulativePurchase[] = round($cum);
                $previousCumulativePurchase[] = round($prevCum);
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly cumulative purchases fetched successfully.',
                'data' => [
                    'month' => $months,
                    'cumulative_purchase_amount' => $cumulativePurchase,
                    'previous_cumulative_purchase_amount' => $previousCumulativePurchase,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while processing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getGroupWiseStockForPie(Request $request)
    {
        try {
            $auth = Auth::user();
            if (!$auth) {
                return response()->json(['code'=>401,'success'=>false,'message'=>'Unauthorized'], 401);
            }
            $companyId = $auth->company_id;

            // 1️⃣ Find current financial year for this company
            $today = Carbon::today();
            $currentFY = FinancialYearModel::where('company_id', $companyId)
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->first();

            if (!$currentFY) {
                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'No active financial year found for today.',
                    'data' => ['group' => [], 'stock' => []]
                ], 200);
            }

            // 2️⃣ Table names
            $csTable = (new ClosingStockModel)->getTable(); // t_closing_stock
            $pTable  = (new ProductsModel)->getTable();     // t_products
            $gTable  = (new GroupModel)->getTable();        // t_group or t_groups

            // 3️⃣ Detect group reference type
            $hasGroupId   = Schema::hasColumn($pTable, 'group_id');
            $hasGroupText = Schema::hasColumn($pTable, 'group');
            $hasGroupName = Schema::hasColumn($pTable, 'group_name');

            // 4️⃣ Build query based on structure
            if ($hasGroupId) {
                // Join to group table to get group name
                $rows = DB::table("$csTable as cs")
                    ->join("$pTable as p", 'p.id', '=', 'cs.product_id')
                    ->leftJoin("$gTable as g", 'g.id', '=', 'p.group_id')
                    ->where('cs.company_id', $companyId)
                    ->where('cs.year', $currentFY->id)
                    ->selectRaw("COALESCE(g.name, 'Not Mapped') as group_name, SUM(cs.value) as total_value")
                    ->groupBy('group_name')
                    ->orderByDesc('total_value')
                    ->get();
            } elseif ($hasGroupText) {
                // Use text column "group"
                $rows = DB::table("$csTable as cs")
                    ->join("$pTable as p", 'p.id', '=', 'cs.product_id')
                    ->where('cs.company_id', $companyId)
                    ->where('cs.year', $currentFY->id)
                    ->selectRaw("COALESCE(NULLIF(TRIM(`p`.`group`), ''), 'Not Mapped') as group_name, SUM(cs.value) as total_value")
                    ->groupBy('group_name')
                    ->orderByDesc('total_value')
                    ->get();
            } elseif ($hasGroupName) {
                // Use text column "group_name"
                $rows = DB::table("$csTable as cs")
                    ->join("$pTable as p", 'p.id', '=', 'cs.product_id')
                    ->where('cs.company_id', $companyId)
                    ->where('cs.year', $currentFY->id)
                    ->selectRaw("COALESCE(NULLIF(TRIM(p.group_name), ''), 'Not Mapped') as group_name, SUM(cs.value) as total_value")
                    ->groupBy('group_name')
                    ->orderByDesc('total_value')
                    ->get();
            } else {
                // No group info at all — single bucket
                $total = DB::table("$csTable as cs")
                    ->where('cs.company_id', $companyId)
                    ->where('cs.year', $currentFY->id)
                    ->sum('cs.value');

                $rows = collect([(object)['group_name' => 'Not Mapped', 'total_value' => $total]]);
            }

            // 5️⃣ Format for response
            $groups = [];
            $stock  = [];
            foreach ($rows as $r) {
                $groups[] = (string)$r->group_name;
                $stock[]  = (int) round($r->total_value);
            }

            // 6️⃣ Final response
            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Group wise Stock Details fetched successfully.',
                'data'    => [
                    'group' => $groups,
                    'stock' => $stock,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // profit distribution
    public function getDailyProfitDistribution(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Parse the start and end dates from the request
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();

            // Fetch daily profit using raw SQL
            $dailyProfitData = DB::table('t_sales_invoice as si')
                ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                ->selectRaw('
                    DATE(si.sales_invoice_date) as invoice_date,
                    ROUND(SUM(sip.profit)) as daily_profit
                ')
                ->where('si.company_id', $companyId)
                ->whereBetween('si.sales_invoice_date', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(si.sales_invoice_date)'))
                ->orderBy(DB::raw('DATE(si.sales_invoice_date)'))
                ->get();

            // If no data found, return an empty array
            if ($dailyProfitData->isEmpty()) {
                return response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'No profit data found for the given date range.',
                    'data' => []
                ]);
            }

            // Format the data to match the desired structure
            $formattedData = $dailyProfitData->map(function ($item) {
                return [
                    'date' => $item->invoice_date,
                    'daily_profit' => $item->daily_profit
                ];
            });

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Daily profit distribution fetched successfully.',
                'data' => $formattedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while processing data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // quotation statistic
    public function getMonthlyQuotationStatusReport(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Generate all months between start date and end date
            $months = collect();
            $current = $startDate->copy();

            while ($current <= $endDate) {
                $months->push([
                    'month' => $current->format('F Y'),
                    'month_num' => $current->format('m'),
                    'year' => $current->format('Y'),
                ]);
                $current->addMonth();
            }

            // Query to fetch total amounts and count grouped by month and status
            $query = DB::table('t_quotations')
              ->selectRaw('MONTH(quotation_date) as month, YEAR(quotation_date) as year, status, ROUND(SUM(total)) as total_amount')
                ->where('company_id', $companyId)
                ->whereBetween('quotation_date', [$startDate, $endDate])
                ->groupBy(DB::raw('YEAR(quotation_date), MONTH(quotation_date), status'))
                ->get();

            // Initialize the result arrays for each status
            $result = [
                'pending' => [],
                'rejected' => [],
                'completed' => [],
            ];

            // Populate the result arrays with the appropriate data
            foreach ($months as $month) {
                // Default to 0 if no data exists for this month in any status
                $result['pending'][] = [
                    'month' => $month['month'],
                    'total_amount' => 0,
                ];
                $result['rejected'][] = [
                    'month' => $month['month'],
                    'total_amount' => 0,
                ];
                $result['completed'][] = [
                    'month' => $month['month'],
                    'total_amount' => 0,
                ];
            }

            // Populate the result arrays with data from the query
            foreach ($query as $record) {
                $monthName = Carbon::createFromFormat('Y-m', "{$record->year}-{$record->month}")->format('F Y');

                if ($record->status == 'pending') {
                    $result['pending'] = array_map(function ($item) use ($monthName, $record) {
                        if ($item['month'] == $monthName) {
                            $item['total_amount'] = round($record->total_amount, 2);
                        }
                        return $item;
                    }, $result['pending']);
                } elseif ($record->status == 'rejected') {
                    $result['rejected'] = array_map(function ($item) use ($monthName, $record) {
                        if ($item['month'] == $monthName) {
                            $item['total_amount'] = round($record->total_amount, 2);
                        }
                        return $item;
                    }, $result['rejected']);
                } elseif ($record->status == 'completed') {
                    $result['completed'] = array_map(function ($item) use ($monthName, $record) {
                        if ($item['month'] == $monthName) {
                            $item['total_amount'] = round($record->total_amount, 2);
                        }
                        return $item;
                    }, $result['completed']);
                }
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly quotation report fetched successfully.',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred while fetching data',
                'error' => $e->getMessage()
            ]);
        }
    }

    // product wise quotation
    public function getProductWiseQuotations(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Extract filters and pagination from request
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $searchProduct = $request->input('product');
            $orderBy = $request->input('order_by', 'product_name');  // name, quantity
            $orderType = $request->input('order', 'asc'); // asc or desc

            $query = DB::table('t_quotation_products')
                ->join('t_quotations', 't_quotations.id', '=', 't_quotation_products.quotation_id')
                ->join('t_products', 't_products.id', '=', 't_quotation_products.product_id')
                ->where('t_quotations.company_id', $companyId)
                ->select(
                    't_quotation_products.product_id',
                    't_products.name as product_name',
                    DB::raw('SUM(t_quotation_products.quantity) as total_quantity')
                )
                ->groupBy('t_quotation_products.product_id', 't_products.name');

            // Apply search filter for product name
            if (!empty($searchProduct)) {
                $query->where('t_products.name', 'like', "%$searchProduct%");
            }

            // Apply sorting
            if (in_array($orderBy, ['product_name', 'total_quantity'])) {
                $query->orderBy($orderBy === 'product_name' ? 't_products.name' : 'total_quantity', $orderType);
            }

            // Get total count before pagination
            $totalRecords = (clone $query)->count();

            // Apply pagination
            $products = $query->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Product-wise quotations fetched successfully.',
                'data' => [
                    'total_records' => $totalRecords,
                    'count' => $products->count(),
                    'records' => $products,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    // export product wise quotation
    public function exportProductWiseQuotations(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Extract filters and pagination from request
            $searchProduct = $request->input('product');
            $orderBy = $request->input('order_by', 'product_name');
            $orderType = $request->input('order', 'asc');

            $query = DB::table('t_quotation_products')
                ->join('t_quotations', 't_quotations.id', '=', 't_quotation_products.quotation_id')
                ->join('t_products', 't_products.id', '=', 't_quotation_products.product_id')
                ->where('t_quotations.company_id', $companyId)
                ->select(
                    't_quotation_products.product_id',
                    't_products.name as product_name',
                    DB::raw('SUM(t_quotation_products.quantity) as total_quantity')
                )
                ->groupBy('t_quotation_products.product_id', 't_products.name');

            // Apply search filter for product name
            if (!empty($searchProduct)) {
                $query->where('t_products.name', 'like', "%$searchProduct%");
            }

            // Apply sorting
            if (in_array($orderBy, ['product_name', 'total_quantity'])) {
                $query->orderBy($orderBy === 'product_name' ? 't_products.name' : 'total_quantity', $orderType);
            }

            $products = $query->get();

            // Generate Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Product Wise Quotations');

            // Set header row
            $sheet->fromArray(['Product ID', 'Product Name', 'Total Quantity'], NULL, 'A1');

            // Write data to Excel
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue("A{$row}", $product->product_id);
                $sheet->setCellValue("B{$row}", $product->product_name);
                $sheet->setCellValue("C{$row}", $product->total_quantity);
                $row++;
            }

            // Save file
            $fileName = 'product_wise_quotations_' . now()->format('Ymd_His') . '.xlsx';
            $directory = public_path('storage/export_product_wise_quotations');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $filePath = $directory . '/' . $fileName;
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($filePath);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Product-wise quotations exported successfully.',
                'download_url' => asset("storage/export_product_wise_quotations/{$fileName}")
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    // client wise quotation
    public function getClientWiseQuotations(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Extract filters from request
            $filterName = $request->input('name');
            $filterType = $request->input('type');
            $filterCategory = $request->input('category');
            $orderBy = $request->input('order_by', 'name'); // name, type, category
            $orderType = $request->input('order', 'asc'); // asc or desc
            $limit = $request->input('limit', 10);  // Pagination limit
            $offset = $request->input('offset', 0); // Pagination offset

            // Base query for client-wise quotation
            $query = QuotationsModel::join('t_clients', 't_clients.id', '=', 't_quotations.client_id')
                ->where('t_quotations.company_id', $companyId)
                ->select(
                    't_clients.name as client_name',
                    't_clients.type as client_type',
                    't_clients.category as client_category',
                    DB::raw('SUM(t_quotations.total) as total_amount'),
                    DB::raw('SUM(CASE WHEN t_quotations.status = "completed" THEN t_quotations.total ELSE 0 END) as completed_amount'),
                    DB::raw('SUM(CASE WHEN t_quotations.status = "pending" THEN t_quotations.total ELSE 0 END) as pending_amount'),
                    DB::raw('SUM(CASE WHEN t_quotations.status = "rejected" THEN t_quotations.total ELSE 0 END) as rejected_amount')
                )
                ->groupBy('t_quotations.client_id', 't_clients.name', 't_clients.type', 't_clients.category');

            // Apply filters
            if ($filterName) {
                $query->where('t_clients.name', 'like', '%' . $filterName . '%');
            }

            if ($filterType) {
                $query->where('t_clients.type', $filterType);
            }

            if ($filterCategory) {
                $query->where('t_clients.category', $filterCategory);
            }

            // Apply sorting
            switch ($orderBy) {
                case 'type':
                    $query->orderBy('t_clients.type', $orderType);
                    break;
                case 'category':
                    $query->orderBy('t_clients.category', $orderType);
                    break;
                case 'name':
                default:
                    $query->orderBy('t_clients.name', $orderType);
                    break;
            }

            // Get total count before pagination
            // $totalRecords = (clone $query)->count();
            $totalRecords = DB::table(DB::raw("({$query->toSql()}) as sub"))
                ->mergeBindings($query->getQuery()) // keep parameter bindings
                ->count();

            // Apply pagination
            $clients = $query->offset($offset)->limit($limit)->get();

            // Calculate completed percentage for each client
            foreach ($clients as $client) {
                $client->complete_percentage = ($client->total_amount > 0)
                    ? round(($client->completed_amount / $client->total_amount) * 100, 2)
                    : 0;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client-wise quotation data fetched successfully.',
                'data' => [
                    'total_records' => $totalRecords,   // Total records count without pagination
                    'count' => $clients->count(),       // Number of records for the current page
                    'limit' => $limit,
                    'offset' => $offset,
                    'records' => $clients,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // export client wise quotation
    public function exportClientWiseQuotations(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Extract filters and pagination from request
            $searchName = $request->input('name');
            $searchType = $request->input('type');
            $searchCategory = $request->input('category');
            $orderBy = $request->input('order_by', 'name');
            $orderType = $request->input('order', 'asc');

            // Base query for client-wise quotation
            $query = QuotationsModel::join('t_clients', 't_clients.id', '=', 't_quotations.client_id')
                ->where('t_quotations.company_id', $companyId)
                ->select(
                    't_clients.name as client_name',
                    't_clients.type as client_type',
                    't_clients.category as client_category',
                    DB::raw('SUM(t_quotations.total) as total_amount'),
                    DB::raw('SUM(CASE WHEN t_quotations.status = "completed" THEN t_quotations.total ELSE 0 END) as completed_amount'),
                    DB::raw('SUM(CASE WHEN t_quotations.status = "pending" THEN t_quotations.total ELSE 0 END) as pending_amount'),
                    DB::raw('SUM(CASE WHEN t_quotations.status = "rejected" THEN t_quotations.total ELSE 0 END) as rejected_amount')
                )
                ->groupBy('t_quotations.client_id', 't_clients.name', 't_clients.type', 't_clients.category');

            // Apply filters
            if (!empty($searchName)) {
                $query->where('t_clients.name', 'like', '%' . $searchName . '%');
            }
            if (!empty($searchType)) {
                $query->whereIn('t_clients.type', explode(',', $searchType));
            }
            if (!empty($searchCategory)) {
                $query->whereIn('t_clients.category', explode(',', $searchCategory));
            }

            // Apply sorting
            switch ($orderBy) {
                case 'type':
                    $query->orderBy('t_clients.type', $orderType);
                    break;
                case 'category':
                    $query->orderBy('t_clients.category', $orderType);
                    break;
                default:
                    $query->orderBy('t_clients.name', $orderType);
            }

            // Get all the data
            $clients = $query->get();

            // Generate Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Client Wise Quotations');

            // Set header row
            $sheet->fromArray([
                'Client Name', 'Client Type', 'Client Category', 'Total Amount', 
                'Completed Amount', 'Pending Amount', 'Rejected Amount', 'Completed Percentage'
            ], null, 'A1');

            $row = 2;
            foreach ($clients as $client) {
                $completedPercentage = $client->total_amount > 0
                    ? round(($client->completed_amount / $client->total_amount) * 100, 2)
                    : 0;

                $sheet->setCellValue("A{$row}", $client->client_name);
                $sheet->setCellValue("B{$row}", $client->client_type);
                $sheet->setCellValue("C{$row}", $client->client_category);
                $sheet->setCellValue("D{$row}", round($client->total_amount, 2));
                $sheet->setCellValue("E{$row}", round($client->completed_amount, 2));
                $sheet->setCellValue("F{$row}", round($client->pending_amount, 2));
                $sheet->setCellValue("G{$row}", round($client->rejected_amount, 2));
                $sheet->setCellValue("H{$row}", $completedPercentage);
                $row++;
            }

            // Define file name and path
            $fileName = 'client_wise_quotations_' . now()->format('Ymd_His') . '.xlsx';
            $filePath = storage_path('app/public/export_client_wise_quotations/' . $fileName);
            $directory = storage_path('app/public/export_client_wise_quotations');

            // Check if directory exists, if not create it
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save Excel file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filePath);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client-wise quotations exported successfully.',
                'download_url' => asset('storage/export_client_wise_quotations/' . $fileName)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // return type
    public function types()
    {
        $types = [
            'OIL',
            'SOIL',
            'QTMS',
            'Cash Discount',
            'TOD',
            'ACI'
        ];

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Types fetched successfully.',
            'data' => $types
        ], 200);
    }

    // report channel wise
    public function getMonthlyBillingSummary(Request $request)
    {
        try {
            $companyId        = Auth::user()->company_id;
            $financialYearId  = $request->input('financial_year_id');
            $groupFilter      = $request->input('group_id'); // can be numeric id or a string name

            // 1) Resolve FY
            $financialYear = $financialYearId
                ? FinancialYearModel::where('company_id', $companyId)->find($financialYearId)
                : FinancialYearModel::where('company_id', $companyId)->latest('id')->first();

            if (!$financialYear) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Financial year not found.'
                ], 404);
            }

            $startDate = \Carbon\Carbon::parse($financialYear->start_date)->startOfDay();
            $endDate   = \Carbon\Carbon::parse($financialYear->end_date)->endOfDay();

            // 2) Prepare safe group filter (resolve to a product column + value)
            $mustJoinProducts = false;
            $groupCol = null;
            $groupVal = null;

            if (!empty($groupFilter)) {
                $mustJoinProducts = true;

                if (\Schema::hasColumn('t_products', 'group_id')) {
                    // FK exists; allow numeric or text (if text, try map to id by name)
                    if (is_numeric($groupFilter)) {
                        $groupCol = 'p.group_id';
                        $groupVal = (int)$groupFilter;
                    } else {
                        // map name to id
                        $gTable = (new \App\Models\GroupModel)->getTable();
                        $gid = DB::table($gTable)->where('name', trim((string)$groupFilter))->value('id');
                        if ($gid) {
                            $groupCol = 'p.group_id';
                            $groupVal = (int)$gid;
                        } else {
                            // If no match, set impossible condition to yield empty aggregation gracefully
                            $groupCol = 'p.group_id';
                            $groupVal = -1;
                        }
                    }
                } elseif (\Schema::hasColumn('t_products', 'group')) {
                    // Text column
                    if (is_numeric($groupFilter)) {
                        // numeric passed ⇒ map id→name
                        $gTable = (new \App\Models\GroupModel)->getTable();
                        $gname = DB::table($gTable)->where('id', (int)$groupFilter)->value('name');
                        $groupCol = '`p`.`group`';
                        $groupVal = $gname ? trim($gname) : '__no_match__';
                    } else {
                        $groupCol = '`p`.`group`';
                        $groupVal = trim((string)$groupFilter);
                    }
                } elseif (\Schema::hasColumn('t_products', 'group_name')) {
                    // Text column group_name
                    if (is_numeric($groupFilter)) {
                        $gTable = (new \App\Models\GroupModel)->getTable();
                        $gname = DB::table($gTable)->where('id', (int)$groupFilter)->value('name');
                        $groupCol = 'p.group_name';
                        $groupVal = $gname ? trim($gname) : '__no_match__';
                    } else {
                        $groupCol = 'p.group_name';
                        $groupVal = trim((string)$groupFilter);
                    }
                }
            }

            // 3) Base query
            $query = DB::table('t_sales_invoice_products as sip')
                ->join('t_sales_invoice as si', 'sip.sales_invoice_id', '=', 'si.id')
                ->where('si.company_id', $companyId)
                ->whereBetween('si.sales_invoice_date', [$startDate, $endDate]);

            if ($mustJoinProducts) {
                $query->join('t_products as p', 'p.id', '=', 'sip.product_id');
                if ($groupCol && $groupVal !== null) {
                    // Use whereRaw for backticked `p`.`group`
                    if ($groupCol === '`p`.`group`') {
                        $query->whereRaw('TRIM(`p`.`group`) = ?', [$groupVal]);
                    } else {
                        $query->whereRaw('TRIM('.$groupCol.') = ?', [$groupVal]);
                    }
                }
            }

            // 4) Aggregate – total independent of channel; buckets from sip.channel (numeric or text)
            $rows = $query->selectRaw("
                    MONTH(si.sales_invoice_date) as m,
                    YEAR(si.sales_invoice_date)  as y,

                    SUM(sip.amount) as total,

                    SUM(
                        CASE
                            WHEN CAST(NULLIF(sip.channel, '') AS UNSIGNED) = 1
                            OR LOWER(TRIM(sip.channel)) IN ('standard','std')
                            THEN sip.amount ELSE 0 END
                    ) as standard_billing,

                    SUM(
                        CASE
                            WHEN CAST(NULLIF(sip.channel, '') AS UNSIGNED) = 2
                            OR LOWER(TRIM(sip.channel)) IN ('non_standard','non standard','ns','non-standard')
                            THEN sip.amount ELSE 0 END
                    ) as non_standard_billing,

                    SUM(
                        CASE
                            WHEN CAST(NULLIF(sip.channel, '') AS UNSIGNED) = 3
                            OR LOWER(TRIM(sip.channel)) IN ('customer_support','customer support','cs')
                            THEN sip.amount ELSE 0 END
                    ) as customer_support_billing
                ")
                ->groupBy(DB::raw('YEAR(si.sales_invoice_date), MONTH(si.sales_invoice_date)'))
                ->orderBy(DB::raw('YEAR(si.sales_invoice_date)'))
                ->orderBy(DB::raw('MONTH(si.sales_invoice_date)'))
                ->get();

            // 5) Put into a map: key = "YYYY-MM"
            $agg = [];
            foreach ($rows as $r) {
                $key = sprintf('%04d-%02d', (int)$r->y, (int)$r->m);
                $agg[$key] = [
                    'standard_billing'         => (float)$r->standard_billing,
                    'non_standard_billing'     => (float)$r->non_standard_billing,
                    'customer_support_billing' => (float)$r->customer_support_billing,
                    'total'                    => (float)$r->total,
                ];
            }

            // 6) Generate ALL months in FY with zero fill
            $cursor = $startDate->copy()->startOfMonth();
            $endEdge = $endDate->copy()->endOfMonth();

            $data = collect();
            while ($cursor <= $endEdge) {
                $key = $cursor->format('Y-m');
                $label = $cursor->format('F Y');

                $std = $agg[$key]['standard_billing']         ?? 0.0;
                $non = $agg[$key]['non_standard_billing']     ?? 0.0;
                $sup = $agg[$key]['customer_support_billing'] ?? 0.0;
                $tot = $agg[$key]['total']                    ?? 0.0;

                $data->push([
                    'month'                    => $label,
                    'standard_billing'         => round($std, 2),
                    'non_standard_billing'     => round($non, 2),
                    'customer_support_billing' => round($sup, 2),
                    'total'                    => round($tot, 2),
                ]);

                $cursor->addMonth();
            }

            // 7) Totals (over all months)
            $total = [
                'standard_billing'         => round($data->sum('standard_billing'), 2),
                'non_standard_billing'     => round($data->sum('non_standard_billing'), 2),
                'customer_support_billing' => round($data->sum('customer_support_billing'), 2),
                'total'                    => round($data->sum('total'), 2),
            ];

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Monthly billing summary fetched successfully.',
                'data'    => $data,
                'count'   => $data->count(),
                'total'   => $total
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to fetch summary.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // export channel wise report
    public function exportMonthlyBillingSummary(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;
            $financialYearId = $request->input('financial_year_id');
            $groupId = $request->input('group_id');

            // Get financial year
            $financialYear = $financialYearId
                ? FinancialYearModel::where('company_id', $companyId)->find($financialYearId)
                : FinancialYearModel::where('company_id', $companyId)->latest('id')->first();

            if (!$financialYear) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Financial year not found.'
                ], 404);
            }

            $startDate = $financialYear->start_date;
            $endDate = $financialYear->end_date;
            $yearSuffix = Carbon::parse($startDate)->format('Y');

            // Build base query
            $query = DB::table('t_sales_invoice_products as sip')
                ->join('t_sales_invoice as si', 'sip.sales_invoice_id', '=', 'si.id')
                ->where('si.company_id', $companyId)
                ->whereBetween('si.sales_invoice_date', [$startDate, $endDate]);

            if ($groupId) {
                $query->join('t_products as p', 'p.id', '=', 'sip.product_id')
                    ->where('p.group', $groupId);
            }

            $billing = $query->selectRaw("
                    MONTH(si.sales_invoice_date) as month,
                    SUM(CASE WHEN sip.channel = 1 THEN sip.amount ELSE 0 END) as standard_billing,
                    SUM(CASE WHEN sip.channel = 2 THEN sip.amount ELSE 0 END) as non_standard_billing,
                    SUM(CASE WHEN sip.channel = 3 THEN sip.amount ELSE 0 END) as customer_support_billing
                ")
                ->groupBy(DB::raw('MONTH(si.sales_invoice_date)'))
                ->orderBy(DB::raw('MONTH(si.sales_invoice_date)'))
                ->get();

            // Format data
            $rows = [];
            $totals = [
                'standard_billing' => 0,
                'non_standard_billing' => 0,
                'customer_support_billing' => 0,
                'total' => 0
            ];

            foreach ($billing as $row) {
                $monthName = Carbon::create()->month($row->month)->format('F') . " " . $yearSuffix;

                $standard = $this->fixZero($row->standard_billing);
                $nonStandard = $this->fixZero($row->non_standard_billing);
                $support = $this->fixZero($row->customer_support_billing);
                $monthlyTotal = $standard + $nonStandard + $support;

                $rows[] = [
                    'month' => $monthName,
                    'standard_billing' => $standard,
                    'non_standard_billing' => $nonStandard,
                    'customer_support_billing' => $support,
                    'total' => round($monthlyTotal, 2),
                ];

                // Accumulate totals
                $totals['standard_billing'] += $standard;
                $totals['non_standard_billing'] += $nonStandard;
                $totals['customer_support_billing'] += $support;
                $totals['total'] += $monthlyTotal;
            }

            // Create Excel Sheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header row
            $sheet->fromArray(['Month', 'Standard Billing', 'Non-standard Billing', 'Customer Support Billing', 'Total'], null, 'A1');

            // Data rows
            $rowIndex = 2; // Starting row for data
            foreach ($rows as $dataRow) {
                $colIndex = 1; // Column A
                foreach ($dataRow as $cellValue) {
                    if ($cellValue === '' || $cellValue === null) {
                        $cellValue = 0;
                    }
                    if (is_numeric($cellValue)) {
                        $sheet->setCellValueExplicitByColumnAndRow(
                            $colIndex, 
                            $rowIndex, 
                            $cellValue, 
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        );
                    } else {
                        $sheet->setCellValueExplicitByColumnAndRow(
                            $colIndex, 
                            $rowIndex, 
                            $cellValue, 
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    }
                    $colIndex++;
                }
                $rowIndex++;
            }

            // Totals row
            $totalRowIndex = count($rows) + 2;
            $totalsRowValues = [
                'Total',
                $this->fixZero(round($totals['standard_billing'], 2)),
                $this->fixZero(round($totals['non_standard_billing'], 2)),
                $this->fixZero(round($totals['customer_support_billing'], 2)),
                $this->fixZero(round($totals['total'], 2))
            ];

            $colIndex = 1;
            foreach ($totalsRowValues as $value) {
                $sheet->setCellValueExplicitByColumnAndRow(
                    $colIndex,
                    $totalRowIndex,
                    $value,
                    is_numeric($value) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
                $colIndex++;
            }


            // File generation
            $fileName = 'channel_wise_billing_report_' . now()->format('Ymd_His') . '.xlsx';
            $filePath = 'uploads/channel_wise_report/' . $fileName;

            // Ensure directory exists
            Storage::disk('public')->makeDirectory('uploads/channel_wise_report');

            // Save to storage
            $writer = new Xlsx($spreadsheet);
            $writer->save(storage_path('app/public/' . $filePath));

            // Return success response
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Excel report generated successfully.',
                'file_url' => asset('storage/' . $filePath),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to generate report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Private reusable method to fetch data
    private function fetchClientYearlySalesSummaryData(Request $request)
    {
        $companyId = Auth::user()->company_id;
        $search = $request->input('search');
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $financialYearIds = $request->input('financial_year_ids');
        if ($financialYearIds) {
            $yearIds = explode(',', $financialYearIds);
            $yearIds = array_filter(array_map('trim', $yearIds));

            if (count($yearIds) < 2) {
                throw new \InvalidArgumentException('Minimum two financial year IDs are required.');
            }
        } else {
            $yearIds = FinancialYearModel::where('company_id', $companyId)
                ->orderByDesc('id')
                ->take(3)
                ->pluck('id')
                ->toArray();
        }

        // Fetch years ordered descending by start_date
        $years = FinancialYearModel::whereIn('id', $yearIds)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date']);

        if ($years->isEmpty()) {
            throw new \Exception('No financial year data found.');
        }

        $yearLabels = $years->mapWithKeys(function ($year) {
            return [$year->id => substr($year->name, 2)]; // e.g., "21-22"
        });

        $clientsQuery = ClientsModel::where('company_id', $companyId);
        if (!empty($search)) {
            $clientsQuery->where('name', 'like', '%' . $search . '%');
        }
        $totalCount = $clientsQuery->count();

        $clients = $clientsQuery
            ->orderBy('name')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'name']);

        // Get last two years for percentage calculation (descending by start_date)
        $lastTwoYears = $years->take(2);
        $lastYearId = $lastTwoYears->first()->id ?? null;
        $prevYearId = $lastTwoYears->skip(1)->first()->id ?? null;

        $data = [];

        foreach ($clients as $client) {
            $row = ['name' => $client->name];
            $amounts = [];

            foreach ($years as $year) {
                $sales = DB::table('t_sales_invoice_products as sip')
                    ->join('t_sales_invoice as si', 'sip.sales_invoice_id', '=', 'si.id')
                    ->where('si.client_id', $client->id)
                    ->where('si.company_id', $companyId)
                    ->whereBetween('si.sales_invoice_date', [$year->start_date, $year->end_date])
                    ->select(
                        DB::raw('SUM(sip.amount) as total_amount'),
                        DB::raw('SUM(sip.profit) as total_profit')
                    )
                    ->first();

                $amount = $sales->total_amount ?? 0;
                $profit = $sales->total_profit ?? 0;

                $amounts[$year->id] = $amount;

                $label = $yearLabels[$year->id];

                $row["amount($label)"] = round($amount, 2);
                $row["profit($label)"] = round($profit, 2);
            }

            // Calculate percentage using last two years amount
            if ($lastYearId && $prevYearId && isset($amounts[$lastYearId], $amounts[$prevYearId]) && $amounts[$prevYearId] > 0) {
                $row['percentage(amount)'] = round(($amounts[$lastYearId] / $amounts[$prevYearId]) * 100, 2);
            } else {
                $row['percentage(amount)'] = 0;
            }

            $data[] = $row;
        }

        return [
            'data' => $data,
            'count' => count($data),
            'total_count' => $totalCount,
            'years' => $years,
        ];
    }

    // API to get stats compare
    public function getClientYearlySalesSummary(Request $request)
    {
        try {
            $result = $this->fetchClientYearlySalesSummaryData($request);
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client yearly sales summary fetched successfully.',
                'data' => $result['data'],
                'count' => $result['count'],
                'total_count' => $result['total_count'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch client summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // API to export stats compare
    public function exportClientWiseSummary(Request $request)
    {
        try {
            $result = $this->fetchClientYearlySalesSummaryData($request);
            $summaryData = $result['data'];
            $years = $result['years'];

            // Prepare year labels for headers
            $yearLabels = $years->mapWithKeys(function ($year) {
                $label = substr($year->name, 2);
                return [$year->id => $label];
            })->toArray();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Headers
            $header = ['Client Name'];
            foreach ($yearLabels as $label) {
                $header[] = "Amount ($label)";
                $header[] = "Profit ($label)";
            }
            $header[] = "Percentage";
            $sheet->fromArray($header, null, 'A1');

            // Data rows
            $rowNum = 2;
            foreach ($summaryData as $row) {
                $line = [$row['name']];
                foreach ($yearLabels as $label) {
                    $amountKey = "amount($label)";
                    $profitKey = "profit($label)";
                    $line[] = $this->fixZero($row[$amountKey] ?? 0);
                    $line[] = $this->fixZero($row[$profitKey] ?? 0);
                }
                $line[] = $this->fixZero($row['percentage(amount)'] ?? 0);
                // $sheet->fromArray($line, null, "A$rowNum");

                $colIndex = 1; // Column A is 1
                foreach ($line as $cellValue) {
                    if ($cellValue === '' || $cellValue === null) {
                        $cellValue = 0;
                    }
                    // Set cell value explicitly as numeric if numeric, else as string
                    if (is_numeric($cellValue)) {
                        $sheet->setCellValueExplicitByColumnAndRow($colIndex, $rowNum, $cellValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } else {
                        $sheet->setCellValueExplicitByColumnAndRow($colIndex, $rowNum, $cellValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    $colIndex++;
                }

                $rowNum++;
            }

            $fileName = 'client_summary_' . now()->format('Ymd_His') . '.xlsx';
            $filePath = 'uploads/stats_compare/' . $fileName;

            Storage::disk('public')->makeDirectory('uploads/stats_compare');

            $writer = new Xlsx($spreadsheet);
            $writer->save(storage_path('app/public/' . $filePath));

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Excel exported successfully.',
                'file_url' => asset('storage/' . $filePath),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to export client summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function fixZero($value) 
    {
        if ($value === null || $value === '' || $value === false) {
        return 0;
    }
        // Cast to float and round to 2 decimals explicitly
        return round((float) $value, 2);
    }

    /**
     * Unified product timeline with filters, sorting, rounding, and pagination.
     *
     * JSON Body (all optional):
     * {
     *   "start_date": "YYYY-MM-DD",                    // inclusive
     *   "end_date":   "YYYY-MM-DD",                    // inclusive
     *   "type": "sales_invoice" | ["sales_invoice",...], // if omitted/empty -> ALL types
     *   "search": "COM DOT 123/24",                    // smart search in masters & voucher_no
     *   "place": "Main Godown",                        // filter by godown/place
     *   "sort_by": "date|client|masters|price|discount|amount|profit|place",
     *   "sort_dir": "asc|desc",
     *   "page": 1,                                     // default 1
     *   "per_page": 50                                 // default 50, max 500
     * }
     */
    public function product_timeline(Request $request, $productId)
    {
        $companyId = auth()->user()->company_id;

        // ---------- Read JSON body (fallback-safe) ----------
        $body       = $request->json()->all() ?: [];
        $startDate  = $body['start_date'] ?? $request->input('start_date');
        $endDate    = $body['end_date']   ?? $request->input('end_date');
        $typeFilter = $body['type']       ?? $request->input('type');
        $search     = $body['search']     ?? $request->input('search');
        $placeQuery = $body['place']      ?? $request->input('place');
        $sortBy     = strtolower($body['sort_by'] ?? $request->input('sort_by', 'date'));
        $sortDir    = strtolower($body['sort_dir'] ?? $request->input('sort_dir', 'desc'));
        $sortDir    = in_array($sortDir, ['asc','desc'], true) ? $sortDir : 'desc';

        // ---------- Pagination inputs ----------
        $page    = max(1, (int) ($body['page'] ?? $request->input('page', 1)));
        $perPage = min(500, max(1, (int) ($body['per_page'] ?? $request->input('per_page', 50))));

        // ---------- Normalize type filter ----------
        // Rules: if type is omitted OR empty string OR empty array => all types
        $wantTypes = null; // null means "all types"
        if (isset($typeFilter)) {
            if (is_array($typeFilter)) {
                // Remove empty strings/nulls
                $clean = array_values(array_filter(array_map(function ($v) {
                    return is_string($v) ? trim(strtolower($v)) : null;
                }, $typeFilter), fn($v) => !empty($v)));

                $wantTypes = count($clean) ? array_values(array_unique($clean)) : null;
            } else {
                $s = trim((string)$typeFilter);
                if ($s !== '') {
                    $wantTypes = [strtolower($s)];
                } // else leave null => all types
            }
        }

        // ---------- Helpers: normalize & word-match ----------
        $normalize = function (?string $s): string {
            if (!$s) return '';
            $s = mb_strtolower($s, 'UTF-8');
            $s = preg_replace('/[^a-z0-9]+/u', ' ', $s); // strip punctuation to spaces
            $s = trim(preg_replace('/\s+/', ' ', $s));
            return $s;
        };
        $wordMatch = function (string $needle, string $haystack) use ($normalize): bool {
            $n = $normalize($needle); $h = $normalize($haystack);
            if ($n === '') return true;
            foreach (explode(' ', $n) as $w) {
                if ($w !== '' && mb_strpos($h, $w) === false) return false;
            }
            return true;
        };

        $result = [];

        // ---------------- SALES INVOICE ----------------
        if (is_null($wantTypes) || in_array('sales_invoice', $wantTypes, true)) {
            $rows = \App\Models\SalesInvoiceProductsModel::query()
                ->select([
                    't_sales_invoice_products.sales_invoice_id   as voucher_id',
                    't_sales_invoice.sales_invoice_no            as voucher_no',
                    't_sales_invoice.sales_invoice_date          as date',
                    DB::raw("'sales_invoice' as type"),
                    't_clients.name                              as masters',
                    't_sales_invoice_products.quantity           as qty',
                    DB::raw('NULL as in_stock'),
                    't_sales_invoice_products.price              as price',
                    't_sales_invoice_products.discount           as discount',
                    't_sales_invoice_products.amount             as amount',
                    't_sales_invoice_products.profit             as profit',
                    'g.name                                      as place',
                ])
                ->join('t_sales_invoice', 't_sales_invoice.id', '=', 't_sales_invoice_products.sales_invoice_id')
                ->leftJoin('t_clients', 't_clients.id', '=', 't_sales_invoice.client_id')
                ->leftJoin('t_godown as g', 'g.id', '=', 't_sales_invoice_products.godown')
                ->where('t_sales_invoice_products.product_id', $productId)
                ->where('t_sales_invoice.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_sales_invoice.sales_invoice_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_sales_invoice.sales_invoice_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'sales_invoice',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => $r->masters,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => null,
                    'price'      => is_null($r->price)    ? null : round((float)$r->price, 2),
                    'discount'   => is_null($r->discount) ? null : round((float)$r->discount, 2), // rounded
                    'amount'     => is_null($r->amount)   ? null : round((float)$r->amount, 2),
                    'profit'     => is_null($r->profit)   ? null : round((float)$r->profit, 2),
                    'place'      => $r->place,
                ];
            }
        }

        // ---------------- PURCHASE INVOICE ----------------
        if (is_null($wantTypes) || in_array('purchase_invoice', $wantTypes, true)) {
            $rows = \App\Models\PurchaseInvoiceProductsModel::query()
                ->select([
                    't_purchase_invoice_products.purchase_invoice_id as voucher_id',
                    't_purchase_invoice.purchase_invoice_no          as voucher_no',
                    't_purchase_invoice.purchase_invoice_date        as date',
                    DB::raw("'purchase_invoice' as type"),
                    't_suppliers.name                                as masters',
                    't_purchase_invoice_products.quantity            as qty',
                    't_purchase_invoice_products.quantity            as in_stock',
                    't_purchase_invoice_products.price               as price',
                    't_purchase_invoice_products.discount            as discount',
                    't_purchase_invoice_products.amount              as amount',
                    DB::raw('NULL as profit'),
                    'g.name                                          as place',
                ])
                ->join('t_purchase_invoice', 't_purchase_invoice.id', '=', 't_purchase_invoice_products.purchase_invoice_id')
                ->leftJoin('t_suppliers', 't_suppliers.id', '=', 't_purchase_invoice.supplier_id')
                ->leftJoin('t_godown as g', 'g.id', '=', 't_purchase_invoice_products.godown')
                ->where('t_purchase_invoice_products.product_id', $productId)
                ->where('t_purchase_invoice.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_purchase_invoice.purchase_invoice_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_purchase_invoice.purchase_invoice_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'purchase_invoice',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => $r->masters,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => (float)$r->in_stock,
                    'price'      => is_null($r->price)    ? null : round((float)$r->price, 2),
                    'discount'   => is_null($r->discount) ? null : round((float)$r->discount, 2), // rounded
                    'amount'     => is_null($r->amount)   ? null : round((float)$r->amount, 2),
                    'profit'     => null,
                    'place'      => $r->place,
                ];
            }
        }

        // ---------------- SALES ORDER ----------------
        if (is_null($wantTypes) || in_array('sales_order', $wantTypes, true)) {
            $rows = \App\Models\SalesOrderProductsModel::query()
                ->select([
                    't_sales_order_products.sales_order_id as voucher_id',
                    't_sales_order.sales_order_no          as voucher_no',
                    't_sales_order.sales_order_date        as date',
                    DB::raw("'sales_order' as type"),
                    't_clients.name                        as masters',
                    't_sales_order_products.quantity       as qty',
                    DB::raw('NULL as in_stock'),
                    't_sales_order_products.price          as price',
                    't_sales_order_products.discount       as discount',
                    't_sales_order_products.amount         as amount',
                    DB::raw('NULL as profit'),
                    DB::raw('NULL as place'),
                ])
                ->join('t_sales_order', 't_sales_order.id', '=', 't_sales_order_products.sales_order_id')
                ->leftJoin('t_clients', 't_clients.id', '=', 't_sales_order.client_id')
                ->where('t_sales_order_products.product_id', $productId)
                ->where('t_sales_order.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_sales_order.sales_order_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_sales_order.sales_order_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'sales_order',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => $r->masters,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => null,
                    'price'      => is_null($r->price)    ? null : round((float)$r->price, 2),
                    'discount'   => is_null($r->discount) ? null : round((float)$r->discount, 2), // rounded
                    'amount'     => is_null($r->amount)   ? null : round((float)$r->amount, 2),
                    'profit'     => null,
                    'place'      => null,
                ];
            }
        }

        // ---------------- PURCHASE ORDER ----------------
        if (is_null($wantTypes) || in_array('purchase_order', $wantTypes, true)) {
            $rows = \App\Models\PurchaseOrderProductsModel::query()
                ->select([
                    't_purchase_order_products.purchase_order_id as voucher_id',
                    't_purchase_order.purchase_order_no          as voucher_no',
                    't_purchase_order.purchase_order_date        as date',
                    DB::raw("'purchase_order' as type"),
                    't_suppliers.name                            as masters',
                    't_purchase_order_products.quantity          as qty',
                    't_purchase_order_products.quantity          as in_stock',
                    't_purchase_order_products.price             as price',
                    't_purchase_order_products.discount          as discount',
                    't_purchase_order_products.amount            as amount',
                    DB::raw('NULL as profit'),
                    DB::raw('NULL as place'),
                ])
                ->join('t_purchase_order', 't_purchase_order.id', '=', 't_purchase_order_products.purchase_order_id')
                ->leftJoin('t_suppliers', 't_suppliers.id', '=', 't_purchase_order.supplier_id')
                ->where('t_purchase_order_products.product_id', $productId)
                ->where('t_purchase_order.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_purchase_order.purchase_order_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_purchase_order.purchase_order_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'purchase_order',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => $r->masters,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => (float)$r->in_stock,
                    'price'      => is_null($r->price)    ? null : round((float)$r->price, 2),
                    'discount'   => is_null($r->discount) ? null : round((float)$r->discount, 2), // rounded
                    'amount'     => is_null($r->amount)   ? null : round((float)$r->amount, 2),
                    'profit'     => null,
                    'place'      => null,
                ];
            }
        }

        // ---------------- PURCHASE RETURN ----------------
        if (is_null($wantTypes) || in_array('purchase_return', $wantTypes, true)) {
            $rows = \App\Models\PurchaseReturnProductsModel::query()
                ->select([
                    't_purchase_return_products.purchase_return_id as voucher_id',
                    't_purchase_return.purchase_return_no          as voucher_no',
                    't_purchase_return.purchase_return_date        as date',
                    DB::raw("'purchase_return' as type"),
                    't_suppliers.name                              as masters',
                    't_purchase_return_products.quantity           as qty',
                    DB::raw('NULL as in_stock'),
                    DB::raw('NULL as price'),
                    DB::raw('NULL as discount'),
                    DB::raw('NULL as amount'),
                    DB::raw('NULL as profit'),
                    'g.name                                        as place',
                ])
                ->join('t_purchase_return', 't_purchase_return.id', '=', 't_purchase_return_products.purchase_return_id')
                ->leftJoin('t_suppliers', 't_suppliers.id', '=', 't_purchase_return.supplier_id')
                ->leftJoin('t_godown as g', 'g.id', '=', 't_purchase_return_products.godown')
                ->where('t_purchase_return_products.product_id', $productId)
                ->where('t_purchase_return.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_purchase_return.purchase_return_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_purchase_return.purchase_return_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'purchase_return',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => $r->masters,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => null,
                    'price'      => null,
                    'discount'   => null,
                    'amount'     => null,
                    'profit'     => null,
                    'place'      => $r->place,
                ];
            }
        }

        // ---------------- ASSEMBLY OPERATION ----------------
        if (is_null($wantTypes) || in_array('assembly_operation', $wantTypes, true)) {
            $rows = \App\Models\AssemblyOperationProductsModel::query()
                ->select([
                    't_assembly_operations_products.assembly_operations_id as voucher_id',
                    't_assembly_operations.assembly_operations_id          as voucher_no',
                    't_assembly_operations.assembly_operations_date        as date',
                    DB::raw("'assembly_operation' as type"),
                    DB::raw('NULL as masters'),
                    't_assembly_operations_products.quantity               as qty',
                    DB::raw('NULL as in_stock'),
                    't_assembly_operations_products.rate                   as price',
                    DB::raw('NULL as discount'),
                    't_assembly_operations_products.amount                 as amount',
                    DB::raw('NULL as profit'),
                    'g.name                                                as place',
                ])
                ->join('t_assembly_operations', 't_assembly_operations.id', '=', 't_assembly_operations_products.assembly_operations_id')
                ->leftJoin('t_godown as g', 'g.id', '=', 't_assembly_operations_products.godown')
                ->where('t_assembly_operations_products.product_id', $productId)
                ->where('t_assembly_operations.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_assembly_operations.assembly_operations_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_assembly_operations.assembly_operations_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'assembly_operation',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => null,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => null,
                    'price'      => is_null($r->price)  ? null : round((float)$r->price, 2),
                    'discount'   => null,
                    'amount'     => is_null($r->amount) ? null : round((float)$r->amount, 2),
                    'profit'     => null,
                    'place'      => $r->place,
                ];
            }
        }

        // ---------------- STOCK TRANSFER (no receiving_date) ----------------
        if (is_null($wantTypes) || in_array('stock_transfer', $wantTypes, true)) {
            $rows = \App\Models\StockTransferProductsModel::query()
                ->select([
                    't_stock_transfer_products.stock_transfer_id as voucher_id',
                    't_stock_transfer.transfer_id                as voucher_no',
                    't_stock_transfer.transfer_date              as date',
                    DB::raw("'stock_transfer' as type"),
                    DB::raw('NULL as masters'),
                    't_stock_transfer_products.quantity         as qty',
                    DB::raw('NULL as in_stock'),
                    DB::raw('NULL as price'),
                    DB::raw('NULL as discount'),
                    DB::raw('NULL as amount'),
                    DB::raw('NULL as profit'),
                    'gd_to.name                                  as place',
                ])
                ->join('t_stock_transfer', 't_stock_transfer.id', '=', 't_stock_transfer_products.stock_transfer_id')
                ->leftJoin('t_godown as gd_to', 'gd_to.id', '=', 't_stock_transfer.godown_to')
                ->where('t_stock_transfer_products.product_id', $productId)
                ->where('t_stock_transfer.company_id', $companyId)
                ->when($startDate, fn($q)=>$q->where('t_stock_transfer.transfer_date','>=',$startDate))
                ->when($endDate,   fn($q)=>$q->where('t_stock_transfer.transfer_date','<=',$endDate))
                ->get();

            foreach ($rows as $r) {
                $result[] = [
                    'type'       => 'stock_transfer',
                    'voucher_id' => $r->voucher_id,
                    'voucher_no' => $r->voucher_no,
                    'date'       => $r->date,
                    'masters'    => null,
                    'qty'        => (float)$r->qty,
                    'in_stock'   => null,
                    'price'      => null,
                    'discount'   => null,
                    'amount'     => null,
                    'profit'     => null,
                    'place'      => $r->place, // destination godown
                ];
            }
        }

        // ---------- In-memory filters: place + smart search ----------
        if ($placeQuery !== null && $placeQuery !== '') {
            $needle = $normalize($placeQuery);
            $result = array_values(array_filter($result, function($r) use ($needle, $normalize) {
                $h = $normalize($r['place'] ?? '');
                return $needle === '' || ($h !== '' && mb_strpos($h, $needle) !== false);
            }));
        }

        if ($search !== null && $search !== '') {
            $result = array_values(array_filter($result, function($r) use ($search, $wordMatch) {
                $hay1 = (string)($r['masters'] ?? '');
                $hay2 = (string)($r['voucher_no'] ?? '');
                return $wordMatch($search, $hay1) || $wordMatch($search, $hay2);
            }));
        }

        // ---------- Sorting (default date desc; nulls last) ----------
        $fieldMap = [
            'date'    => 'date',
            'client'  => 'masters',
            'masters' => 'masters',
            'price'   => 'price',
            'discount'=> 'discount',
            'amount'  => 'amount',
            'profit'  => 'profit',
            'place'   => 'place',
        ];
        $key = $fieldMap[$sortBy] ?? 'date';

        usort($result, function ($a, $b) use ($key, $sortDir) {
            $av = $a[$key] ?? null; $bv = $b[$key] ?? null;
            $aNull = is_null($av);  $bNull = is_null($bv);
            if ($aNull && $bNull) return 0;
            if ($aNull) return 1;
            if ($bNull) return -1;

            if (is_numeric($av) && is_numeric($bv)) $cmp = $av <=> $bv;
            else                                    $cmp = strcmp((string)$av,(string)$bv);

            return $sortDir === 'asc' ? $cmp : -$cmp;
        });

        // ---------- Pagination ----------
        $total  = count($result);
        $offset = ($page - 1) * $perPage;
        $paged  = array_slice($result, $offset, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $paged,
            'meta'    => [
                'filters' => [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'type'       => $wantTypes,     // null means "all"
                    'search'     => $search,
                    'place'      => $placeQuery,
                ],
                'sort' => [
                    'by'  => $key,
                    'dir' => $sortDir,
                ],
                'pagination' => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }



}
