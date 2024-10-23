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

    // view
    public function assembly_operations()
    {        
        $get_assembly_operations = AssemblyOperationModel::with(['products' => function ($query)
        {
            $query->select('assembly_operations_id','product_id','product_name','quantity','rate','godown','amount');
        }])
        ->select('assembly_operations_id','assembly_operations_date','type','product_id','product_name','quantity','godown','rate','amount')->get();

        return isset($get_assembly_operations) && $get_assembly_operations->isNotEmpty()
        ? response()->json(['Assembly Operations data successfully!', 'data' => $get_assembly_operations], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_assembly_operations(Request $request)
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
            'products.*.assembly_operations_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.rate' => 'required|numeric',
            'products.*.godown' => 'required|integer',
            'products.*.amount' => 'required|numeric',
        ]);

        // Get the assembly operation record by ID
        $assemblyOperation = AssemblyOperationModel::where('assembly_operations_id', $request->input('assembly_operations_id'))->first();

        // Update the assembly operation details
        $assemblyUpdated = $assemblyOperation->update([
            'assembly_operations_date' => $request->input('assembly_operations_date'),
            'type' => $request->input('type'),
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'quantity' => $request->input('quantity'),
            'godown' => $request->input('godown'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'log_user' => $request->input('log_user'),
        ]);

        // Get the list of products from the request
        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            // Check if the product exists for this assembly_operations_id and product_id
            $existingProduct = AssemblyOperationProductsModel::where('assembly_operations_id',  $productData['assembly_operations_id'])
                                                            ->where('product_id', $productData['product_id'])
                                                            ->first();

            if ($existingProduct) {
                // Update the existing product
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'quantity' => $productData['quantity'],
                    'rate' => $productData['rate'],
                    'godown' => $productData['godown'],
                    'amount' => $productData['amount'],
                ]);
            } else {
                // Create a new product if not exists
                AssemblyOperationProductsModel::create([
                    'assembly_operations_id' => $productData['assembly_operations_id'],
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'quantity' => $productData['quantity'],
                    'rate' => $productData['rate'],
                    'godown' => $productData['godown'],
                    'amount' => $productData['amount'],
                ]);
            }
        }

            // Delete products that are not in the request but exist in the database for this assembly_operations_id
            $productsDeleted = AssemblyOperationProductsModel::where('assembly_operations_id', $id)
                                                            ->where('product_id', $requestProductIDs)
                                                            ->delete();

            // Remove timestamps from the response for neatness
            unset($assemblyOperation['created_at'], $assemblyOperation['updated_at']);

            return ($assemblyUpdated || $productsDeleted)
                ? response()->json(['message' => 'Assembly operation and products updated successfully!', 'data' => $assemblyOperation], 200)
                : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_assembly_operations($id)
    {
        // Try to find the client by the given ID
        $get_assembly_operations_id = AssemblyOperationModel::select('assembly_operations_id')
                                        ->where('id', $id)
                                        ->first();
        
        // Check if the client exists

        if ($get_assembly_operations_id) 
        {
            // Delete the client
            $delete_assembly_operations = AssemblyOperationModel::where('id', $id)->delete();

            // Delete associated contacts by customer_id
            $delete_assembly_operations_products = AssemblyOperationProductsModel::where('assembly_operations_id', $get_assembly_operations_id->assembly_operations_id)->delete();

            // Return success response if deletion was successful
            return $delete_assembly_operations && $delete_assembly_operations_products
            ? response()->json(['message' => 'Assembly Operations and associated products deleted successfully!'], 200)
            : response()->json(['message' => 'Failed to delete Assembly Operations or products.'], 400);

        } 
        else 
        {
            // Return error response if client not found
            return response()->json(['message' => 'Assembly Operations not found.'], 404);
        }
    }
}
