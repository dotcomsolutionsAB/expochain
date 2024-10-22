<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockTransferModel;
use App\Models\StockTransferProductsModel;

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
    public function view_stock_transfers()
    {
        $get_stock_transfers = StockTransferModel::with(['products' => function ($query)
        {
            $query->select('transfer_id', 'product_id', 'product_name', 'description', 'quantity', 'unit', 'status');
        }])
        ->select('transfer_id', 'godown_from', 'godown_to', 'transfer_date', 'status', 'log_user')
        ->get();

        return isset($get_stock_transfers) && $get_stock_transfers !== null
            ? response()->json(['Stock Transfers fetched successfully!', 'data' => $get_stock_transfers], 200)
            : response()->json(['Failed to fetch Stock Transfers data'], 404);
    }

    // update
    public function update_stock_transfer(Request $request, $id)
    {
        $request->validate([
            'transfer_id' => 'required|integer',
            'godown_from' => 'required|string',
            'godown_to' => 'required|string',
            'transfer_date' => 'required|date',
            'status' => 'required|string',
            'log_user' => 'required|string',
            'products' => 'required|array',
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
            $existingProduct = StockTransferProductsModel::where('transfer_id', $request->input('transfer_id'))
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
                    'transfer_id' => $request->input('transfer_id'),
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
        $productsDeleted = StockTransferProductsModel::where('transfer_id', $request->input('transfer_id'))
                                                    ->whereNotIn('product_id', $requestProductIDs)
                                                    ->delete();

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
}
