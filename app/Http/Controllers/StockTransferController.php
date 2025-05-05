<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\StockTransferModel;
use App\Models\StockTransferProductsModel;
use App\Models\ProductsModel;
use App\Models\GodownModel;
use Carbon\Carbon;
use Auth;

class StockTransferController extends Controller
{
    //
    // create
    public function add_stock_transfer(Request $request)
    {
        $request->validate([
            'godown_from' => 'required|integer|exists:t_godown,id',
            'godown_to' => 'required|integer|exists:t_godown,id',
            'transfer_date'  => 'required|date_format:Y-m-d',
            'remarks' => 'nullable|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.quantity' => 'required|integer',
            'products.*.description'  => 'nullable|string',
        ]);
    
        do {
            $transferNo  = rand(1111111111,9999999999);
            $exists = StockTransferModel::where('transfer_id', $transferNo )->exists();
        } while ($exists);

        $currentDate = Carbon::now()->toDateString();

        $register_stock_transfer = StockTransferModel::create([
            'transfer_id'   => $transferNo,
            'company_id'    => Auth::user()->company_id,
            'godown_from'   => $request->input('godown_from'),
            'godown_to'     => $request->input('godown_to'),
            'transfer_date' => $request->input('transfer_date'),
            'remarks'       => $request->input('remarks'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            $product_details = ProductsModel::where('id', $product['product_id'])
                                            ->where('company_id', Auth::user()->company_id)
                                            ->first();

            if ($product_details) 
            {
                StockTransferProductsModel::create([
                    'stock_transfer_id'   => $register_stock_transfer->id,
                    'company_id'    => Auth::user()->company_id,
                    'product_id'    => $product['product_id'],
                    'product_name'  => $product['product_name'],
                    'description'   => $product['description'] ?? null,
                    'quantity'      => $product['quantity'],
                ]);
            }

            else{
                return response()->json(['message' => 'Sorry, Products not found'], 404);
            }
        }

        unset($register_stock_transfer['id'], $register_stock_transfer['created_at'], $register_stock_transfer['updated_at']);
    
        return isset($register_stock_transfer) && $register_stock_transfer !== null
        ? response()->json(['code' => 201,'success' => true, 'message' => 'Stock Transfer registered successfully!', 'data' => $register_stock_transfer], 201)
        : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to register Stock Transfer record'], 400);
    }

    // view
    public function view_stock_transfer(Request $request, $id = null)
    {
        try {
            $companyId = Auth::user()->company_id;
            // Optional filters from request (LR number filter and product filters)
            $transferIdFilter = $request->input('transfer_id'); // master filter on transfer_id
            $productId = $request->input('product_id');          // filter for product_id in products
            $productName = $request->input('product_name');      // filter for product_name in products
            $productDesc = $request->input('product_desc');      // filter for product description
            $limit = $request->input('limit', 10);                // default limit 10
            $offset = $request->input('offset', 0);               // default offset 0

            // Build query on the master table with its products
            $query = StockTransferModel::with([
                'products' => function ($q) use ($productId, $productName, $productDesc) {
                    $q->select('stock_transfer_id', 'product_id', 'product_name', 'description', 'quantity');
                    if ($productId) {
                        $q->where('product_id', $productId);
                    }
                    if ($productName) {
                        $q->where('product_name', 'LIKE', '%' . $productName . '%');
                    }
                    if ($productDesc) {
                        $q->where('description', 'LIKE', '%' . $productDesc . '%');
                    }
                },
                'godownFrom:id,name',
                'godownTo:id,name'
            ])            
            ->select('id', 'transfer_id', 'godown_from', 'godown_to', 'transfer_date', 'remarks')
            ->where('company_id', $companyId);

            // If an ID is passed as a route parameter, fetch that specific record.
            if ($id) {
                $query->where('id', $id);
            } elseif ($transferIdFilter) {
                // Else if a transfer_id filter is provided in the request, apply it.
                $query->where('transfer_id', $transferIdFilter);
            }

            // Get total record count before applying limit
            $totalRecords = $query->count();
            // Apply pagination
            $query->offset($offset)->limit($limit);

            $stockTransfers = $query->get();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Stock Transfers fetched successfully!',
                'data' => $stockTransfers,
                'count' => $stockTransfers->count(),
                'total_records' => $totalRecords,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Error fetching Stock Transfers.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // update
    public function edit_stock_transfer(Request $request, $id)
    {
        $request->validate([
            'godown_from' => 'required|integer|exists:t_godown,id',
            'godown_to' => 'required|integer|exists:t_godown,id',
            'transfer_date'  => 'required|date_format:Y-m-d',
            'remarks' => 'nullable|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.quantity' => 'required|integer',
            'products.*.description'  => 'nullable|string',
        ]);

        // Fetch the stock transfer by ID
        $stockTransfer = StockTransferModel::where('id', $id)->first();

        // Update stock transfer details
        $stockTransferUpdated = $stockTransfer->update([
            'godown_from' => $request->input('godown_from'),
            'godown_to' => $request->input('godown_to'),
            'transfer_date' => $request->input('transfer_date'),
            'remarks' => $request->input('remarks'),
        ]);

        // Get the products from the request
        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            // Check if the product exists for this transfer_id
            $existingProduct = StockTransferProductsModel::where('stock_transfer_id', $id)
                                                        ->where('product_id', $productData['product_id'])
                                                        ->first();

            if ($existingProduct) {
                // Update the existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                ]);
            } else {
                // Create new product if it does not exist
                StockTransferProductsModel::create([
                    'stock_transfer_id' =>$id,
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                ]);
            }
        }

        // Delete products not in the request but in the database
        $productsDeleted = StockTransferProductsModel::where('stock_transfer_id', $id)
                                                    ->whereNotIn('product_id', $requestProductIDs)
                                                    ->delete();

        // Remove timestamps from the response for neatness
        unset($stockTransfer['created_at'], $stockTransfer['updated_at']);

        return ($stockTransferUpdated || $productsDeleted)
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Stock Transfer updated successfully!', 'data' => $stockTransfer], 200)
            : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    public function delete_stock_transfer($id)
    {
        // Fetch the transfer by ID
        $get_transfer_id = StockTransferModel::select('transfer_id', 'company_id')
                                            ->where('id', $id)
                                            ->first();

        if ($get_transfer_id && $get_transfer_id->company_id === Auth::user()->company_id) {
            // Delete the stock transfer
            $delete_stock_transfer = StockTransferModel::where('id', $id)->delete();

            // Delete associated products by transfer_id
            $delete_stock_transfer_products = StockTransferProductsModel::where('stock_transfer_id', $id)->delete();

            // Return success response if deletion was successful
            return $delete_stock_transfer && $delete_stock_transfer_products
                ? response()->json(['code' => 200,'success' => true, 'message' => 'Stock Transfer and associated products deleted successfully!'], 200)
                : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Stock Transfer or products.'], 400);
        } else {
            // Return error response if the stock transfer not found
            return response()->json(['code' => 404,'success' => false, 'message' => 'Stock Transfer not found.'], 404);
        }
    }

    public function importStockTransfers()
    {
        set_time_limit(300);

        // Clear old stock transfer data
        StockTransferModel::truncate();
        StockTransferProductsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/stock_transfer.php';

        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $companyId = Auth::user()->company_id;
        $successfulInserts = 0;
        $errors = [];

        $batchSize = 50; // You can adjust for performance

        $stockTransfersBatch = [];
        $productsDataMap = [];

        // Step 1: Prepare stock transfer records
        foreach ($data as $record) {
            $itemsData = json_decode($record['items'], true);

            if (!is_array($itemsData) || !isset($itemsData['product'], $itemsData['desc'], $itemsData['quantity'], $itemsData['unit'], $itemsData['status'])) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in items field.'];
                continue;
            }

            // Fetch Godown IDs
            $godownFromId = GodownModel::where('name', $record['from'] ?? '')
                                ->where('company_id', $companyId)
                                ->value('id');

            $godownToId = GodownModel::where('name', $record['to'] ?? '')
                                ->where('company_id', $companyId)
                                ->value('id');

            $stockTransfersBatch[] = [
                'transfer_id'    => $record['transfer_id'],
                'company_id'     => $companyId,
                'godown_from'    => $godownFromId,
                'godown_to'      => $godownToId,
                'transfer_date'  => !empty($record['t_date']) ? $record['t_date'] : now(),
                // 'status'         => $record['status'] ?? '0',
                // 'log_user'       => $record['log_user'] ?? 'Unknown',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // Store products data separately mapped to `transfer_id`
            $productsDataMap[$record['transfer_id']] = $itemsData;

            $successfulInserts++;
        }

        // Step 2: Insert stock transfers in batch
        foreach (array_chunk($stockTransfersBatch, $batchSize) as $chunk) {
            StockTransferModel::insert($chunk);
        }

        // Step 3: Fetch inserted stock transfers (transfer_id => id)
        $transferIdToStockTransferId = StockTransferModel::whereIn('transfer_id', array_keys($productsDataMap))
            ->pluck('id', 'transfer_id')
            ->toArray();

        $productsBatch = [];

        // Step 4: Prepare stock transfer products batch
        foreach ($productsDataMap as $transferId => $itemsData) {
            $stockTransferId = $transferIdToStockTransferId[$transferId] ?? null;

            if (!$stockTransferId) {
                $errors[] = ['transfer_id' => $transferId, 'error' => 'Stock Transfer ID not found after insert.'];
                continue;
            }

            foreach ($itemsData['product'] as $index => $productName) {
                $product = ProductsModel::where('name', $productName)->first();

                if (!$product) {
                    $errors[] = [
                        'transfer_id' => $transferId,
                        'error' => "Product '{$productName}' not found."
                    ];
                    continue;
                }

                $productsBatch[] = [
                    'stock_transfer_id' => $stockTransferId, // ✅ Now correct foreign key
                    'company_id'        => $companyId,
                    'product_id'        => $product->id,
                    'product_name'      => $itemsData['product_name'][$index] ?? $productName,
                    'description'       => $itemsData['desc'][$index] ?? 'No Description',
                    'quantity'          => isset($itemsData['quantity'][$index]) ? (int) $itemsData['quantity'][$index] : 0,
                    // 'unit'              => $itemsData['unit'][$index] ?? 'PCS',
                    // 'status'            => $itemsData['status'][$index] ?? '1',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }
        }

        // Step 5: Batch Insert products
        foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
            StockTransferProductsModel::insert($chunk);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Import completed with {$successfulInserts} successful Stock Transfers.",
            'errors' => $errors,
        ], 200);
    }

    // fetch by product id
    public function fetchStockTransfersByProduct(Request $request, $productId)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Inputs
            $sortField = $request->input('sort_field', 'date');
            $sortOrder = strtolower($request->input('sort_order', 'asc'));
            $limit = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $searchTransferId = $request->input('transfer_id');

            $validFields = ['transfer_id', 'date', 'quantity', 'from', 'to'];
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

            // Fetch all transfers
            $records = StockTransferProductsModel::with([
                    'stockTransfer:id,transfer_id,transfer_date,godown_from,godown_to',
                    'stockTransfer.godownFrom:id,name',
                    'stockTransfer.godownTo:id,name',
                ])
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->select('stock_transfer_id', 'product_id', 'quantity')
                ->get()
                ->map(function ($item) {
                    return [
                        'transfer_id' => optional($item->stockTransfer)->transfer_id,
                        'date'        => optional($item->stockTransfer)->transfer_date,
                        'quantity'    => (float) $item->quantity,
                        'from'        => optional($item->stockTransfer->godownFrom)->name,
                        'to'          => optional($item->stockTransfer->godownTo)->name,
                    ];
                })
                ->toArray();

            // Filter by transfer_id
            if (!empty($searchTransferId)) {
                $records = array_filter($records, fn($r) =>
                    stripos((string) $r['transfer_id'], $searchTransferId) !== false
                );
            }

            // Sort
            usort($records, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc'
                    ? $a[$sortField] <=> $b[$sortField]
                    : $b[$sortField] <=> $a[$sortField];
            });

            // Total (before pagination)
            $totalQty = array_sum(array_column($records, 'quantity'));
            $totalRecords = count($records);

            // Pagination
            $paginated = array_slice($records, $offset, $limit);
            $subQty = array_sum(array_column($paginated, 'quantity'));

            // Response
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
                'sub_total' => [
                    'quantity' => $subQty,
                ],
                'total' => [
                    'quantity' => $totalQty,
                ]
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error fetching stock transfers: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0
            ], 500);
        }
    }
}
