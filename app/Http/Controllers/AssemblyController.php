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

    // view
    public function assembly()
    {        
        $get_assembly = AssemblyModel::with(['products' => function ($query)
        {
            $query->select('assembly_id','product_id','product_name','quantity','log_user');
        }])
        ->select('assembly_id','product_id','product_name','quantity','log_user')->get();
        

        return isset($get_assembly) && $get_assembly !== null
        ? response()->json(['Assembly record fetch successfully!', 'data' => $get_assembly], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function update_assembly_operations(Request $request)
    {
        $request->validate([
            'assembly_id' => 'required|integer',
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

        // Fetch the assembly record by ID
        $assembly = AssemblyModel::where('assembly_id', $request->input('assembly_id'))->firstOrFail();

        // Update the assembly operation details
        $assemblyUpdated = $assembly->update([
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'quantity' => $request->input('quantity'),
            'log_user' => $request->input('log_user'),
        ]);

        // Get the list of products from the request
        $products = $request->input('products');
        $requestProductIDs = [];

        // Loop through each product in the request
        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            // Check if the product exists for this assembly_operations_id and product_id
            $existingProduct = AssemblyProductsModel::where('assembly_id', $request->input('assembly_id'))
                                                            ->where('product_id', $productData['product_id'])
                                                            ->first();

            if ($existingProduct) {
                // Update the existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'quantity' => $productData['quantity'],
                    'rate' => $productData['rate'] ?? null,
                    'godown' => $productData['godown'] ?? null,
                    'amount' => $productData['amount'] ?? null,
                ]);
            } else {
                // Create a new product if not exists
                AssemblyProductsModel::create([
                    'assembly_operations_id' => $request->input('assembly_id'),
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'quantity' => $productData['quantity'],
                    'log_user' => $productData['log_user'],
                                    
                ]);
            }
        }

        // Delete products that are not in the request but exist in the database for this assembly_operations_id
        $productsDeleted = AssemblyProductsModel::where('assembly_id', $request->input('assembly_id'))
                                                        ->whereNotIn('product_id', $requestProductIDs)
                                                        ->delete();

        // Remove timestamps from the response for neatness
        unset($assembly['created_at'], $assembly['updated_at']);

        return ($assemblyUpdated || $productsDeleted)
            ? response()->json(['message' => 'Assembly and products updated successfully!', 'data' => $assembly], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }


    // delete
    public function delete_assembly($id)
    {
        // Try to find the client by the given ID
        $get_assembly_id = AssemblyModel::select('assembly_id')
                                        ->where('id', $id)
                                        ->first();
        
        // Check if the client exists

        if ($get_assembly_id) 
        {
            // Delete the client
            $delete_assembly = AssemblyModel::where('id', $id)->delete();

            // Delete associated contacts by customer_id
            $delete_assembly_products = AssemblyProductsModel::where('assembly_id', $get_assembly_id->assembly_id)->delete();

            // Return success response if deletion was successful
            return $delete_assembly && $delete_assembly_products
            ? response()->json(['message' => 'Assembly and associated products deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete Assembly or products.'], 400);

        } 
        else 
        {
            // Return error response if client not found
            return response()->json(['message' => 'Assembly not found.'], 404);
        }
    }
}
