<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\StockTransferModel;
use App\Models\StockTransferProductsModel;
use App\Models\ProductsModel;
use Auth;

class StockTransferController extends Controller
{
    //
    // create
    public function add_stock_transfer(Request $request)
    {
        $request->validate([
            'godown_from' => 'required|string',
            'godown_to' => 'required|string',
            'transfer_date' => 'required|date',
            'status' => 'required|string',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.status' => 'required|string',
        ]);
    
        $transfer_id = rand(1111111111,9999999999);

        $register_stock_transfer = StockTransferModel::create([
            'transfer_id' => $transfer_id,
            'company_id' => Auth::user()->company_id,
            'godown_from' => $request->input('godown_from'),
            'godown_to' => $request->input('godown_to'),
            'transfer_date' => $request->input('transfer_date'),
            'status' => $request->input('status'),
            'log_user' => $request->input('log_user'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            StockTransferProductsModel::create([
                'transfer_id' => $transfer_id,
                'company_id' => Auth::user()->company_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'quantity' => $product['quantity'],
                'unit' => $product['unit'],
                'status' => $product['status'],
            ]);
        }

        unset($register_stock_transfer['id'], $register_stock_transfer['created_at'], $register_stock_transfer['updated_at']);
    
        return isset($register_stock_transfer) && $register_stock_transfer !== null
        ? response()->json(['Stock Transfer registered successfully!', 'data' => $register_stock_transfer], 201)
        : response()->json(['Failed to register Stock Transfer record'], 400);
    }

    // view
    public function view_stock_transfer()
    {
        $get_stock_transfers = StockTransferModel::with(['products' => function ($query)
        {
            $query->select('transfer_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'status');
        }])
        ->select('transfer_id', 'godown_from', 'godown_to', 'transfer_date', 'status', 'log_user')
        ->get();

        return isset($get_stock_transfers) && $get_stock_transfers->isNotEmpty()
            ? response()->json(['Stock Transfers fetched successfully!', 'data' => $get_stock_transfers], 200)
            : response()->json(['Failed to fetch Stock Transfers data'], 404);
    }

    // update
    public function edit_stock_transfer(Request $request, $id)
    {
        $request->validate([
            'transfer_id' => 'required|integer',
            'godown_from' => 'required|string',
            'godown_to' => 'required|string',
            'transfer_date' => 'required|date',
            'status' => 'required|string',
            'log_user' => 'required|string',
            'products' => 'required|array',
            'products.*.transfer_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.status' => 'required|string',
        ]);

        // Fetch the stock transfer by ID
        $stockTransfer = StockTransferModel::where('transfer_id', $request->input('transfer_id'))->first();

        // Update stock transfer details
        $stockTransferUpdated = $stockTransfer->update([
            'godown_from' => $request->input('godown_from'),
            'godown_to' => $request->input('godown_to'),
            'transfer_date' => $request->input('transfer_date'),
            'status' => $request->input('status'),
            'log_user' => $request->input('log_user'),
        ]);

        // Get the products from the request
        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            // Check if the product exists for this transfer_id
            $existingProduct = StockTransferProductsModel::where('transfer_id', $productData['transfer_id'])
                                                        ->where('product_id', $productData['product_id'])
                                                        ->first();

            if ($existingProduct) {
                // Update the existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'status' => $productData['status'],
                ]);
            } else {
                // Create new product if it does not exist
                StockTransferProductsModel::create([
                    'transfer_id' =>$productData['transfer_id'],
                    'company_id' => Auth::user()->company_id,
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'status' => $productData['status'],
                ]);
            }
        }

        // Delete products not in the request but in the database
        $productsDeleted = StockTransferProductsModel::where('transfer_id', $id)
                                                    ->where('product_id', $requestProductIDs)
                                                    ->delete();

        // Remove timestamps from the response for neatness
        unset($stockTransfer['created_at'], $stockTransfer['updated_at']);

        return ($stockTransferUpdated || $productsDeleted)
            ? response()->json(['message' => 'Stock Transfer updated successfully!', 'data' => $stockTransfer], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    public function delete_stock_transfer($id)
    {
        // Fetch the transfer by ID
        $get_transfer_id = StockTransferModel::select('transfer_id')
                                            ->where('id', $id)
                                            ->first();

        if ($get_transfer_id) {
            // Delete the stock transfer
            $delete_stock_transfer = StockTransferModel::where('id', $id)->delete();

            // Delete associated products by transfer_id
            $delete_stock_transfer_products = StockTransferProductsModel::where('transfer_id', $get_transfer_id->transfer_id)->delete();

            // Return success response if deletion was successful
            return $delete_stock_transfer && $delete_stock_transfer_products
                ? response()->json(['message' => 'Stock Transfer and associated products deleted successfully!'], 200)
                : response()->json(['message' => 'Failed to delete Stock Transfer or products.'], 400);
        } else {
            // Return error response if the stock transfer not found
            return response()->json(['message' => 'Stock Transfer not found.'], 404);
        }
    }

    public function importStockTransfers()
    {
        set_time_limit(300);

        // Clear the StockTransfer and related tables
        StockTransferModel::truncate();
        StockTransferProductsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/stock_transfer.php'; // Replace with the actual URL

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

        $successfulInserts = 0;
        $errors = [];

        foreach ($data as $record) {
            // Decode JSON fields for items
            $itemsData = json_decode($record['items'], true);
            // print_r($itemsData);
            // echo "mmm";
            // $a = (!is_array($itemsData['id']));
            // print_r($a);

            if (!is_array($itemsData) || !isset($itemsData['product'], $itemsData['desc'], $itemsData['quantity'], $itemsData['unit'], $itemsData['status'])) {
                $errors[] = ['record' => $record, 'error' => 'Invalid JSON structure in items field.'];
                continue;
            }

            // Prepare Stock Transfer data
            $stockTransferData = [
                'transfer_id' => $record['transfer_id'],
                'godown_from' => $record['from'] ?? 'Unknown',
                'godown_to' => $record['to'] ?? 'Unknown',
                'transfer_date' => !empty($record['t_date']) ? $record['t_date'] : now(),
                'status' => $record['status'] ?? '0',
                'log_user' => $record['log_user'] ?? 'Unknown',
            ];

            // Validate Stock Transfer data
            $validator = Validator::make($stockTransferData, [
                'transfer_id' => 'required|integer',
                'godown_from' => 'required|string',
                'godown_to' => 'required|string',
                'transfer_date' => 'required|date',
                'status' => 'required|string',
                'log_user' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                $stockTransfer = StockTransferModel::create($stockTransferData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert Stock Transfer: ' . $e->getMessage()];
                continue;
            }

            // Insert associated products
            foreach ($itemsData['product'] as $index => $productName) {

                // Fetch the product ID from the ProductsModel
                $product = ProductsModel::where('name', $productName)->first();

                if (!$product) {
                    $errors[] = [
                        'record' => $record,
                        'error' => "Product with name '{$productName}' not found."
                    ];
                    continue; // Skip this product if not found
                }

                try {
                    StockTransferProductsModel::create([
                        'transfer_id' => $record['transfer_id'],
                        'product_id' => $product->id,
                        'product_name' => $itemsData['product_name'][$index] ?? 'Unnamed Product',
                        'description' => $itemsData['desc'][$index] ?? 'No Description',
                        'quantity' => (int) $itemsData['quantity'][$index] ?? 0,
                        'unit' => $itemsData['unit'][$index] ?? 'PCS',
                        'status' => $itemsData['status'][$index] ?? '1',
                    ]);
                } catch (\Exception $e) {
                    $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
                }
            }
        }

        return response()->json([
            'message' => "Import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
