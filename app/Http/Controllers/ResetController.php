<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpeningStockModel;
use App\Models\ClosingStockModel;
use App\Models\PurchaseInvoiceProductsModel;
use App\Models\SalesInvoiceProductsModel;
use App\Models\ResetQueueModel;
use App\Models\GodownModel;
use App\Models\SalesInvoiceModel;
use App\Models\SalesReturnProductsModel;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;
use App\Models\StockTransferProductsModel;
use Carbon\Carbon;
use Auth;
use DB;

class ResetController extends Controller
{
    //

    public function make_reset_queue(Request $request)
    {
        // Check if the record exists with status 0
        $existingQueue = ResetQueueModel::where([
            'product_id' => $request->input('product_id'),
            'company_id' => Auth::user()->company_id,
        ])->first();

        if ($existingQueue != null) {
            if ($existingQueue->status == '0') {
                // Skip if the status is 0
                return response()->json([
                    'message' => 'Product already exists in the reset queue with status 0. Skipped.',
                ], 200);
            } 
        }

        // Create a new record if status not 0
        $newQueue = ResetQueueModel::create([
            'product_id' => $request->input('product_id'),
            'company_id' => Auth::user()->company_id,
            'status' => '0', // Default status for new records
        ]);

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Product added to the reset queue successfully!',
            'reset_queue' => $newQueue,
        ], 201);
    }

    public function stock_calculation_old($id)
    {
        $start_date = "2024-04-01";
        $end_date = Carbon::now()->format('Y-m-d');
        $get_year = 6;

        DB::transaction(function() use ($start_date, $end_date, $get_year, $id) {
            // 🔹 STEP 1 : Reset 'sold' in opening stock for this product and year
            OpeningStockModel::where('year', $get_year)
                ->where('company_id', Auth::user()->company_id)
                ->where('product_id', $id)
                ->update(['sold' => 0]);

            // 🔹 STEP 2 : Reset 'sold' in purchase invoice products within the date range for this product
            PurchaseInvoiceProductsModel::whereHas('purchaseInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('purchase_invoice_date', '>=', $start_date)
                        ->whereDate('purchase_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)
                ->update(['sold' => 0]);

            // 🔹 STEP 3 : Reset sales invoice products fields for this product within the date range
            SalesInvoiceProductsModel::whereHas('salesInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('sales_invoice_date', '>=', $start_date)
                        ->whereDate('sales_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)  // 👈 Add filter for this product
                ->update([
                    'profit' => 0,
                    'returned' => 0,
                    'purchase_invoice_id' => null,
                    'purchase_rate' => null,
                ]);

                // 🔹 STEP 4 : Traverse sales invoices for this product within date range
                $salesInvoices = SalesInvoiceModel::with(['products' => function($query) use ($id) {
                    $query->where('product_id', $id);
                }])
                ->whereBetween('sales_invoice_date', [$start_date, $end_date])
                ->orderBy('sales_invoice_date')
                ->get();

                foreach ($salesInvoices as $invoice) {
                    foreach ($invoice->products as $saleProduct) {
                        $productId = $saleProduct->product_id;

                        if($productId != $id) {
                            continue; // Skip if product ID does not match
                        }

                        $godownId = $saleProduct->godown;
                        $remainingQty = $saleProduct->quantity;
                        $purchaseDetails = [];
                        $totalPurchaseCost = 0;

                        // 🔹 Step 4A: Exhaust Opening Stock FIRST for matching godown
                        $openingStocks = OpeningStockModel::where('product_id', $productId)
                            ->where('year', $get_year)
                            ->where('company_id', Auth::user()->company_id)
                            ->where('godown_id', $godownId)
                            ->whereColumn('quantity', '>', DB::raw('sold'))
                            ->orderBy('created_at')
                            ->get();

                        foreach ($openingStocks as $opening) {
                            if ($remainingQty <= 0) break;

                            $availableQty = $opening->quantity - $opening->sold;
                            $usedQty = min($availableQty, $remainingQty);

                            $opening->sold += $usedQty;
                            $opening->save();

                            // 🔹 Add detailed entry for opening stock
                            $purchaseDetails[] = [
                                'id' => $opening->id,
                                'type' => 'opening_stock',
                                'quantity' => $usedQty,
                            ];

                            $totalPurchaseCost += $usedQty * $opening->value;
                            $remainingQty -= $usedQty;
                        }

                        // 🔹 Step 4B: Then use Purchase Invoices with matching godown
                        if ($remainingQty > 0) {
                            $purchaseEntries = PurchaseInvoiceProductsModel::where('product_id', $productId)
                                ->where('godown', $godownId)
                                ->whereColumn('quantity', '>', DB::raw('sold'))
                                ->join('t_purchase_invoice', 't_purchase_invoice_products.purchase_invoice_id', '=', 't_purchase_invoice.id')
                                ->whereBetween('t_purchase_invoice.purchase_invoice_date', [$start_date, $end_date])
                                ->orderBy('t_purchase_invoice.purchase_invoice_date')
                                ->select('t_purchase_invoice_products.*', 't_purchase_invoice.purchase_invoice_date')
                                ->get();

                            foreach ($purchaseEntries as $purchase) {
                                if ($remainingQty <= 0) break;

                                $availableQty = $purchase->quantity - $purchase->sold;
                                $usedQty = min($availableQty, $remainingQty);

                                $purchase->sold += $usedQty;
                                $purchase->save();

                                // 🔹 Add detailed entry for purchase
                                $purchaseDetails[] = [
                                    'id' => $purchase->id,
                                    'type' => 'purchase',
                                    'quantity' => $usedQty,
                                ];
                                $totalPurchaseCost += $usedQty * $purchase->price;
                                $remainingQty -= $usedQty;
                            }
                        }

                        // 🔹 Step 4C: Update SalesInvoiceProduct with calculations
                        $saleProduct->purchase_invoice_id = $purchaseDetails ? json_encode($purchaseDetails) : null;
                        $saleProduct->purchase_rate = $totalPurchaseCost ?: null;
                        $saleProduct->profit = ($saleProduct->amount ?? 0) - ($saleProduct->cgst ?: 0) - ($saleProduct->sgst ?: 0) - ($saleProduct->igst ?: 0) - ($totalPurchaseCost ?: 0);
                        $saleProduct->save();
                    }
                }

            // Collect godown-wise stock from OpeningStock
            $openingStocks = OpeningStockModel::where('year', $get_year)
                ->where('company_id', Auth::user()->company_id)
                ->where('product_id', $id)
                ->get();

            // Collect godown-wise stock from PurchaseInvoiceProducts
            $purchaseStocks = PurchaseInvoiceProductsModel::whereHas('purchaseInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('purchase_invoice_date', '>=', $start_date)
                        ->whereDate('purchase_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)
                ->get()
                ->groupBy('godown');

            // 🔹 STEP 6 : Delete existing closing stock for this product and year
            ClosingStockModel::where('year', $get_year)
                ->where('company_id', Auth::user()->company_id)
                ->where('product_id', $id)
                ->delete();

            // 🔹 STEP 7 : Loop through godowns and calculate remaining stock
            $godownIds = $openingStocks->keys()->merge($purchaseStocks->keys())->unique();

            foreach ($godownIds as $godownId) {
                $totalQty = 0;
                $totalValue = 0;
                $totalSold = 0;

                // From Opening Stock
                if ($openingStocks->has($godownId) && is_iterable($openingStocks[$godownId])) {
                    foreach ($openingStocks[$godownId] as $opening) {
                        $availableQty = round($opening->quantity - $opening->sold, 2);
                        if ($availableQty > 0) {
                            $totalQty += $availableQty;
                            $totalValue += round($availableQty * $opening->value, 2);
                            $totalSold += $opening->sold;
                        }
                    }
                }
                

                // From Purchase Invoices
                if ($purchaseStocks->has($godownId) && is_iterable($purchaseStocks[$godownId])) {
                    foreach ($purchaseStocks[$godownId] as $purchase) {
                        $availableQty = round($purchase->quantity - $purchase->sold, 2);
                        if ($availableQty > 0) {
                            $totalQty += $availableQty;
                            $totalValue += round($availableQty * $purchase->price, 2);
                            $totalSold += $purchase->sold;
                        }
                    }
                }
                

                // 🔹 STEP 8 : Insert new closing stock if totalQty > 0
                if ($totalQty > 0) {
                    ClosingStockModel::create([
                        'company_id' => Auth::user()->company_id,
                        'year' => $get_year,
                        'godown_id' => $godownId,
                        'product_id' => $id,
                        'quantity' => round($totalQty, 2),
                        'value' => round($totalValue, 2),
                    ]);
                }

            }
        });
    }

    public function stock_calculation($id)
    {
        $start_date = "2024-04-01";
        $end_date = Carbon::now()->format('Y-m-d');
        $get_year = 6;
        $company_id = Auth::user()->company_id;

        
        DB::transaction(function() use ($id, $start_date, $end_date, $get_year, $company_id) {

            // 🔹 STEP 1 : Reset 'sold' in opening stock for this product and year
            OpeningStockModel::where('year', $get_year)
                ->where('company_id', Auth::user()->company_id)
                ->where('product_id', $id)
                ->update(['sold' => 0]);

            // 🔹 STEP 2 : Reset 'sold' in purchase invoice products within the date range for this product
            PurchaseInvoiceProductsModel::whereHas('purchaseInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('purchase_invoice_date', '>=', $start_date)
                        ->whereDate('purchase_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)
                ->update(['sold' => 0]);

            // 🔹 STEP 3 : Reset sales invoice products fields for this product within the date range
            SalesInvoiceProductsModel::whereHas('salesInvoice', function ($query) use ($start_date, $end_date) {
                    $query->whereDate('sales_invoice_date', '>=', $start_date)
                        ->whereDate('sales_invoice_date', '<=', $end_date);
                })
                ->where('product_id', $id)  // 👈 Add filter for this product
                ->update([
                    'profit' => 0,
                    'returned' => 0,
                    'purchase_invoice_id' => null,
                    'purchase_rate' => null,
                ]);

            $events = [];

            // 1️⃣ Opening Stock
            $openings = OpeningStockModel::where('product_id', $id)
                ->where('company_id', $company_id)
                ->where('year', $get_year)
                ->get();
            foreach ($openings as $o) {
                $events[] = [
                    'type' => 'opening', 'product_id' => $id, 'godown_id' => $o->godown_id,
                    'quantity' => $o->quantity, 'rate' => $o->value, 'amount' => $o->quantity * $o->value,
                    'date' => '0000-00-00', 'source_id' => $o->id, 'source_type' => 'opening'
                ];
            }

            // 2️⃣ Purchases (net of returns)
            $purchases = PurchaseInvoiceProductsModel::where('product_id', $id)
                ->whereHas('purchaseInvoice', fn($q) => $q->whereBetween('purchase_invoice_date', [$start_date, $end_date]))
                ->with('purchaseInvoice')->get();
            foreach ($purchases as $p) {
                $netQty = $p->quantity - ($p->returned ?? 0);
                if ($netQty <= 0) continue;
                $events[] = [
                    'type' => 'purchase', 'product_id' => $id, 'godown_id' => null,
                    'quantity' => $netQty, 'rate' => $p->price, 'amount' => $netQty * $p->price,
                    'date' => $p->purchaseInvoice->purchase_invoice_date, 'source_id' => $p->id, 'source_type' => 'purchase'
                ];
            }

            // 3️⃣ Sales (net of returns)
            $sales = SalesInvoiceProductsModel::where('product_id', $id)
                ->whereHas('salesInvoice', fn($q) => $q->whereBetween('sales_invoice_date', [$start_date, $end_date]))
                ->with('salesInvoice')->get();
            foreach ($sales as $s) {
                $netQty = $s->quantity - ($s->returned ?? 0);
                if ($netQty <= 0) continue;
                $events[] = [
                    'type' => 'sale', 'product_id' => $id, 'godown_id' => null,
                    'quantity' => $netQty, 'rate' => null, 'amount' => $s->amount,
                    'date' => $s->salesInvoice->sales_invoice_date, 'source_id' => $s->id
                ];
            }

            // 4️⃣ Assembly
            // Assemblies (already filtered)
            $assemblies = AssemblyOperationModel::where('company_id', $company_id)
                ->whereBetween('assembly_operations_date', [$start_date, $end_date])
                ->with('products')->get();
                foreach ($assemblies as $a) {
                $type = strtolower($a->type);
                foreach ($a->products as $c) {
                    if ($c->product_id == $id) {
                        $qty = $c->quantity * $a->quantity;
                        $events[] = [
                            'type' => ($type == 'assemble') ? 'assembly_component_out' : 'assembly_component_in',
                            'product_id' => $id, 'godown_id' => $c->godown, 'quantity' => $qty,
                            'date' => $a->assembly_operations_date, 'source_id' => $c->id, 'source_type' => 'assembly_product'
                        ];
                    }
                }
                if ($a->product_id == $id) {
                    $events[] = [
                        'type' => ($type == 'assemble') ? 'assembly_product_in' : 'assembly_product_out',
                        'product_id' => $id, 'godown_id' => $a->godown, 'quantity' => $a->quantity,
                        'rate' => $a->rate, 'amount' => $a->amount, 'date' => $a->assembly_operations_date, 'source_id' => $a->id, 'source_type' => 'assembly'
                    ];
                }
            }

            // Transfers (filtered by date)
            $transfers = StockTransferProductsModel::where('product_id', $id)
                ->whereHas('stockTransfer', fn($q) => $q->whereBetween('transfer_date', [$start_date, $end_date]))
                ->with('stockTransfer')->get();
                foreach ($transfers as $t) {
                $transferDate = $t->stockTransfer->transfer_date;
                $events[] = [
                    'type' => 'transfer_out', 'product_id' => $id, 'godown_id' => $t->stockTransfer->godown_from,
                    'quantity' => $t->quantity, 'date' => $transferDate, 'source_id' => $t->id
                ];
                $events[] = [
                    'type' => 'transfer_in', 'product_id' => $id, 'godown_id' => $t->stockTransfer->godown_to,
                    'quantity' => $t->quantity, 'date' => $transferDate, 'source_id' => $t->id
                ];
            }


            // 6️⃣ Sort Events
            usort($events, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

            // header('Content-Type: application/json');
            // die(json_encode($events));

            $fifo = [];  // [{qty, rate, source_id, source_type}]
            $godownStock = [];

            // 7️⃣ Process Events
            foreach ($events as $e) {
                $q = $e['quantity']; $r = $e['rate'] ?? 0; $g = $e['godown_id'];
                switch ($e['type']) {
                    case 'opening': case 'purchase': case 'assembly_product_in': case 'assembly_component_in': case 'transfer_in':
                        $fifo[] = [
                            'qty' => $q, 'rate' => $r, 'source_id' => $e['source_id'], 'source_type' => $e['source_type'] ?? 'other'
                        ];
                        if ($g !== null) $godownStock[$g] = ($godownStock[$g] ?? 0) + $q;
                        break;

                    case 'sale':
                        $sale = SalesInvoiceProductsModel::find($e['source_id']);
                        if ($sale) {
                            $purchaseDetails = [];
                            $rem = $q; $cost = 0;
                            while ($rem > 0 && $fifo) {
                                $layer = &$fifo[0];
                                $used = min($layer['qty'], $rem);
                                $cost += $used * $layer['rate'];
                                $purchaseDetails[] = [
                                    'id' => $layer['source_id'], 'type' => $layer['source_type'], 'quantity' => $used
                                ];
                                // Mark sold in source
                                switch ($layer['source_type']) {
                                    case 'purchase':
                                        PurchaseInvoiceProductsModel::where('id', $layer['source_id'])->increment('sold', $used);
                                        break;
                                    case 'opening':
                                        OpeningStockModel::where('id', $layer['source_id'])->increment('sold', $used);
                                        break;
                                    case 'assembly_product':
                                        AssemblyOperationProductsModel::where('id', $layer['source_id'])->increment('sold', $used);
                                        break;
                                    case 'assembly':
                                        AssemblyOperationModel::where('id', $layer['source_id'])->increment('sold', $used);
                                        break;
                                }
                                $layer['qty'] -= $used; $rem -= $used;
                                if ($layer['qty'] == 0) array_shift($fifo);
                            }
                            $sale->profit = ($sale->amount ?? 0) - ($sale->cgst ?: 0) - ($sale->sgst ?: 0) - ($sale->igst ?: 0) - $cost;
                            $sale->purchase_rate = $cost;
                            $sale->purchase_invoice_id = json_encode($purchaseDetails);
                            $sale->save();
                        }
                        break;

                    case 'assembly_product_out': case 'assembly_component_out': case 'transfer_out':
                        $rem = $q;
                        while ($rem > 0 && $fifo) {
                            $layer = &$fifo[0];
                            $used = min($layer['qty'], $rem);
                            switch ($layer['source_type']) {
                                case 'purchase':
                                    PurchaseInvoiceProductsModel::where('id', $layer['source_id'])->increment('sold', $used);
                                    break;
                                case 'opening':
                                    OpeningStockModel::where('id', $layer['source_id'])->increment('sold', $used);
                                    break;
                                case 'assembly_product':
                                    AssemblyOperationProductsModel::where('id', $layer['source_id'])->increment('sold', $used);
                                    break;
                                case 'assembly':
                                    AssemblyOperationModel::where('id', $layer['source_id'])->increment('sold', $used);
                                    break;
                            }
                            $layer['qty'] -= $used; $rem -= $used;
                            if ($layer['qty'] == 0) array_shift($fifo);
                        }
                        if ($g !== null) $godownStock[$g] = ($godownStock[$g] ?? 0) - $q;
                        break;
                }
            }

            // 8️⃣ Update Closing Stock
            ClosingStockModel::where('company_id', $company_id)
                ->where('year', $get_year)
                ->where('product_id', $id)->delete();
            foreach ($godownStock as $g => $qty) {
                if ($qty > 0) {
                    ClosingStockModel::create([
                        'company_id' => $company_id, 'year' => $get_year,
                        'godown_id' => $g, 'product_id' => $id,
                        'quantity' => round($qty,2), 'value' => 0
                    ]);
                }
            }
        });
    }

    function updateReturnedQuantitiesForSalesInvoice($salesInvoiceId)
    {
        // Reset all returned quantities to 0 for the given sales_invoice_id
        SalesInvoiceProductsModel::where('sales_invoice_id', $salesInvoiceId)
            ->update(['returned' => 0]);

        // Fetch sales return products linked to the given sales_invoice_id
        $salesReturnProducts = SalesReturnProductsModel::whereHas('salesreturn', function($query) use ($salesInvoiceId) {
            $query->where('sales_invoice_id', $salesInvoiceId);
        })->get();

        if ($salesReturnProducts->isEmpty()) {
            return; // No sales returns found, exit
        }

        // Group sales return products by product_id with total returned quantity
        $returnProductGroups = $salesReturnProducts->groupBy('product_id')->map(function($items) {
            return $items->sum('quantity');
        });

        foreach ($returnProductGroups as $productId => $totalReturnedQty) {
            if ($totalReturnedQty <= 0) continue;

            // Fetch all matching sales invoice product lines (same sales_invoice_id & product_id), ordered by id (FIFO)
            $invoiceProductLines = SalesInvoiceProductsModel::where('sales_invoice_id', $salesInvoiceId)
                ->where('product_id', $productId)
                ->orderBy('id')
                ->get();

            foreach ($invoiceProductLines as $productLine) {
                if ($totalReturnedQty <= 0) break; // No more qty to distribute

                $lineQty = $productLine->quantity;
                $currentReturned = $productLine->returned;

                // Calculate how much can be allocated to this line
                $allocatable = min($totalReturnedQty, $lineQty - $currentReturned);
                if ($allocatable > 0) {
                    $productLine->returned += $allocatable;
                    $productLine->save();

                    $totalReturnedQty -= $allocatable;
                }
            }
        }
    }


    // public function reset_product(Request $request)
    // {
    //     $request->validate([
    //         'product_id' => 'required|integer|exists:t_products,id'
    //     ]);

    //     $companyId = auth()->user()->company_id;
    //     $productId = $request->product_id;

    //     // Step 1: Fetch all godowns for the company
    //     $godowns = GodownModel::where('company_id', $companyId)->get(['id', 'name']);

    //     // Step 2: Fetch godown-wise stock and value
    //     $stockSummary = OpeningStockModel::where('company_id', $companyId)
    //         ->where('product_id', $productId)
    //         ->selectRaw('godown_id, SUM(quantity) as total_quantity, SUM(quantity * value) as stock_value')
    //         ->groupBy('godown_id')
    //         ->get()
    //         ->keyBy('godown_id');

    //     $godownData = $godowns->map(function ($godown) use ($stockSummary) {
    //         $entry = $stockSummary->get($godown->id);
    //         return [
    //             'godown_id' => $godown->id,
    //             'godown_name' => $godown->name,
    //             'quantity' => $entry->total_quantity ?? 0,
    //             'stock_value' => $entry->stock_value ?? 0,
    //         ];
    //     });

    //     // Step 3: Update PurchaseInvoiceProductsModel stock = quantity
    //     $purchaseItems = PurchaseInvoiceProductsModel::where('company_id', $companyId)
    //     ->where('product_id', $productId)
    //     ->whereHas('purchaseInvoice', function ($query) {
    //         $query->whereDate('purchase_invoice_date', '>=', '2024-04-01');
    //     })
    //     ->with(['purchaseInvoice:id,purchase_invoice_date']) // Eager load invoice date
    //     ->get();

    //     // Initialize counters and details

    //     $updatedCount = 0;
    //     $purchaseUpdateDetails = [];

    //     foreach ($purchaseItems as $item) {
    //         $item->stock = $item->quantity;
    //         $item->save();
    //         $updatedCount++;

    //         $purchaseUpdateDetails[] = [
    //             'purchase_invoice_date' => $item->purchaseInvoice->purchase_invoice_date,
    //             'godown' => $item->godown,
    //             'quantity' => $item->quantity,
    //         ];
    //     }

    //     // Step 4: Fetch Sales data
    //     $salesItems = SalesInvoiceProductsModel::where('company_id', $companyId)
    //         ->where('product_id', $productId)
    //         ->whereHas('salesInvoice', function ($query) {
    //             $query->whereDate('sales_invoice_date', '>=', '2024-04-01');
    //         })
    //         ->with(['salesInvoice:id,sales_invoice_date']) // Eager load invoice date
    //         ->get();

    //     $salesData = $salesItems->map(function ($item) {
    //         return [
    //             'sales_invoice_date' => $item->salesInvoice->sales_invoice_date,
    //             'godown' => $item->godown,
    //             'quantity' => $item->quantity,
    //         ];
    //     });

    //     return response()->json([
    //         'message' => 'Reset completed successfully.',
    //         'godown_stock' => $godownData,
    //         'purchase_stock_updated' => $updatedCount,
    //         'purchase_update_details' => $purchaseUpdateDetails,
    //         'sales_invoice_data' => $salesData,
    //     ]);    
    // }

}
