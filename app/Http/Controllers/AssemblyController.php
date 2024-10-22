<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssemblyModel;
use App\Models\AssemblyProductsModel;

class AssemblyController extends Controller
{
    //
    // create
    public function add_assembly(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'product_name' => 'required|string',
            'quantity' => 'required|integer',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.log_user' => 'required|string',
        ]);
    
        $assembly_id = rand(1111111111,9999999999);

        $register_assembly = AssemblyModel::create([
            'assembly_id' => $assembly_id,
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'quantity' => $request->input('quantity'),
            'log_user' => $request->input('log_user'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            AssemblyProductsModel::create([
                'assembly_id' => $assembly_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'quantity' => $product['quantity'],
                'log_user' => $product['log_user'],
            ]);
        }

        unset($register_assembly['id'], $register_assembly['created_at'], $register_assembly['updated_at']);
    
        return isset($register_assembly) && $register_assembly !== null
        ? response()->json(['Assembly records registered successfully!', 'data' => $register_assembly], 201)
        : response()->json(['Failed to register Assembly records'], 400);
    }
}
