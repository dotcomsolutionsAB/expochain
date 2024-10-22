<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;

class AssemblyOperationsController extends Controller
{
    //
    // create
    public function add_assembly_operations(Request $request)
    {
        $request->validate([
            'assembly_operations_id' => 'required|integer',
            'assembly_operations_date' => 'required|date',
            'type' => 'required|in:assemble,de-assemble',
            'product_id' => 'required|integer',
            'product_name' => 'required|string',
            'quantity' => 'required|integer',
            'godown' => 'required|integer',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.rate' => 'required|numeric',
            'products.*.godown' => 'required|integer',
            'products.*.amount' => 'required|numeric',
        ]);
    
        $assembly_operations_id = rand(1111111111,9999999999);

        $register_assembly_operations = AssemblyOperationModel::create([
            'assembly_operations_id' => $assembly_operations_id,
            'assembly_operations_date' => $request->input('assembly_operations_date'),
            'type' => $request->input('type'),
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'quantity' => $request->input('quantity'),
            'godown' => $request->input('godown'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'log_user' => $request->input('log_user')
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            AssemblyOperationProductsModel::create([
                'assembly_operations_id' => $assembly_operations_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'quantity' => $product['quantity'],
                'rate' => $product['rate'],
                'godown' => $product['godown'],
                'amount' => $product['amount'],
            ]);
        }

        unset($register_assembly_operations['id'], $register_assembly_operations['created_at'], $register_assembly_operations['updated_at']);
    
        return isset($register_assembly_operations) && $register_assembly_operations !== null
        ? response()->json(['Assembly Operations records registered successfully!', 'data' => $register_assembly_operations], 201)
        : response()->json(['Failed to register Assembly Operations records'], 400);
    }
}
