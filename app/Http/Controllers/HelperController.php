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
            $filterProduct = $request->input('product');
            $filterGroup = $request->input('group');
            $filterCategory = $request->input('category');
            $filterSubCategory = $request->input('sub_category');
            $filterAlias = $request->input('alias');

            // Base product query
            $productQuery = ProductsModel::with([
                'groupRelation:id,name',
                'categoryRelation:id,name'
            ])->where('company_id', $companyId);

            // Apply filters
            if ($filterProduct) {
                $productQuery->where('name', 'like', '%' . $filterProduct . '%');
            }
            if ($filterAlias) {
                $productQuery->where('alias', 'like', '%' . $filterAlias . '%');
            }
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

            // Get total count before pagination
            $totalProducts = $productQuery->count();

            // Apply pagination
            $products = $productQuery
                ->select('id', 'name', 'alias', 'group', 'category')
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
                    'stock_by_godown' => $stockData,
                    'total_quantity' => $totalQuantity,
                    'stock_value' => 0,
                    'pending_purchase' => $pendingPurchase[$product->id] ?? 0,
                    'pending_sales' => $pendingSales[$product->id] ?? 0,
                ];
            });

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => "Fetched successfully",
                'data' => [
                    'total_products' => $totalProducts,
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

}
