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

    // public function importStockTransfers()
    // {
    //     set_time_limit(300);

    //     // Clear the StockTransfer and related tables
    //     StockTransferModel::truncate();
    //     StockTransferProductsModel::truncate();

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/stock_transfer.php'; // Replace with the actual URL

    //     try {
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $successfulInserts = 0;
    //     $errors = [];

    //     foreach ($data as $record) {
    //         // Decode JSON fields for items
    //         $itemsData = json_decode($record['items'], true);
    //         // print_r($itemsData);
    //         // echo "mmm";
    //         // $a = (!is_array($itemsData['id']));
    //         // print_r($a);

    //         if (!is_array($itemsData) || !isset($itemsData['product'], $itemsData['desc'], $itemsData['quantity'], $itemsData['unit'], $itemsData['status'])) {
    //             $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in items field.'];
    //             continue;
    //         }

    //         // Prepare Stock Transfer data
    //         $stockTransferData = [
    //             'transfer_id' => $record['transfer_id'],
    //             'godown_from' => $record['from'] ?? 'Unknown',
    //             'godown_to' => $record['to'] ?? 'Unknown',
    //             'transfer_date' => !empty($record['t_date']) ? $record['t_date'] : now(),
    //             'status' => $record['status'] ?? '0',
    //             'log_user' => $record['log_user'] ?? 'Unknown',
    //         ];

    //         // Validate Stock Transfer data
    //         $validator = Validator::make($stockTransferData, [
    //             'transfer_id' => 'required|integer',
    //             'godown_from' => 'required|string',
    //             'godown_to' => 'required|string',
    //             'transfer_date' => 'required|date',
    //             'status' => 'required|string',
    //             'log_user' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             $errors[] = ['record' => $record, 'errors' => $validator->errors()];
    //             continue;
    //         }

    //         try {
    //             $stockTransfer = StockTransferModel::create($stockTransferData);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert Stock Transfer: ' . $e->getMessage()];
    //             continue;
    //         }

    //         // Insert associated products
    //         foreach ($itemsData['product'] as $index => $productName) {

    //             // Fetch the product ID from the ProductsModel
    //             $product = ProductsModel::where('name', $productName)->first();

    //             if (!$product) {
    //                 $errors[] = [
    //                     'record' => $record,
    //                     'error' => "Product with name '{$productName}' not found."
    //                 ];
    //                 continue; // Skip this product if not found
    //             }

    //             try {
    //                 StockTransferProductsModel::create([
    //                     'transfer_id' => $record['transfer_id'],
    //                     'product_id' => $product->id,
    //                     'product_name' => $itemsData['product_name'][$index] ?? 'Unnamed Product',
    //                     'description' => $itemsData['desc'][$index] ?? 'No Description',
    //                     'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
    //                     'unit' => $itemsData['unit'][$index] ?? 'PCS',
    //                     'status' => $itemsData['status'][$index] ?? '1',
    //                 ]);
    //             } catch (\Exception $e) {
    //                 $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
    //             }
    //         }
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Import completed with $successfulInserts successful inserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    // public function importStockTransfers()
    // {
    //     set_time_limit(300);

    //     // Clear the StockTransfer and related tables
    //     StockTransferModel::truncate();
    //     StockTransferProductsModel::truncate();

    //     $url = 'https://expo.egsm.in/assets/custom/migrate/stock_transfer.php';

    //     try {
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $successfulInserts = 0;
    //     $errors = [];

    //     $batchStockTransfers = [];
    //     $batchStockTransferProducts = [];

    //     $companyId = Auth::user()->company_id; // ✅ cache company_id

    //     foreach ($data as $record) {

    //         $itemsData = json_decode($record['items'], true);

    //         if (!is_array($itemsData) || !isset($itemsData['product'], $itemsData['desc'], $itemsData['quantity'], $itemsData['unit'], $itemsData['status'])) {
    //             $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in items field.'];
    //             continue;
    //         }

    //         // 🔵 Fetch Godown From ID
    //         $godownFrom = GodownModel::where('name', $record['from'] ?? '')
    //                         ->where('company_id', $companyId)
    //                         ->value('id');

    //         // 🔵 Fetch Godown To ID
    //         $godownTo = GodownModel::where('name', $record['to'] ?? '')
    //                         ->where('company_id', $companyId)
    //                         ->value('id');

    //         $batchStockTransfers[] = [
    //             'transfer_id'   => $record['transfer_id'],
    //             'company_id'    => $companyId,
    //             'godown_from'   => $godownFrom ?? null,
    //             'godown_to'     => $godownTo ?? null,
    //             'transfer_date' => !empty($record['t_date']) ? $record['t_date'] : now(),
    //             // 'status'        => $record['status'] ?? '0',
    //             // 'log_user'      => $record['log_user'] ?? 'Unknown',
    //             'created_at'    => now(),
    //             'updated_at'    => now(),
    //         ];

    //         $successfulInserts++;

    //         foreach ($itemsData['product'] as $index => $productName) {

    //             $product = ProductsModel::where('name', $productName)->first();

    //             if (!$product) {
    //                 $errors[] = [
    //                     'record' => $record,
    //                     'error' => "Product with name '{$productName}' not found."
    //                 ];
    //                 continue;
    //             }

    //             $batchStockTransferProducts[] = [
    //                 'transfer_id'   => $record['transfer_id'],
    //                 'company_id'    => $companyId,
    //                 'product_id'    => $product->id,
    //                 'product_name'  => $itemsData['product_name'][$index] ?? $productName,
    //                 'description'   => $itemsData['desc'][$index] ?? 'No Description',
    //                 'quantity'      => isset($itemsData['quantity'][$index]) ? (int)$itemsData['quantity'][$index] : 0,
    //                 // 'unit'          => $itemsData['unit'][$index] ?? 'PCS',
    //                 // 'status'        => $itemsData['status'][$index] ?? '1',
    //                 'created_at'    => now(),
    //                 'updated_at'    => now(),
    //             ];
    //         }
    //     }

    //     // Batch Insert Stock Transfers
    //     if (!empty($batchStockTransfers)) {
    //         StockTransferModel::insert($batchStockTransfers);
    //     }

    //     // 🔵 Batch Insert Stock Transfer Products
    //     if (!empty($batchStockTransferProducts)) {
    //         StockTransferProductsModel::insert($batchStockTransferProducts);
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Import completed with {$successfulInserts} successful Stock Transfers.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    // public function importStockTransfers()
    // {
    //     set_time_limit(300);

    //     // Clear old stock transfer data
    //     StockTransferModel::truncate();
    //     StockTransferProductsModel::truncate();

    //     $url = 'https://expo.egsm.in/assets/custom/migrate/stock_transfer.php';

    //     try {
    //         $response = Http::timeout(120)->get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $companyId = Auth::user()->company_id;
    //     $successfulInserts = 0;
    //     $errors = [];

    //     $batchSize = 50; // You can adjust

    //     $stockTransfersBatch = [];
    //     $productsBatch = [];

    //     $transferIdMap = []; // Map transfer_id => data for later linking

    //     // Step 1: Prepare stock transfer records
    //     foreach ($data as $record) {
    //         $itemsData = json_decode($record['items'], true);

    //         if (!is_array($itemsData) || !isset($itemsData['product'], $itemsData['desc'], $itemsData['quantity'], $itemsData['unit'], $itemsData['status'])) {
    //             $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in items field.'];
    //             continue;
    //         }

    //         // Fetch Godown IDs
    //         $godownFromId = GodownModel::where('name', $record['from'] ?? '')
    //                             ->where('company_id', $companyId)
    //                             ->value('id');

    //         $godownToId = GodownModel::where('name', $record['to'] ?? '')
    //                             ->where('company_id', $companyId)
    //                             ->value('id');

    //         $stockTransfersBatch[] = [
    //             'transfer_id'    => $record['transfer_id'],
    //             'company_id'     => $companyId,
    //             'godown_from'    => $godownFromId,
    //             'godown_to'      => $godownToId,
    //             'transfer_date'  => !empty($record['t_date']) ? $record['t_date'] : now(),
    //             // 'status'         => $record['status'] ?? '0',
    //             // 'log_user'       => $record['log_user'] ?? 'Unknown',
    //             'created_at'     => now(),
    //             'updated_at'     => now(),
    //         ];

    //         // Store raw item data mapped with transfer_id
    //         $transferIdMap[$record['transfer_id']] = $itemsData;

    //         $successfulInserts++;
    //     }

    //     // Step 2: Insert stock transfers in batch
    //     foreach (array_chunk($stockTransfersBatch, $batchSize) as $chunk) {
    //         StockTransferModel::insert($chunk);
    //     }

    //     // Step 3: Fetch newly inserted Stock Transfer IDs if needed (Not needed here because `transfer_id` is custom, not auto_increment)

    //     // Step 4: Prepare products batch
    //     foreach ($transferIdMap as $transferId => $itemsData) {
    //         foreach ($itemsData['product'] as $index => $productName) {
    //             $product = ProductsModel::where('name', $productName)->first();

    //             if (!$product) {
    //                 $errors[] = [
    //                     'transfer_id' => $transferId,
    //                     'error' => "Product '{$productName}' not found."
    //                 ];
    //                 continue;
    //             }

    //             $productsBatch[] = [
    //                 'stock_transfer_id'   => $transferId,
    //                 'company_id'    => $companyId,
    //                 'product_id'    => $product->id,
    //                 'product_name'  => $itemsData['product_name'][$index] ?? $productName,
    //                 'description'   => $itemsData['desc'][$index] ?? 'No Description',
    //                 'quantity'      => isset($itemsData['quantity'][$index]) ? (int) $itemsData['quantity'][$index] : 0,
    //                 // 'unit'          => $itemsData['unit'][$index] ?? 'PCS',
    //                 // 'status'        => $itemsData['status'][$index] ?? '1',
    //                 'created_at'    => now(),
    //                 'updated_at'    => now(),
    //             ];
    //         }
    //     }

    //     // Step 5: Insert products in batch
    //     foreach (array_chunk($productsBatch, $batchSize) as $chunk) {
    //         StockTransferProductsModel::insert($chunk);
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Import completed with {$successfulInserts} stock transfers successfully imported.",
    //         'errors' => $errors,
    //     ], 200);
    // }
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
                'status'         => $record['status'] ?? '0',
                'log_user'       => $record['log_user'] ?? 'Unknown',
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
                    'unit'              => $itemsData['unit'][$index] ?? 'PCS',
                    'status'            => $itemsData['status'][$index] ?? '1',
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

}
