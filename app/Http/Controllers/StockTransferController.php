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
}
