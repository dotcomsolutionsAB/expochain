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
        $end_date   = Carbon::now()->format('Y-m-d');
        $get_year   = 6;
        $company_id = Auth::user()->company_id;

        // Run for ~5 minutes max; cron will call us every 5 minutes
        set_time_limit(300);

        $batchSize = 100; // process 100 products per trigger
        $processed = 0;

        // Pick 100 pending rows (status=0)
        $queueRows = DB::table('t_reset_queue')
            ->where('status', '0')
            ->orderBy('id')
            ->limit($batchSize)
            ->get(['id','product_id']);

        if ($queueRows->isEmpty()) {
            return response()->json([
                'success' => true,
                'processed' => 0,
                'message' => 'No pending products in reset queue.'
            ]);
        }

        foreach ($queueRows as $row) {
            $processed++;

            DB::transaction(function () use ($row, $start_date, $end_date, $get_year, $company_id) {
                $productId = (int)$row->product_id;

                // 1) Reset flags for this product in the window
                OpeningStockModel::where('year', $get_year)
                    ->where('company_id', $company_id)
                    ->where('product_id', $productId)
                    ->update(['sold' => 0]);

                PurchaseInvoiceProductsModel::where('product_id', $productId)
                    ->whereHas('purchaseInvoice', fn($q) => $q->whereBetween('purchase_invoice_date', [$start_date, $end_date]))
                    ->update(['sold' => 0]);

                SalesInvoiceProductsModel::where('product_id', $productId)
                    ->whereHas('salesInvoice', fn($q) => $q->whereBetween('sales_invoice_date', [$start_date, $end_date]))
                    ->update([
                        'profit' => 0,
                        'returned' => 0,
                        'purchase_invoice_id' => null,
                        'purchase_rate' => null,
                    ]);

                // 2) Build event stream (ALL flows, with godowns & dates)
                $events = [];

                // Opening stock (forced earliest date)
                $openings = OpeningStockModel::where('product_id', $productId)
                    ->where('company_id', $company_id)
                    ->where('year', $get_year)
                    ->get(['id','godown_id','quantity','value']);

                foreach ($openings as $o) {
                    if ($o->godown_id === null) continue;
                    $events[] = [
                        'type'        => 'opening',
                        'godown_id'   => (int)$o->godown_id,
                        'quantity'    => (float)$o->quantity,
                        'rate'        => (float)$o->value,
                        'date'        => '1970-01-01',
                        'source_id'   => (int)$o->id,
                        'source_type' => 'opening',
                    ];
                }

                // Purchases (lean join to include date)
                $purchases = DB::table('t_purchase_invoice_products as pip')
                    ->join('t_purchase_invoice as pi', 'pi.id', '=', 'pip.purchase_invoice_id')
                    ->where('pip.product_id', $productId)
                    ->whereBetween('pi.purchase_invoice_date', [$start_date, $end_date])
                    ->get(['pip.id','pip.godown','pip.quantity','pip.returned','pip.price','pip.purchase_invoice_id','pi.purchase_invoice_date']);

                foreach ($purchases as $p) {
                    $g = $p->godown;
                    if ($g === null) continue;
                    $netQty = (float)$p->quantity - (float)($p->returned ?? 0);
                    if ($netQty <= 0) continue;

                    $events[] = [
                        'type'        => 'purchase',
                        'godown_id'   => (int)$g,
                        'quantity'    => $netQty,
                        'rate'        => (float)$p->price,
                        'date'        => $p->purchase_invoice_date,
                        'source_id'   => (int)$p->id,
                        'source_type' => 'purchase',
                        'pi_id'       => (int)$p->purchase_invoice_id, // stash PI id on the layer
                    ];
                }

                // Sales (lean join to include date & taxes)
                $sales = DB::table('t_sales_invoice_products as sip')
                    ->join('t_sales_invoice as si', 'si.id', '=', 'sip.sales_invoice_id')
                    ->where('sip.product_id', $productId)
                    ->whereBetween('si.sales_invoice_date', [$start_date, $end_date])
                    ->get(['sip.id','sip.godown','sip.quantity','sip.returned','sip.amount','sip.cgst','sip.sgst','sip.igst','si.sales_invoice_date']);

                foreach ($sales as $s) {
                    $g = $s->godown;
                    if ($g === null) continue;
                    $netQty = (float)$s->quantity - (float)($s->returned ?? 0);
                    if ($netQty <= 0) continue;

                    $events[] = [
                        'type'        => 'sale',
                        'godown_id'   => (int)$g,
                        'quantity'    => $netQty,
                        'rate'        => null,
                        'date'        => $s->sales_invoice_date,
                        'source_id'   => (int)$s->id,
                        'source_type' => 'sale',
                        'amount'      => (float)($s->amount ?? 0),
                        'cgst'        => (float)($s->cgst ?? 0),
                        'sgst'        => (float)($s->sgst ?? 0),
                        'igst'        => (float)($s->igst ?? 0),
                    ];
                }

                // Assemblies: only those touching this product
                $assemblies = AssemblyOperationModel::where('company_id', $company_id)
                    ->whereBetween('assembly_operations_date', [$start_date, $end_date])
                    ->where(function($q) use ($productId) {
                        $q->where('product_id', $productId)
                        ->orWhereHas('products', fn($q2) => $q2->where('product_id', $productId));
                    })
                    ->with(['products' => function($q) use ($productId) {
                        $q->where('product_id', $productId)
                        ->select('id','assembly_operation_id','product_id','godown','quantity');
                    }])
                    ->get(['id','type','assembly_operations_date','product_id','godown','quantity','rate','amount']);

                foreach ($assemblies as $a) {
                    $atype = strtolower($a->type ?? '');
                    // component line(s) for this product
                    foreach ($a->products as $c) {
                        if ($c->godown === null) continue;
                        $qty = (float)$c->quantity * (float)$a->quantity;
                        $events[] = [
                            'type'        => ($atype === 'assemble') ? 'assembly_component_out' : 'assembly_component_in',
                            'godown_id'   => (int)$c->godown,
                            'quantity'    => $qty,
                            'rate'        => null,
                            'date'        => $a->assembly_operations_date,
                            'source_id'   => (int)$c->id,
                            'source_type' => 'assembly_product',
                        ];
                    }
                    // main product line if this product is produced/consumed
                    if ((int)$a->product_id === $productId && $a->godown !== null) {
                        $events[] = [
                            'type'        => ($atype === 'assemble') ? 'assembly_product_in' : 'assembly_product_out',
                            'godown_id'   => (int)$a->godown,
                            'quantity'    => (float)$a->quantity,
                            'rate'        => (float)($a->rate ?? 0),
                            'date'        => $a->assembly_operations_date,
                            'source_id'   => (int)$a->id,
                            'source_type' => 'assembly',
                        ];
                    }
                }

                // Transfers: move layers from from→to at same rates (no revaluation)
                $transfers = DB::table('t_stock_transfer_products as stp')
                    ->join('t_stock_transfer as st', 'st.id', '=', 'stp.stock_transfer_id')
                    ->where('stp.product_id', $productId)
                    ->whereBetween('st.transfer_date', [$start_date, $end_date])
                    ->get(['stp.id','st.godown_from','st.godown_to','stp.quantity','st.transfer_date']);

                foreach ($transfers as $t) {
                    if ($t->godown_from === null || $t->godown_to === null) continue;
                    $events[] = [
                        'type'        => 'transfer',
                        'godown_from' => (int)$t->godown_from,
                        'godown_to'   => (int)$t->godown_to,
                        'quantity'    => (float)$t->quantity,
                        'date'        => $t->transfer_date,
                        'source_id'   => (int)$t->id,
                        'source_type' => 'transfer',
                    ];
                }

                // 3) Sort by date ascending
                usort($events, fn($a,$b) => strtotime($a['date']) <=> strtotime($b['date']));

                // 4) FIFO per-godown + helpers
                $fifo = [];        // $fifo[$g] = [ ['qty','rate','type','id','pi_id?'], ... ]
                $godownStock = []; // running qty per godown

                $pushLayer = function (&$fifo, int $g, float $qty, float $rate, string $stype, int $sid, ?int $piId = null) {
                    if ($qty <= 0) return;
                    $fifo[$g] ??= [];
                    $fifo[$g][] = [
                        'qty'   => $qty,
                        'rate'  => $rate,
                        'type'  => $stype,     // 'purchase'|'opening'|'assembly'|'assembly_product'
                        'id'    => $sid,
                        'pi_id' => $piId,      // only for purchases
                    ];
                };

                $consumeFrom = function (&$fifo, int $g, float $qtyNeeded) {
                    $cost = 0.0; $rem = $qtyNeeded; $firstPiId = null;
                    while ($rem > 0 && !empty($fifo[$g])) {
                        $layer = &$fifo[$g][0];
                        $used  = min($layer['qty'], $rem);
                        $cost += $used * (float)$layer['rate'];

                        if ($layer['type'] === 'purchase' && !$firstPiId) {
                            $firstPiId = $layer['pi_id'] ?? null;
                        }

                        // mark sold back on source
                        switch ($layer['type']) {
                            case 'purchase':
                                PurchaseInvoiceProductsModel::where('id', $layer['id'])->increment('sold', $used);
                                break;
                            case 'opening':
                                OpeningStockModel::where('id', $layer['id'])->increment('sold', $used);
                                break;
                            case 'assembly_product':
                                AssemblyOperationProductsModel::where('id', $layer['id'])->increment('sold', $used);
                                break;
                            case 'assembly':
                                AssemblyOperationModel::where('id', $layer['id'])->increment('sold', $used);
                                break;
                        }

                        $layer['qty'] -= $used;
                        $rem -= $used;
                        if ($layer['qty'] <= 1e-9) array_shift($fifo[$g]);
                    }
                    return [$cost, $firstPiId, $rem]; // rem>0 means shortage
                };

                $transferLayers = function (&$fifo, int $gFrom, int $gTo, float $qty) use ($pushLayer) {
                    if ($qty <= 0) return 0.0;
                    $moved = 0.0; $rem = $qty;
                    while ($rem > 0 && !empty($fifo[$gFrom])) {
                        $layer = &$fifo[$gFrom][0];
                        $used  = min($layer['qty'], $rem);
                        // move same rate & source meta to destination
                        $pushLayer($fifo, $gTo, $used, (float)$layer['rate'], $layer['type'], (int)$layer['id'], $layer['pi_id'] ?? null);
                        $layer['qty'] -= $used;
                        $moved += $used;
                        $rem -= $used;
                        if ($layer['qty'] <= 1e-9) array_shift($fifo[$gFrom]);
                    }
                    return $moved;
                };

                // 5) Process events
                foreach ($events as $e) {
                    if ($e['type'] === 'transfer') {
                        $from = $e['godown_from']; $to = $e['godown_to']; $qty = (float)$e['quantity'];
                        $moved = $transferLayers($fifo, $from, $to, $qty);
                        $godownStock[$from] = ($godownStock[$from] ?? 0) - $moved;
                        $godownStock[$to]   = ($godownStock[$to] ?? 0) + $moved;
                        continue;
                    }

                    $g = $e['godown_id'] ?? null;
                    $q = (float)$e['quantity'];
                    $r = (float)($e['rate'] ?? 0);

                    switch ($e['type']) {
                        // inflows
                        case 'opening':
                        case 'purchase':
                        case 'assembly_product_in':
                        case 'assembly_component_in':
                            if ($g === null) break;
                            $pushLayer(
                                $fifo, (int)$g, $q, $r,
                                $e['source_type'] ?? 'other',
                                (int)$e['source_id'],
                                $e['pi_id'] ?? null
                            );
                            $godownStock[$g] = ($godownStock[$g] ?? 0) + $q;
                            break;

                        // sales (outflow with profit)
                        case 'sale':
                            if ($g === null) break;
                            $sale = SalesInvoiceProductsModel::find($e['source_id']);
                            if ($sale) {
                                [$cost, $firstPiId, $rem] = $consumeFrom($fifo, (int)$g, $q);
                                $profit = ($e['amount'] ?? 0)
                                    - ($e['cgst'] ?? 0) - ($e['sgst'] ?? 0) - ($e['igst'] ?? 0)
                                    - $cost;

                                $sale->purchase_rate = $cost;
                                $sale->purchase_invoice_id = $firstPiId;
                                $sale->profit = $profit;
                                $sale->save();

                                $godownStock[$g] = ($godownStock[$g] ?? 0) - $q;
                                // Optional: if $rem > 0, log shortage
                            }
                            break;

                        // other outflows (no profit calculation)
                        case 'assembly_product_out':
                        case 'assembly_component_out':
                            if ($g === null) break;
                            [, , $rem] = $consumeFrom($fifo, (int)$g, $q);
                            $godownStock[$g] = ($godownStock[$g] ?? 0) - $q;
                            // Optional: handle $rem > 0 (shortage)
                            break;
                    }
                }

                // 6) Write closing stock (per godown, valued)
                ClosingStockModel::where('company_id', $company_id)
                    ->where('year', $get_year)
                    ->where('product_id', $productId)
                    ->delete();

                foreach ($godownStock as $g => $qty) {
                    if ($qty <= 0) continue;

                    $value = 0.0;
                    if (!empty($fifo[$g])) {
                        foreach ($fifo[$g] as $layer) {
                            $lq = (float)($layer['qty'] ?? 0);
                            if ($lq > 0) $value += $lq * (float)($layer['rate'] ?? 0);
                        }
                    }

                    ClosingStockModel::create([
                        'company_id' => $company_id,
                        'year'       => $get_year,
                        'godown_id'  => (int)$g,
                        'product_id' => $productId,
                        'quantity'   => round((float)$qty, 2),
                        'value'      => round($value, 2),
                    ]);
                }

                // 7) Mark queue row complete
                DB::table('t_reset_queue')
                    ->where('id', $row->id)
                    ->update(['status' => '1', 'updated_at' => now()]);
            });
        }

        return response()->json([
            'success'   => true,
            'processed' => $processed,
            'message'   => "Processed $processed products. Cron can trigger again in 5 minutes."
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
