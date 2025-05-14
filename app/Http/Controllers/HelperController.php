<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Models\ProductsModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SalesInvoiceModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderProductsModel;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductsModel;
use App\Models\GodownModel;
use App\Models\QuotationsModel;
use App\Models\FinancialYearModel;
use App\Models\ClientsModel;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use DB;
use Auth;

class HelperController extends Controller
{

    // dashboard
    public function dashboard(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Extract filters from request
            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);
            $filterGroup = $request->input('group');
            $filterCategory = $request->input('category');
            $filterSubCategory = $request->input('sub_category');
            $search = $request->input('search');

            $sortBy = $request->input('sort_by', 'name'); // name, group, category, sub_category, godown
            $sortOrder = $request->input('sort_order', 'asc'); // asc or desc

            // Base product query
            $productQuery = ProductsModel::with([
                'groupRelation:id,name',
                'categoryRelation:id,name',
                'subCategoryRelation:id,name'
            ])->where('company_id', $companyId);

            // Apply search filter (for both product name and alias)
            if (!empty($search)) {
                $productQuery->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('alias', 'like', '%' . $search . '%');
                });
            }

            // Apply filters (for group, category, sub_category)
            if ($filterGroup) {
                $groupIds = explode(',', $filterGroup);
                $productQuery->whereIn('group', $groupIds);
            }
            if ($filterCategory) {
                $catIds = explode(',', $filterCategory);
                $productQuery->whereIn('category', $catIds);
            }
            if ($filterSubCategory) {
                $subCatIds = explode(',', $filterSubCategory);
                $productQuery->whereIn('sub_category', $subCatIds);
            }

            switch ($sortBy) {
                case 'group':
                    $productQuery->orderBy('group', $sortOrder);
                    break;
                case 'category':
                    $productQuery->orderBy('category', $sortOrder);
                    break;
                case 'sub_category':
                    $productQuery->orderBy('sub_category', $sortOrder);
                    break;
                case 'alias':
                    $productQuery->orderBy('alias', $sortOrder);
                    break;
                default:
                    $productQuery->orderBy('name', $sortOrder); // default to name
            }

            // Get total count before pagination
            $totalProducts = $productQuery->count();

            // Apply pagination
            $products = $productQuery
                ->select('id', 'name', 'alias', 'group', 'category', 'sub_category', 'unit')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Fetch all godowns
            $godowns = GodownModel::where('company_id', $companyId)->select('id', 'name')->get();

            // Fetch stock data
            $closingStock = ClosingStockModel::where('company_id', $companyId)
                ->get()
                ->groupBy('product_id');

            // Pending purchase orders
            $pendingPurchase = PurchaseOrderModel::where('company_id', $companyId)
                ->where('status', 'pending')
                ->with('products')
                ->get()
                ->flatMap(fn ($order) => $order->products->pluck('product_id'))
                ->countBy();

            // Pending sales orders
            $pendingSales = SalesOrderModel::where('company_id', $companyId)
                ->where('status', 'pending')
                ->with('products')
                ->get()
                ->flatMap(fn ($order) => $order->products->pluck('product_id'))
                ->countBy();

            // Format product data
            $productsTransformed = $products->map(function ($product) use ($closingStock, $godowns, $pendingPurchase, $pendingSales) {
                $stockData = [];
                $totalQuantity = 0;

                // Index stock by godown_id for easy lookup
                $productStock = isset($closingStock[$product->id])
                    ? $closingStock[$product->id]->keyBy('godown_id')
                    : collect();

                foreach ($godowns as $godown) {
                    $qty = $productStock[$godown->id]->quantity ?? 0;
                    $stockData[] = [
                        'godown_id' => $godown->id,
                        'godown_name' => $godown->name,
                        'quantity' => $qty,
                    ];
                    $totalQuantity += $qty;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'alias' => $product->alias,
                    'group' => optional($product->groupRelation)->name,
                    'category' => optional($product->categoryRelation)->name,
                    'sub_category' => optional($product->subCategoryRelation)->name,
                    'unit' => $product->unit,
                    'stock_by_godown' => $stockData,
                    'total_quantity' => $totalQuantity,
                    'stock_value' => 0,
                    'pending_po' => $pendingPurchase[$product->id] ?? 0,
                    'pending_so' => $pendingSales[$product->id] ?? 0,
                ];
            });

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => "Fetched successfully",
                'data' => [
                    'count' => $products->count(),          
                    'total_records' => $totalProducts,    
                    'limit' => $limit,
                    'offset' => $offset,
                    'records' => $productsTransformed,
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
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
        $companyId = auth()->user()->company_id;
    
        // Get start and end date from request
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();

        // Get all products with group, category, sub-category
        $products = ProductsModel::with([
            'groupRelation:id,name',
            'categoryRelation:id,name',
            'subCategoryRelation:id,name'
        ])->where('company_id', $companyId)->get()->keyBy('id');

        // Fetch purchase data
        $purchaseData = PurchaseInvoiceProductsModel::with('purchaseInvoice')
            ->whereHas('purchaseInvoice', function ($q) use ($companyId, $startDate, $endDate) {
                $q->where('company_id', $companyId)
                ->whereBetween('purchase_invoice_date', [$startDate, $endDate]);
            })->get();

        // Fetch sales data
        $salesData = SalesInvoiceProductsModel::with('salesInvoice')
            ->whereHas('salesInvoice', function ($q) use ($companyId, $startDate, $endDate) {
                $q->where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$startDate, $endDate]);
            })->get();

        // Build structure
        $result = [];

        foreach ($products as $productId => $product) {
            $groupId = $product->group;
            $categoryId = $product->category;
            $subCategoryId = $product->sub_category;

            $groupName = $product->groupRelation->name ?? 'Unknown';
            $categoryName = $product->categoryRelation->name ?? 'Unknown';
            $subCategoryName = $product->subCategoryRelation->name ?? 'Unknown';

            // Initialize
            if (!isset($result[$groupId])) {
                $result[$groupId] = [
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                    'total_sales' => 0,
                    'total_purchase' => 0,
                    'total_profit' => 0,
                    'categories' => []
                ];
            }

            if (!isset($result[$groupId]['categories'][$categoryId])) {
                $result[$groupId]['categories'][$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'total_sales' => 0,
                    'total_purchase' => 0,
                    'total_profit' => 0,
                    'sub_categories' => []
                ];
            }

            if (!isset($result[$groupId]['categories'][$categoryId]['sub_categories'][$subCategoryId])) {
                $result[$groupId]['categories'][$categoryId]['sub_categories'][$subCategoryId] = [
                    'sub_category_id' => $subCategoryId,
                    'sub_category_name' => $subCategoryName,
                    'total_sales' => 0,
                    'total_purchase' => 0,
                    'total_profit' => 0
                ];
            }
        }

        // Loop and add purchase values
        foreach ($purchaseData as $item) {
            $product = $products[$item->product_id] ?? null;
            if (!$product) continue;

            $groupId = $product->group;
            $categoryId = $product->category;
            $subCategoryId = $product->sub_category;

            $amount = $item->amount ?? 0;

            $result[$groupId]['total_purchase'] += $amount;
            $result[$groupId]['categories'][$categoryId]['total_purchase'] += $amount;
            $result[$groupId]['categories'][$categoryId]['sub_categories'][$subCategoryId]['total_purchase'] += $amount;
        }

        // Loop and add sales values
        foreach ($salesData as $item) {
            $product = $products[$item->product_id] ?? null;
            if (!$product) continue;

            $groupId = $product->group;
            $categoryId = $product->category;
            $subCategoryId = $product->sub_category;

            $amount = $item->amount ?? 0;
            $profit = $item->profit ?? 0;

            $result[$groupId]['total_sales'] += $amount;
            $result[$groupId]['total_profit'] += $profit;

            $result[$groupId]['categories'][$categoryId]['total_sales'] += $amount;
            $result[$groupId]['categories'][$categoryId]['total_profit'] += $profit;

            $result[$groupId]['categories'][$categoryId]['sub_categories'][$subCategoryId]['total_sales'] += $amount;
            $result[$groupId]['categories'][$categoryId]['sub_categories'][$subCategoryId]['total_profit'] += $profit;
        }

        // Re-index data for clean structure
        $final = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => array_values(array_map(function ($group) {
                $group['categories'] = array_values(array_map(function ($cat) {
                    $cat['sub_categories'] = array_values($cat['sub_categories']);
                    return $cat;
                }, $group['categories']));
                return $group;
            }, $result))
        ];
    
        // return response()->json([
        //     'success' => true,
        //     'data' => $result
        // ]);

        return response()->json([
            'code' => 200,
            'success' => true,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => array_values(array_map(function ($group) {
                $group['categories'] = array_values(array_map(function ($cat) {
                    $cat['sub_categories'] = array_values($cat['sub_categories']);
                    // Round off category totals
                    $cat['total_sales'] = round($cat['total_sales']);
                    $cat['total_purchase'] = round($cat['total_purchase']);
                    $cat['total_profit'] = round($cat['total_profit']);
                    foreach ($cat['sub_categories'] as &$sub) {
                        $sub['total_sales'] = round($sub['total_sales']);
                        $sub['total_purchase'] = round($sub['total_purchase']);
                        $sub['total_profit'] = round($sub['total_profit']);
                    }
                    return $cat;
                }, $group['categories']));
        
                // Round off group totals
                $group['total_sales'] = round($group['total_sales']);
                $group['total_purchase'] = round($group['total_purchase']);
                $group['total_profit'] = round($group['total_profit']);
        
                return $group;
            }, $result))
        ]);
        
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

    // public function getMonthlyPurchaseSalesSummary(Request $request)
    // {
    //     try {
    //         $companyId = auth()->user()->company_id;

    //         $startDate = Carbon::parse($request->start_date)->startOfMonth();
    //         $endDate = Carbon::parse($request->end_date)->endOfMonth();

    //         // 1️⃣ Purchase Stats
    //         $purchaseStats = DB::table('t_purchase_invoice as pi')
    //             ->join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
    //             ->where('pi.company_id', $companyId)
    //             ->whereBetween('pi.purchase_invoice_date', [$startDate, $endDate])
    //             ->selectRaw("
    //                 DATE_FORMAT(pi.purchase_invoice_date, '%M %Y') as month,
    //                 DATE_FORMAT(pi.purchase_invoice_date, '%Y-%m') as month_key,
    //                 SUM(pip.amount) as purchase_total,
    //                 COUNT(DISTINCT pi.id) as purchase_invoice_count
    //             ")
    //             ->groupBy('month', 'month_key')
    //             ->orderBy('month_key')
    //             ->get()
    //             ->keyBy('month_key');

    //         // 2️⃣ Sales Stats
    //         $salesStats = DB::table('t_sales_invoice as si')
    //             ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
    //             ->where('si.company_id', $companyId)
    //             ->whereBetween('si.sales_invoice_date', [$startDate, $endDate])
    //             ->selectRaw("
    //                 DATE_FORMAT(si.sales_invoice_date, '%M %Y') as month,
    //                 DATE_FORMAT(si.sales_invoice_date, '%Y-%m') as month_key,
    //                 SUM(sip.amount) as sales_total,
    //                 COUNT(DISTINCT si.id) as sales_invoice_count
    //             ")
    //             ->groupBy('month', 'month_key')
    //             ->orderBy('month_key')
    //             ->get()
    //             ->keyBy('month_key');

    //         // 3️⃣ Merge & Format Final Response
    //         $months = [];
    //         $purchaseTotals = [];
    //         $salesTotals = [];
    //         $purchaseInvoiceCounts = [];
    //         $salesInvoiceCounts = [];

    //         $period = CarbonPeriod::create($startDate, '1 month', $endDate);
    //         foreach ($period as $date) {
    //             $key = $date->format('Y-m');
    //             $label = $date->format('F Y');

    //             $months[] = $label;
    //             $purchaseTotals[] = round(optional($purchaseStats->get($key))->purchase_total ?? 0, 2);
    //             $salesTotals[] = round(optional($salesStats->get($key))->sales_total ?? 0, 2);
    //             $purchaseInvoiceCounts[] = optional($purchaseStats->get($key))->purchase_invoice_count ?? 0;
    //             $salesInvoiceCounts[] = optional($salesStats->get($key))->sales_invoice_count ?? 0;
    //         }

    //         return response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Monthly Purchase vs Sales summary fetched successfully!',
    //             'data' => [
    //                 'month' => $months,
    //                 'purchase_total' => $purchaseTotals,
    //                 'sales_total' => $salesTotals,
    //                 'purchase_invoice_count' => $purchaseInvoiceCounts,
    //                 'sales_invoice_count' => $salesInvoiceCounts,
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'code' => 500,
    //             'success' => false,
    //             'message' => 'Error occurred while processing data',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function getMonthlyPurchaseSalesSummary(Request $request)
    // {
    //     try {
    //         $companyId = auth()->user()->company_id;

    //         $startDate = Carbon::parse($request->start_date)->startOfMonth();
    //         $endDate = Carbon::parse($request->end_date)->endOfMonth();

    //         // 1️⃣ Purchase Stats
    //         $purchaseStats = DB::table('t_purchase_invoice as pi')
    //             ->join('t_purchase_invoice_products as pip', 'pi.id', '=', 'pip.purchase_invoice_id')
    //             ->where('pi.company_id', $companyId)
    //             ->whereBetween('pi.purchase_invoice_date', [$startDate, $endDate])
    //             ->selectRaw("
    //                 DATE_FORMAT(pi.purchase_invoice_date, '%M %Y') as month,
    //                 DATE_FORMAT(pi.purchase_invoice_date, '%Y-%m') as month_key,
    //                 SUM(pip.amount) as purchase_total,
    //                 COUNT(DISTINCT pi.id) as purchase_invoice_count
    //             ")
    //             ->groupBy('month', 'month_key')
    //             ->orderBy('month_key')
    //             ->get()
    //             ->keyBy('month_key');

    //         // 2️⃣ Sales Stats
    //         $salesStats = DB::table('t_sales_invoice as si')
    //             ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
    //             ->where('si.company_id', $companyId)
    //             ->whereBetween('si.sales_invoice_date', [$startDate, $endDate])
    //             ->selectRaw("
    //                 DATE_FORMAT(si.sales_invoice_date, '%M %Y') as month,
    //                 DATE_FORMAT(si.sales_invoice_date, '%Y-%m') as month_key,
    //                 SUM(sip.amount) as sales_total,
    //                 COUNT(DISTINCT si.id) as sales_invoice_count
    //             ")
    //             ->groupBy('month', 'month_key')
    //             ->orderBy('month_key')
    //             ->get()
    //             ->keyBy('month_key');

    //         // 3️⃣ Merge & Format Final Response
    //         $months = [];
    //         $purchaseTotals = [];
    //         $salesTotals = [];
    //         $purchaseInvoiceCounts = [];
    //         $salesInvoiceCounts = [];

    //         $period = CarbonPeriod::create($startDate, '1 month', $endDate);

    //         foreach ($period as $date) {
    //             $key = $date->format('Y-m');
    //             $label = $date->format('F Y');

    //             $months[] = $label;

    //             $purchaseTotals[] = round($purchaseStats[$key]->purchase_total ?? 0, 2);
    //             $salesTotals[] = round($salesStats[$key]->sales_total ?? 0, 2);

    //             $purchaseInvoiceCounts[] = $purchaseStats[$key]->purchase_invoice_count ?? 0;
    //             $salesInvoiceCounts[] = $salesStats[$key]->sales_invoice_count ?? 0;
    //         }

    //         return response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Monthly Purchase vs Sales summary fetched successfully!',
    //             'data' => [
    //                 'month' => $months,
    //                 'purchase_total' => $purchaseTotals,
    //                 'sales_total' => $salesTotals,
    //                 'purchase_invoice_count' => $purchaseInvoiceCounts,
    //                 'sales_invoice_count' => $salesInvoiceCounts,
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'code' => 500,
    //             'success' => false,
    //             'message' => 'Error occurred while processing data',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    // product wise profit
    public function getProductWiseSalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Get date range
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

             // Step 1: Filtered sales invoice IDs for the company and date range
            $salesInvoiceIds = SalesInvoiceModel::where('company_id', $companyId)
            ->whereBetween('sales_invoice_date', [$startDate, $endDate])
            ->pluck('id');

            // Step 2: Fetch product-wise sales with totals
            $products = SalesInvoiceProductsModel::select('product_id', 'product_name')
            ->where('company_id', $companyId)
            ->whereIn('sales_invoice_id', $salesInvoiceIds)
            ->selectRaw('SUM(amount) as total_amount, SUM(profit) as total_profit')
            ->groupBy('product_id', 'product_name')
            ->get()
            ->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'product_name' => $product->product_name,
                    'total_amount' => round($product->total_amount, 2),
                    'total_profit' => round($product->total_profit, 2),
                ];
            });

            // Step 3: Return response
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Product-wise sales summary fetched successfully!',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            // Error handling
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching product-wise sales summary.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //client wise profit
    public function getClientWiseSalesSummary(Request $request)
    {
        try {
            $companyId = auth()->user()->company_id;

            // Step 1: Parse dates from request or default to full range
            $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::minValue();
            $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

            // Step 2: Get all sales invoices for company & date range, with products
            $invoices = SalesInvoiceModel::with('products:id,sales_invoice_id,profit,amount', 
                'client:id,name')
                ->select('id', 'client_id')
                ->where('company_id', $companyId)
                ->whereBetween('sales_invoice_date', [$startDate, $endDate])
                ->get();

            // Step 3: Aggregate totals by client_id
            $result = [];

            foreach ($invoices as $invoice) {
                $clientId = $invoice->client_id;
                if (!$clientId) continue;

                $profitSum = $invoice->products->sum('profit');
                $amountSum = $invoice->products->sum('amount');
                $clientName = $invoice->client->name ?? 'Unknown';

                if (!isset($result[$clientId])) {
                    $result[$clientId] = [
                        'client_id' => $clientId,
                        'client_name' => $clientName,
                        'total_profit' => 0,
                        'total_amount' => 0
                    ];
                }

                $result[$clientId]['total_profit'] += $profitSum;
                $result[$clientId]['total_amount'] += $amountSum;
            }

            // Round values to 2 decimal places
            $finalResult = array_map(function ($item) {
                return [
                    'client_id' => $item['client_id'],
                    'client_name' => $item['client_name'],
                    'total_profit' => round($item['total_profit'], 2),
                    'total_amount' => round($item['total_amount'], 2)
                ];
            }, $result);

            // Step 3: Return the result
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client wise profit fetched successfully!',
                'data' => array_values($finalResult)
            ]);

        } catch (\Exception $e) {
            // Catch unexpected errors
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while calculating client-wise sales summary.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // last 3 years fy wise product wise profit
    public function getProductWiseYearlySalesSummary()
    {
        try {
            $companyId = auth()->user()->company_id;
            $now = Carbon::now();

            $years = [];

            // Get current and last 2 years
            for ($i = 0; $i < 3; $i++) {
                $start = Carbon::create($now->year - $i, 4, 1)->startOfDay(); // Financial year starts April 1
                $end = Carbon::create($now->year - $i + 1, 3, 31)->endOfDay(); // Ends March 31 next year

                $label = $start->format('Y') . '-' . $end->format('Y');

                $salesInvoiceIds = SalesInvoiceModel::where('company_id', $companyId)
                    ->whereBetween('sales_invoice_date', [$start, $end])
                    ->pluck('id');

                $yearlyData = SalesInvoiceProductsModel::select('product_id', 'product_name')
                    ->where('company_id', $companyId)
                    ->whereIn('sales_invoice_id', $salesInvoiceIds)
                    ->selectRaw('SUM(amount) as total_amount, SUM(profit) as total_profit')
                    ->groupBy('product_id', 'product_name')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'total_amount' => round($item->total_amount, 2),
                            'total_profit' => round($item->total_profit, 2),
                        ];
                    });

                $years[$label] = $yearlyData;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Year-wise product sales summary fetched successfully!',
                'data' => $years
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching year-wise summary.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // last 3 years fy wise product wise profit
    public function getClientWiseYearlySalesSummary()
    {
        try {
            $companyId = auth()->user()->company_id;

            $currentYear = Carbon::now()->year;

            $result = [];

            for ($i = 0; $i < 3; $i++) {
                $fyStartYear = $currentYear - $i;
                $fyEndYear = $fyStartYear + 1;
                $fyKey = "{$fyStartYear}-{$fyEndYear}";

                $startDate = Carbon::create($fyStartYear, 4, 1)->startOfDay(); // April 1st
                $endDate = Carbon::create($fyEndYear, 3, 31)->endOfDay();      // March 31st

                // Get all sales invoices for the year with products
                $invoices = SalesInvoiceModel::with('products:id,sales_invoice_id,profit,amount',
                    'client:id,name')
                    ->select('id', 'client_id')
                    ->where('company_id', $companyId)
                    ->whereBetween('sales_invoice_date', [$startDate, $endDate])
                    ->get();

                $yearlyData = [];

                foreach ($invoices as $invoice) {
                    $clientId = $invoice->client_id;
                    if (!$clientId) continue;

                    $profitSum = $invoice->products->sum('profit');
                    $amountSum = $invoice->products->sum('amount');
                    $clientName = $invoice->client->name ?? 'Unknown';

                    if (!isset($yearlyData[$clientId])) {
                        $yearlyData[$clientId] = [
                            'client_id' => $clientId,
                            'client_name' => $clientName,
                            'year' => $fyKey,
                            'total_profit' => 0,
                            'total_amount' => 0
                        ];
                    }

                    $yearlyData[$clientId]['total_profit'] += $profitSum;
                    $yearlyData[$clientId]['total_amount'] += $amountSum;
                }

                // Round values
                $finalYearData = array_map(function ($item) {
                    return [
                        'client_id' => $item['client_id'],
                        'client_name' => $item['client_name'],
                        'year' => $item['year'],
                        'total_profit' => round($item['total_profit'], 2),
                        'total_amount' => round($item['total_amount'], 2)
                    ];
                }, $yearlyData);

                $result[$fyKey] = array_values($finalYearData); // keep FY key like "2025-2026"
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client-wise yearly sales summary fetched successfully!',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while fetching client-wise yearly summary.',
                'error' => $e->getMessage()
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
            $invoiceCounts = [];

            // Generate months between start and end dates
            $current = $startDate->copy();

            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();
                $monthName = $current->format('F Y'); // e.g., "January 2022"

                // Query sales for this month
                $salesStats = DB::table('t_sales_invoice as si')
                    ->join('t_sales_invoice_products as sip', 'si.id', '=', 'sip.sales_invoice_id')
                    ->where('si.company_id', $companyId)
                    ->whereBetween('si.sales_invoice_date', [$monthStart, $monthEnd])
                    ->selectRaw('SUM(sip.amount) as total, COUNT(DISTINCT si.id) as invoice_count')
                    ->first();

                // If no data found for this month, default to 0
                $salesTotal = $salesStats->total ?? 0;
                $invoiceCount = $salesStats->invoice_count ?? 0;

                // Populate results for the current month
                $months[] = $monthName;
                $salesTotals[] = round($salesTotal); // If no sales, default to 0
                $invoiceCounts[] = $invoiceCount;

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
                    'invoice_count' => $invoiceCounts,
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

            // Initialize variables to calculate cumulative sales
            $cumulativeSales = 0;
            $salesWithCumulative = [];

            // Iterate through sales data and calculate cumulative sales
            foreach ($sales as $sale) {
                // Add the current month's sales to the cumulative total
                $cumulativeSales += $sale->monthly_sales_amount;

                // Store the month name and cumulative total
                $monthName = Carbon::createFromFormat('m', $sale->month)->format('F Y');
                $salesWithCumulative[] = [
                    'month' => $monthName,
                    'monthly_sales_amount' => round($sale->monthly_sales_amount),
                    'cumulative_sales_amount' => round($cumulativeSales),
                ];
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly cumulative sales fetched successfully.',
                'data' => $salesWithCumulative
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
            $companyId = Auth::user()->company_id;
            $financialYearId = $request->input('financial_year_id');
            $groupId = $request->input('group_id');

            // 1. Get financial year range
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

            // 2. Build base query
            $query = DB::table('t_sales_invoice_products as sip')
                ->join('t_sales_invoice as si', 'sip.sales_invoice_id', '=', 'si.id')
                ->where('si.company_id', $companyId)
                ->whereBetween('si.sales_invoice_date', [$startDate, $endDate]);

            // 3. Optional group filter
            if ($groupId) {
                $query->join('t_products as p', 'p.id', '=', 'sip.product_id')
                    ->where('p.group', $groupId);
            }

            // 4. Billing aggregation
            $billing = $query->selectRaw("
                    MONTH(si.sales_invoice_date) as month,
                    SUM(CASE WHEN sip.channel = 1 THEN sip.amount ELSE 0 END) as standard_billing,
                    SUM(CASE WHEN sip.channel = 2 THEN sip.amount ELSE 0 END) as non_standard_billing,
                    SUM(CASE WHEN sip.channel = 3 THEN sip.amount ELSE 0 END) as customer_support_billing,
                    SUM(CASE WHEN sip.channel IN (1, 2, 3) THEN sip.amount ELSE 0 END) as total
                ")
                ->groupBy(DB::raw('MONTH(si.sales_invoice_date)'))
                ->orderBy(DB::raw('MONTH(si.sales_invoice_date)'))
                ->get();

            // 5. Format rows
            $data = $billing->map(function ($row) use ($yearSuffix) {
                return [
                    'month' => Carbon::create()->month($row->month)->format('F') . " " . $yearSuffix,
                    'standard_billing' => round($row->standard_billing, 2),
                    'non_standard_billing' => round($row->non_standard_billing, 2),
                    'customer_support_billing' => round($row->customer_support_billing, 2),
                    'total' => round($row->total, 2),
                ];
            });

            // 6. Totals
            $total = [
                'standard_billing' => round($billing->sum('standard_billing'), 2),
                'non_standard_billing' => round($billing->sum('non_standard_billing'), 2),
                'customer_support_billing' => round($billing->sum('customer_support_billing'), 2),
                'total' => round($billing->sum('total'), 2),
            ];

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Monthly billing summary fetched successfully.',
                'data' => $data,
                'count' => $data->count(),
                'total' => $total
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch summary.',
                'error' => $e->getMessage()
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

                $standard = is_numeric($row->standard_billing) ? $row->standard_billing : 0;
                $nonStandard = is_numeric($row->non_standard_billing) ? $row->non_standard_billing : 0;
                $support = is_numeric($row->customer_support_billing) ? $row->customer_support_billing : 0;
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
            $sheet->fromArray($rows, null, 'A2');

            // Totals row
            $totalRowIndex = count($rows) + 2;
            $sheet->setCellValue("A{$totalRowIndex}", 'Total');
            $sheet->setCellValue("B{$totalRowIndex}", round($totals['standard_billing'], 2));
            $sheet->setCellValue("C{$totalRowIndex}", round($totals['non_standard_billing'], 2));
            $sheet->setCellValue("D{$totalRowIndex}", round($totals['customer_support_billing'], 2));
            $sheet->setCellValue("E{$totalRowIndex}", round($totals['total'], 2));

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

    // stats compare
    public function getClientYearlySalesSummary(Request $request)
    {
        try {
            $companyId = Auth::user()->company_id;
            $search = $request->input('search');
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $financialYearIds = $request->input('financial_year_ids');
            $yearIds = $financialYearIds 
                ? explode(',', $financialYearIds) 
                : FinancialYearModel::where('company_id', $companyId)
                    ->orderByDesc('id')
                    ->take(3)
                    ->pluck('id')
                    ->toArray();

            $years = FinancialYearModel::whereIn('id', $yearIds)
                ->orderBy('start_date')
                ->get(['id', 'name', 'start_date', 'end_date']);

            if ($years->isEmpty()) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'No financial year data found.'
                ], 404);
            }

            // Get clients
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

            $data = [];

            foreach ($clients as $client) {
                $row = ['name' => $client->name];
                $totalAmount = 0;

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

                    $totalAmount += $amount;

                    $fyLabel = substr($year->name, 2); // e.g., "21-22"
                    $row["amount($fyLabel)"] = round($amount, 2);
                    $row["profit($fyLabel)"] = round($profit, 2);
                }

                $row["percentage(amount)"] = 0; // Placeholder
                $data[] = $row;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Client yearly sales summary fetched successfully.',
                'data' => $data,
                'count' => count($data),
                'total_count' => $totalCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch client summary.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // export stats compare
    public function exportClientWiseSummary(Request $request)
    {
        $summaryData = $this->getClientYearlySalesSummary($request)->getData(true)['data'];
        $yearIds = collect(explode(',', $request->input('financial_year_ids', '')))
                    ->filter()
                    ->map(fn($id) => (int) trim($id))
                    ->toArray();
        $companyId = Auth::user()->company_id;

        if (empty($yearIds)) {
            $yearIds = FinancialYearModel::where('company_id', $companyId)
                ->orderByDesc('id')->limit(3)->pluck('id')->toArray();
        }

        $yearLabels = FinancialYearModel::whereIn('id', $yearIds)->pluck('name')->toArray();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $header = ['Client Name'];
        foreach ($yearLabels as $y) {
            $header[] = "Amount ($y)";
            $header[] = "Profit ($y)";
        }
        $header[] = "Percentage";
        $sheet->fromArray($header, null, 'A1');

        // Data
        $rowNum = 2;
        foreach ($summaryData as $row) {
            $line = [$row['name']];
            foreach ($yearLabels as $y) {
                $line[] = $row["amount_$y"] ?? 0;
                $line[] = $row["profit_$y"] ?? 0;
            }
            $line[] = 0;
            $sheet->fromArray($line, null, "A$rowNum");
            $rowNum++;
        }

        $fileName = 'client_summary_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'uploads/client_summary/' . $fileName;

        Storage::disk('public')->makeDirectory('uploads/client_summary');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save(storage_path('app/public/' . $filePath));

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Excel exported successfully.',
            'file_url' => asset('storage/' . $filePath),
        ]);
    }

}
