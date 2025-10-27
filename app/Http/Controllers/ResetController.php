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
use App\Models\ProductsModel;
use Carbon\Carbon;
use Auth;
use DB;

class ResetController extends Controller
{

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

    public function stock_calculation()
    {
        $start_date = "2025-04-01";
        $end_date = Carbon::now()->format('Y-m-d');
        $get_year = 6;
        $company_id = Auth::user()->company_id;

        set_time_limit(0);

        $batchSize = 50; // tune as needed
        $offset = 0;

        do {
            // Get next batch of product IDs that are pending
            $product_ids = DB::table('t_reset_queue')
                ->where('status', '0')
                ->orderBy('id')
                ->offset($offset)
                ->limit($batchSize)
                ->pluck('product_id');

            if ($product_ids->isEmpty()) {
                break;
            }

            foreach ($product_ids as $id) {
                DB::transaction(function () use ($id, $start_date, $end_date, $get_year, $company_id) {

                    // 1) Reset sold flags for this product
                    OpeningStockModel::where('year', $get_year)
                        ->where('company_id', $company_id)
                        ->where('product_id', $id)
                        ->update(['sold' => 0]);

                    PurchaseInvoiceProductsModel::where('product_id', $id)
                        ->whereHas('purchaseInvoice', function ($q) use ($start_date, $end_date) {
                            $q->whereBetween('purchase_invoice_date', [$start_date, $end_date]);
                        })
                        ->update(['sold' => 0]);

                    SalesInvoiceProductsModel::where('product_id', $id)
                        ->whereHas('salesInvoice', function ($q) use ($start_date, $end_date) {
                            $q->whereBetween('sales_invoice_date', [$start_date, $end_date]);
                        })
                        ->update([
                            'profit' => 0,
                            'returned' => 0,
                            'purchase_invoice_id' => null,
                            'purchase_rate' => null,
                        ]);

                    // 2) Build event stream (each with godown)
                    $events = [];

                    // Opening stock (date set to very old so it sorts first)
                    $openings = OpeningStockModel::where('product_id', $id)
                        ->where('company_id', $company_id)
                        ->where('year', $get_year)
                        ->get();

                    foreach ($openings as $o) {
                        if ($o->godown_id === null) continue;
                        $events[] = [
                            'type' => 'opening',
                            'product_id' => $id,
                            'godown_id' => (int)$o->godown_id,
                            'quantity' => (float)$o->quantity,
                            'rate' => (float)$o->value,
                            'date' => '1970-01-01',
                            'source_id' => $o->id,
                            'source_type' => 'opening',
                        ];
                    }

                    // Purchases (net of returns)
                    $purchases = PurchaseInvoiceProductsModel::where('product_id', $id)
                        ->whereHas('purchaseInvoice', fn($q) => $q->whereBetween('purchase_invoice_date', [$start_date, $end_date]))
                        ->with('purchaseInvoice')
                        ->get();

                    foreach ($purchases as $p) {
                        $netQty = (float)$p->quantity - (float)($p->returned ?? 0);
                        if ($netQty <= 0) continue;
                        if ($p->godown === null) continue;
                        $events[] = [
                            'type' => 'purchase',
                            'product_id' => $id,
                            'godown_id' => (int)$p->godown,
                            'quantity' => $netQty,
                            'rate' => (float)$p->price,
                            'date' => $p->purchaseInvoice->purchase_invoice_date,
                            'source_id' => $p->id,
                            'source_type' => 'purchase',
                        ];
                    }

                    // Sales (net of returns)
                    $sales = SalesInvoiceProductsModel::where('product_id', $id)
                        ->whereHas('salesInvoice', fn($q) => $q->whereBetween('sales_invoice_date', [$start_date, $end_date]))
                        ->with('salesInvoice')
                        ->get();

                    foreach ($sales as $s) {
                        $netQty = (float)$s->quantity - (float)($s->returned ?? 0);
                        if ($netQty <= 0) continue;
                        if ($s->godown === null) continue;
                        $events[] = [
                            'type' => 'sale',
                            'product_id' => $id,
                            'godown_id' => (int)$s->godown,
                            'quantity' => $netQty,
                            'rate' => null,
                            'date' => $s->salesInvoice->sales_invoice_date,
                            'source_id' => $s->id,
                            'source_type' => 'sale',
                        ];
                    }

                    // Assemblies
                    $assemblies = AssemblyOperationModel::where('company_id', $company_id)
                        ->whereBetween('assembly_operations_date', [$start_date, $end_date])
                        ->with('products')
                        ->get();

                    foreach ($assemblies as $a) {
                        $atype = strtolower($a->type ?? '');
                        // components
                        foreach ($a->products as $c) {
                            if ((int)$c->product_id !== (int)$id) continue;
                            if ($c->godown === null) continue;
                            $qty = (float)$c->quantity * (float)$a->quantity;
                            $events[] = [
                                'type' => ($atype === 'assemble') ? 'assembly_component_out' : 'assembly_component_in',
                                'product_id' => $id,
                                'godown_id' => (int)$c->godown,
                                'quantity' => $qty,
                                'rate' => null,
                                'date' => $a->assembly_operations_date,
                                'source_id' => $c->id,
                                'source_type' => 'assembly_product',
                            ];
                        }
                        // product
                        if ((int)$a->product_id === (int)$id) {
                            if ($a->godown !== null) {
                                $events[] = [
                                    'type' => ($atype === 'assemble') ? 'assembly_product_in' : 'assembly_product_out',
                                    'product_id' => $id,
                                    'godown_id' => (int)$a->godown,
                                    'quantity' => (float)$a->quantity,
                                    'rate' => (float)($a->rate ?? 0),
                                    'date' => $a->assembly_operations_date,
                                    'source_id' => $a->id,
                                    'source_type' => 'assembly',
                                ];
                            }
                        }
                    }

                    // Transfers – create a single "transfer" event that knows from/to
                    $transfers = StockTransferProductsModel::where('product_id', $id)
                        ->whereHas('stockTransfer', fn($q) => $q->whereBetween('transfer_date', [$start_date, $end_date]))
                        ->with('stockTransfer')
                        ->get();

                    foreach ($transfers as $t) {
                        $from = $t->stockTransfer->godown_from;
                        $to   = $t->stockTransfer->godown_to;
                        if ($from === null || $to === null) continue;
                        $events[] = [
                            'type' => 'transfer',
                            'product_id' => $id,
                            'godown_from' => (int)$from,
                            'godown_to' => (int)$to,
                            'quantity' => (float)$t->quantity,
                            'date' => $t->stockTransfer->transfer_date,
                            'source_id' => $t->id,
                            'source_type' => 'transfer',
                        ];
                    }

                    // 3) Sort by date ASC
                    usort($events, function ($a, $b) {
                        return strtotime($a['date']) <=> strtotime($b['date']);
                    });

                    // 4) FIFO per-godown utilities
                    $fifo = [];        // $fifo[$godownId] = [ ['qty','rate','source_type','source_id'], ... ]
                    $godownStock = []; // running qty per godown

                    $pushLayer = function (&$fifo, int $g, float $qty, float $rate, string $stype, int $sid) {
                        if ($qty <= 0) return;
                        $fifo[$g] ??= [];
                        $fifo[$g][] = ['qty' => $qty, 'rate' => $rate, 'source_type' => $stype, 'source_id' => $sid];
                    };

                    $consumeFrom = function (&$fifo, int $g, float $qtyNeeded) {
                        $cost = 0.0; $rem = $qtyNeeded; $piIds = [];
                        while ($rem > 0 && !empty($fifo[$g])) {
                            $layer = &$fifo[$g][0];
                            $used  = min($layer['qty'], $rem);
                            $cost += $used * (float)$layer['rate'];

                            // mark sold to original source & collect PI ids
                            switch ($layer['source_type']) {
                                case 'purchase':
                                    $piId = PurchaseInvoiceProductsModel::where('id', $layer['source_id'])->value('purchase_invoice_id');
                                    if ($piId) $piIds[$piId] = true;
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

                            $layer['qty'] -= $used;
                            $rem -= $used;
                            if ($layer['qty'] <= 1e-9) array_shift($fifo[$g]);
                        }
                        return [$cost, array_keys($piIds), $rem];
                    };

                    $transferLayers = function (&$fifo, int $gFrom, int $gTo, float $qty) use ($pushLayer) {
                        if ($qty <= 0) return 0.0;
                        $moved = 0.0;
                        $rem = $qty;
                        while ($rem > 0 && !empty($fifo[$gFrom])) {
                            $layer = &$fifo[$gFrom][0];
                            $used  = min($layer['qty'], $rem);
                            // move at same rate and source
                            $pushLayer($fifo, $gTo, $used, (float)$layer['rate'], $layer['source_type'], $layer['source_id']);
                            $layer['qty'] -= $used;
                            $moved += $used;
                            $rem -= $used;
                            if ($layer['qty'] <= 1e-9) array_shift($fifo[$gFrom]);
                        }
                        return $moved; // quantity actually moved
                    };

                    // 5) Process events
                    foreach ($events as $e) {
                        $type = $e['type'];
                        if ($type === 'transfer') {
                            $from = $e['godown_from'];
                            $to   = $e['godown_to'];
                            $qty  = (float)$e['quantity'];
                            $moved = $transferLayers($fifo, $from, $to, $qty);
                            $godownStock[$from] = ($godownStock[$from] ?? 0) - $moved;
                            $godownStock[$to]   = ($godownStock[$to] ?? 0) + $moved;
                            continue;
                        }

                        $g = $e['godown_id'] ?? null;
                        $q = (float)$e['quantity'];
                        $r = (float)($e['rate'] ?? 0);

                        switch ($type) {
                            // inflows
                            case 'opening':
                            case 'purchase':
                            case 'assembly_product_in':
                            case 'assembly_component_in':
                                if ($g === null) break;
                                $pushLayer($fifo, (int)$g, $q, $r, $e['source_type'] ?? 'other', (int)$e['source_id']);
                                $godownStock[$g] = ($godownStock[$g] ?? 0) + $q;
                                break;

                            // outflows that impact profit (sales)
                            case 'sale':
                                if ($g === null) break;
                                $sale = SalesInvoiceProductsModel::find($e['source_id']);
                                if ($sale) {
                                    [$cost, $piIds, $rem] = $consumeFrom($fifo, (int)$g, $q);
                                    // (optional) if $rem > 0, shortage occurred – handle/log

                                    $sale->purchase_rate = $cost;
                                    $sale->purchase_invoice_id = $piIds[0] ?? null; // first PI used (FIFO)
                                    $sale->profit = ($sale->amount ?? 0)
                                        - ($sale->cgst ?: 0) - ($sale->sgst ?: 0) - ($sale->igst ?: 0)
                                        - $cost;
                                    $sale->save();

                                    $godownStock[$g] = ($godownStock[$g] ?? 0) - $q;
                                }
                                break;

                            // other outflows (no profit calc)
                            case 'assembly_product_out':
                            case 'assembly_component_out':
                                if ($g === null) break;
                                [, , $rem] = $consumeFrom($fifo, (int)$g, $q);
                                // (optional) handle $rem > 0
                                $godownStock[$g] = ($godownStock[$g] ?? 0) - $q;
                                break;
                        }
                    }

                    // 6) Write Closing Stock (per godown, with valuation)
                    ClosingStockModel::where('company_id', $company_id)
                        ->where('year', $get_year)
                        ->where('product_id', $id)
                        ->delete();

                    foreach ($godownStock as $g => $qty) {
                        if ($qty <= 0) continue;

                        $value = 0.0;
                        if (!empty($fifo[$g])) {
                            foreach ($fifo[$g] as $layer) {
                                $lqty = (float)($layer['qty'] ?? 0);
                                if ($lqty > 0) {
                                    $value += $lqty * (float)($layer['rate'] ?? 0);
                                }
                            }
                        }

                        ClosingStockModel::create([
                            'company_id' => $company_id,
                            'year'       => $get_year,
                            'godown_id'  => (int)$g,
                            'product_id' => $id,
                            'quantity'   => round((float)$qty, 2),
                            'value'      => round($value, 2),
                        ]);
                    }

                    // 7) Mark queue row done
                    DB::table('t_reset_queue')
                        ->where('product_id', $id)
                        ->update(['status' => '1', 'updated_at' => now()]);
                });
            }

            // next page
            $offset += $batchSize;

            // IMPORTANT: do not return here (keep looping)
        } while (true);

        return response()->json([
            'success' => true,
            'message' => 'Stock reset and closing stock recomputed for all products in reset queue.',
        ]);
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

    public function reset_queue_status()
    {
        // Optional: Increase timeout for large datasets
        set_time_limit(0);

        // 🔹 Update all records in t_reset_queue to set status = '0'
        DB::table('t_reset_queue')->update(['status' => '0', 'updated_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All queue statuses have been reset to 0.'
        ]);
    }

    public function addAllProductsToResetQueue()
    {
        set_time_limit(0);

        $companyId = Auth::user()->company_id;
        $now = now();

        // Build a fast lookup of existing product_ids in queue for this company
        $existingIds = [];
        ResetQueueModel::where('company_id', $companyId)
            ->select('product_id')
            ->orderBy('product_id')
            ->chunk(5000, function ($rows) use (&$existingIds) {
                foreach ($rows as $r) {
                    $existingIds[$r->product_id] = true; // associative for O(1) lookup
                }
            });

        $toInsert = [];
        $toUpdateIds = [];
        $inserted = 0;
        $updated = 0;

        // Iterate all products of this company in chunks
        ProductsModel::where('company_id', $companyId)
            ->select('id')
            ->orderBy('id')
            ->chunk(2000, function ($products) use (
                $companyId, $now, &$toInsert, &$toUpdateIds, &$inserted, &$updated, $existingIds
            ) {
                foreach ($products as $p) {
                    if (isset($existingIds[$p->id])) {
                        $toUpdateIds[] = $p->id;
                    } else {
                        $toInsert[] = [
                            'product_id' => $p->id,
                            'company_id' => $companyId,
                            'status'     => '0',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Flush inserts in manageable batches
                if (count($toInsert) >= 5000) {
                    foreach (array_chunk($toInsert, 1000) as $chunk) {
                        ResetQueueModel::insert($chunk);
                        $inserted += count($chunk);
                    }
                    $toInsert = [];
                }

                // Flush updates in manageable batches
                if (count($toUpdateIds) >= 5000) {
                    foreach (array_chunk($toUpdateIds, 1000) as $batchIds) {
                        $count = ResetQueueModel::where('company_id', $companyId)
                            ->whereIn('product_id', $batchIds)
                            ->update(['status' => '0', 'updated_at' => $now]);
                        $updated += $count;
                    }
                    $toUpdateIds = [];
                }
            });

        // Final flush (remaining records)
        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 1000) as $chunk) {
                ResetQueueModel::insert($chunk);
                $inserted += count($chunk);
            }
        }

        if (!empty($toUpdateIds)) {
            foreach (array_chunk($toUpdateIds, 1000) as $batchIds) {
                $count = ResetQueueModel::where('company_id', $companyId)
                    ->whereIn('product_id', $batchIds)
                    ->update(['status' => '0', 'updated_at' => $now]);
                $updated += $count;
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => 'All products enqueued (missing inserted; existing set to status 0).',
            'inserted' => $inserted,
            'updated'  => $updated,
        ]);
    }

}
