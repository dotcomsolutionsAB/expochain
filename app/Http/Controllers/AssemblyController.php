<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\AssemblyModel;
use App\Models\AssemblyProductsModel;
use App\Models\ProductsModel;
use Auth;

class AssemblyController extends Controller
{
    //
    // create
    public function add_assembly(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:t_products,id',
            'product_name' => 'required|string|exists:t_products,name',
            // 'godown' => 'required|integer|exists:t_godown,id',
            // 'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.quantity' => 'required|integer|min:1',
            // 'products.*.rate' => 'required|integer|min:1',
            // 'products.*.godown' => 'required|integer|exists:t_godown,id',
            // 'products.*.log_user' => 'required|string',
        ]);
    
        do{
            $assembly_id = rand(1111111111,9999999999);

            $exists = AssemblyModel::where('assembly_id', $assembly_id)->exists();
        }while ($exists);

        if (AssemblyModel::where('product_id', $request->input('product_id'))->exists()) {
            return response()->json([
                'code' => 422,
                'success' => false,
                'message' => 'Assembly for this product already exists.'
            ], 422);
        }

        $register_assembly = AssemblyModel::create([
            'assembly_id' => $assembly_id,
            'company_id' => Auth::user()->company_id,
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            // 'godown' => $request->input('godown'),
            // 'log_user' => $request->input('log_user'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            AssemblyProductsModel::create([
                'assembly_id' => $assembly_id,
                'company_id' => Auth::user()->company_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'quantity' => $product['quantity'],
                // 'rate' => $product['rate'],
                // 'godown' => $product['godown'],
                // 'log_user' => $product['log_user'],
            ]);
        }

        unset($register_assembly['id'], $register_assembly['created_at'], $register_assembly['updated_at']);
    
        return isset($register_assembly) && $register_assembly !== null
        ? response()->json(['code' => 201,'success' => true, 'message' => 'Assembly records registered successfully!', 'data' => $register_assembly], 201)
        : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to register Assembly records'], 400);
    }

    // view
    // public function view_assembly()
    // {        
    //     $get_assembly = AssemblyModel::with(['products' => function ($query)
    //     {
    //         $query->select('assembly_id','product_id','product_name','quantity','log_user');
    //     }])
    //     ->select('assembly_id','product_id','product_name','quantity','log_user')
    //     ->where('company_id',Auth::user()->company_id) 
    //     ->get();
        

    //     return isset($get_assembly) && $get_assembly->isNotEmpty()
    //     ? response()->json(['Assembly record fetch successfully!', 'data' => $get_assembly, 'count' => count($get_assembly)], 200)
    //     : response()->json(['Failed to fetch data'], 404); 
    // }

    public function view_assembly(Request $request, $id = null)
    {
        // Get filter inputs
        $assemblyId = $request->input('assembly_id');
        $productId = $request->input('product_id');
        $productName = $request->input('product_name');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = AssemblyModel::with(['products' => function ($query) {
            $query->select('assembly_id', 'product_id', 'product_name', 'quantity');
        }])
        ->select('assembly_id', 'product_id', 'product_name')
        ->where('company_id', Auth::user()->company_id);

        // If an $id is provided, filter based on product_id and return a single assembly record
        if ($id) {
            $assembly = $query->where('product_id', $id)->first();
            
            if (!$assembly) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Assembly record not found!',
                ], 404);
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Assembly record fetched successfully!',
                'data'    => $assembly,
            ], 200);
        }

        // Apply filters
        if ($assemblyId) {
            $query->where('assembly_id', $assemblyId);
        }
        // if ($productId) {
        //     $query->where('product_id', $productId);
        // }

        if ($productId) {
            $query->where(function ($q) use ($productId) {
                $q->where('product_id', $productId)
                  ->orWhereHas('products', function ($q2) use ($productId) {
                      $q2->where('product_id', $productId);
                  });
            });
        }

        // if ($productName) {
        //     $query->where('product_name', 'LIKE', '%' . $productName . '%');
        // }

        if ($productName) {
            $query->where(function ($q) use ($productName) {
                $q->where('product_name', 'LIKE', '%' . $productName . '%')
                  ->orWhereHas('products', function ($q2) use ($productName) {
                      $q2->where('product_name', 'LIKE', '%' . $productName . '%');
                  });
            });
        }        

        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_assembly = $query->get();

        // Return response
        return $get_assembly->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Assembly records fetched successfully!',
                'data' => $get_assembly,
                'count' => $get_assembly->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Assembly records found!',
            ], 404);
    }


    // update
    public function edit_assembly(Request $request)
    {
        $request->validate([
            'assembly_id' => 'required|integer',
            'product_id' => 'required|integer',
            'product_name' => 'required|string',
            // 'quantity' => 'required|integer',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.assembly_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.quantity' => 'required|integer',
            'products.*.log_user' => 'required|string',
        ]);

        // Fetch the assembly record by ID
        $assembly = AssemblyModel::where('assembly_id', $request->input('assembly_id'))->first();

        if($assembly)
        {
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
            foreach ($products as $productData) 
            {
                $requestProductIDs[] = $productData['product_id'];

                // Check if the product exists for this assembly_operations_id and product_id
                $existingProduct = AssemblyProductsModel::where('assembly_id', $productData['assembly_id'])
                                                                ->where('product_id', $productData['product_id'])
                                                                ->first();

                if ($existingProduct) 
                {
                    // Update the existing product
                    $existingProduct->update([
                        'product_name' => $productData['product_name'],
                        'quantity' => $productData['quantity'],
                        'rate' => $productData['rate'] ?? null,
                        'godown' => $productData['godown'] ?? null,
                        'amount' => $productData['amount'] ?? null,
                    ]);
                } 
                else 
                {
                    // Create a new product if not exists
                    AssemblyProductsModel::create([
                        'assembly_id' => $productData['assembly_id'],
                        'company_id' => Auth::user()->company_id,
                        'product_id' => $productData['product_id'],
                        'product_name' => $productData['product_name'],
                        'quantity' => $productData['quantity'],
                        'log_user' => $productData['log_user'],
                                        
                    ]);
                }
            }

            // Delete products that are not in the request but exist in the database for this assembly_operations_id
            $productsDeleted = AssemblyProductsModel::where('assembly_id', $id)
                                                            ->where('product_id', $requestProductIDs)
                                                            ->delete();

            // Remove timestamps from the response for neatness
            unset($assembly['created_at'], $assembly['updated_at']);

            return ($assemblyUpdated || $productsDeleted)
                ? response()->json(['code' => 200,'success' => true, 'message' => 'Assembly and products updated successfully!', 'data' => $assembly], 200)
                : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
        }
        return response()->json(['code' => 404,'success' => false, 'message' => 'Sorry, record not available!.'], 404);
    }


    // delete
    public function delete_assembly($id)
    {
        // Try to find the client by the given ID
        $get_assembly_id = AssemblyModel::select('assembly_id', 'company_id')
                                        ->where('id', $id)
                                        ->first();
        
        // Check if the client exists

        if ($get_assembly_id && $get_assembly_id->company_id === Auth::user()->company_id) 
        {
            // Delete the client
            $delete_assembly = AssemblyModel::where('id', $id)->delete();

            // Delete associated contacts by customer_id
            $delete_assembly_products = AssemblyProductsModel::where('assembly_id', $get_assembly_id->assembly_id)->delete();

            // Return success response if deletion was successful
            return $delete_assembly && $delete_assembly_products
            ? response()->json(['code' => 200,'success' => true, 'message' => 'Assembly and associated products deleted successfully!'], 200)
            : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to delete Assembly or products.'], 400);

        } 
        else 
        {
            // Return error response if client not found
            return response()->json(['code' => 404,'success' => false, 'message' => 'Assembly not found.'], 404);
        }
    }

    public function importAssemblies()
    {
        set_time_limit(300);

        // Clear the Assembly and related tables
        AssemblyModel::truncate();
        AssemblyProductsModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/assembly.php'; // Replace with the actual URL

        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data: ' . $e->getMessage()], 500);
        }

        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        $successfulInserts = 0;
        $errors = [];

        foreach ($data as $record) {
            // Fetch the product details for the composite
            $compositeProduct = ProductsModel::where('name', $record['composite'])->first();

            if (!$compositeProduct) {
                $errors[] = [
                    'record' => $record,
                    'error' => "Composite product '{$record['composite']}' not found."
                ];
                continue; // Skip this record if the composite product is not found
            }

            do {
                // Generate a random assembly ID
                $assembly_id = rand(1111111111, 9999999999);
                
                $exists = AssemblyModel::where('assembly_id', $assembly_id)->exists();
        } while ($exists);

            // Prepare Assembly data
            $assemblyData = [
                'assembly_id' => $assembly_id,
                'product_id' => $compositeProduct->id,
                'product_name' => $compositeProduct->name,
                'quantity' => 1, // Assuming quantity is 1 for the composite
                'log_user' => $record['log_user'] ?? 'Unknown',
            ];

            // Validate Assembly data
            $validator = Validator::make($assemblyData, [
                'assembly_id' => 'required|integer',
                'product_id' => 'required|integer',
                'product_name' => 'required|string',
                'quantity' => 'required|integer',
                'log_user' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $record, 'errors' => $validator->errors()];
                continue;
            }

            try {
                $assembly = AssemblyModel::create($assemblyData);
                $successfulInserts++;
            } catch (\Exception $e) {
                $errors[] = ['record' => $record, 'error' => 'Failed to insert assembly: ' . $e->getMessage()];
                continue;
            }

            // Parse and handle spares
            $sparesData = json_decode($record['spares'], true);

            if (is_array($sparesData) && isset($sparesData['product'], $sparesData['quantity'])) {
                foreach ($sparesData['product'] as $index => $spareProductName) {
                    // Fetch the spare product details
                    $spareProduct = ProductsModel::where('name', $spareProductName)->first();

                    if (!$spareProduct) {
                        $errors[] = [
                            'record' => $record,
                            'error' => "Spare product '{$spareProductName}' not found."
                        ];
                        continue; // Skip this spare if not found
                    }

                    try {
                        AssemblyProductsModel::create([
                            'assembly_id' => $assembly_id,
                            'product_id' => $spareProduct->id,
                            'product_name' => $spareProduct->name,
                            'quantity' => (int)($sparesData['quantity'][$index] ?? 1),
                            'log_user' => $record['log_user'] ?? 'Unknown',
                        ]);
                    } catch (\Exception $e) {
                        $errors[] = ['record' => $record, 'error' => 'Failed to insert spare product: ' . $e->getMessage()];
                    }
                }
            }
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Import completed with $successfulInserts successful inserts.",
            'errors' => $errors,
        ], 200);
    }

}
