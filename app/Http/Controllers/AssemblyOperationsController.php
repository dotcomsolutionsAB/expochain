<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\AssemblyOperationModel;
use App\Models\AssemblyOperationProductsModel;
use App\Models\ProductsModel;
use App\Models\GodownModel;
use Carbon\Carbon;
use Auth;

class AssemblyOperationsController extends Controller
{
    //
    // create
    public function add_assembly_operations(Request $request)
    {
        $request->validate([
            'type' => 'required|in:assemble,de-assemble',
            'assembly_operations_date' => 'required|date',
            'product_id' => 'required|integer|exists:t_products,id',
            'product_name' => 'required|string|exists:t_products,name',
            'godown' => 'required|integer|exists:t_godown,id',
            'quantity' => 'required|integer',
            'rate' => 'required|numeric',
            'amount' => 'required|numeric',
            'log_user' => 'required|string',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer|exists:t_products,id',
            'products.*.product_name' => 'required|string|exists:t_products,name',
            'products.*.quantity' => 'required|integer',
            'products.*.rate' => 'required|numeric',
            'products.*.godown' => 'required|integer|exists:t_godown,id',
            'products.*.amount' => 'required|numeric',
        ]);
    
        do{
            $assembly_operations_id = rand(1111111111,9999999999);

            $exists = AssemblyOperationModel::where('assembly_operations_id', $assembly_operations_id)->exists();
        }while ($exists);

        // $currentDate = Carbon::now()->toDateString();

        $register_assembly_operations = AssemblyOperationModel::create([
            'assembly_operations_id' => $assembly_operations_id,
            'company_id' => Auth::user()->company_id,
            'assembly_operations_date' => $request->input('assembly_operations_date'),
            'company_id' => Auth::user()->company_id,
            'type' => $request->input('type'),
            'product_id' => $request->input('product_id'),
            'product_name' => $request->input('product_name'),
            'godown' => $request->input('godown'),
            'quantity' => $request->input('quantity'),
            'rate' => $request->input('rate'),
            'amount' => $request->input('amount'),
            'log_user' => $request->input('log_user')
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            AssemblyOperationProductsModel::create([
                'assembly_operations_id' => $register_assembly_operations['id'],
                'company_id' => Auth::user()->company_id,
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
        ? response()->json(['code' => 201,'success' => true, 'message' => 'Assembly Operations records registered successfully!', 'data' => $register_assembly_operations], 201)
        : response()->json(['code' => 400,'success' => false, 'message' => 'Failed to register Assembly Operations records'], 400);
    }

    // view
    public function assembly_operations(Request $request, $id = null)
    {
        // Get filter inputs
        $assemblyOperationsId = $request->input('assembly_operations_id');
        $productId = $request->input('product_id');
        $productName = $request->input('product_name');
        $quantity = $request->input('quantity');
        $limit = $request->input('limit', 10); // Default limit to 10
        $offset = $request->input('offset', 0); // Default offset to 0

        // Build the query
        $query = AssemblyOperationModel::with(['products' => function ($query) {
            $query->select('id','assembly_operations_id', 'product_id', 'product_name', 'quantity', 'rate', 'godown', 'amount');
        }])
        ->select('id','assembly_operations_id', 'assembly_operations_date', 'type', 'product_id', 'product_name', 'godown', 'quantity', 'rate', 'amount')
        ->where('company_id', Auth::user()->company_id);

        // Apply filters
        if ($assemblyOperationsId) {
            $query->where('assembly_operations_id', $assemblyOperationsId);
        }

        if ($id) {
            $query->where('id', $id);
        }
        // if ($productId) {
        //     $query->where('product_id', $productId);
        // }
        // if ($productName) {
        //     $query->where('product_name', 'LIKE', '%' . $productName . '%');
        // }

        // Filter by Product ID (checks both main assembly and related products)
        if ($productId) {
            $query->where(function ($q) use ($productId) {
                $q->where('product_id', $productId)
                ->orWhereHas('products', function ($q2) use ($productId) {
                    $q2->where('product_id', $productId);
                });
            });
        }

        // Filter by Product Name (checks both main assembly and related products)
        if ($productName) {
            $query->where(function ($q) use ($productName) {
                $q->where('product_name', 'LIKE', '%' . $productName . '%')
                ->orWhereHas('products', function ($q2) use ($productName) {
                    $q2->where('product_name', 'LIKE', '%' . $productName . '%');
                });
            });
        }

        if ($quantity) {
            $query->where('quantity', $quantity);
        }

        // Get total record count before applying limit
        $totalRecords = $query->count();
        // Apply limit and offset
        $query->offset($offset)->limit($limit);

        // Fetch data
        $get_assembly_operations = $query->get();

        // Load godown names in one query to prevent N+1 problem
        $godownMap = GodownModel::pluck('name', 'id')->toArray();

        // Format godown fields in main and products
        foreach ($get_assembly_operations as $operation) {
            $operation->godown = [
                'id' => $operation->godown,
                'name' => $godownMap[$operation->godown] ?? 'Unknown'
            ];

            foreach ($operation->products as $product) {
                $product->godown = [
                    'id' => $product->godown,
                    'name' => $godownMap[$product->godown] ?? 'Unknown'
                ];
            }
        }

        // Return response
        return $get_assembly_operations->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Assembly Operations data fetched successfully!',
                'data' => $get_assembly_operations,
                'count' => $get_assembly_operations->count(),
                'total_records' => $totalRecords,
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'No Assembly Operations data found!',
            ], 404);
    }

    // update
    public function edit_assembly_operations(Request $request, $id)
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

        // Get the assembly operation record by ID
        $assemblyOperation = AssemblyOperationModel::where('id', $id)->first();

        if (!$assemblyOperation) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Assembly operation not found.',
            ], 404);
        }

        // Update the assembly operation details
        $assemblyUpdated = $assemblyOperation->update([
            'assembly_operations_date' => $request->input('assembly_operations_date'),
            'company_id' => Auth::user()->company_id,
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
            $existingProduct = AssemblyOperationProductsModel::where('assembly_operations_id',  $id)
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
                    'assembly_operations_id' => $id,
                    'company_id' => Auth::user()->company_id,
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
                                                            ->whereNotIn('product_id', $requestProductIDs)
                                                            ->delete();

            // Remove timestamps from the response for neatness
            //unset($assemblyOperation['created_at'], $assemblyOperation['updated_at']);
            $data = $assemblyOperation->makeHidden(['created_at', 'updated_at']);

            return ($assemblyUpdated || $productsDeleted)
                ? response()->json(['code' => 200,'success' => true, 'message' => 'Assembly operation and products updated successfully!', 'data' => $data], 200)
                : response()->json(['code' => 304,'success' => false, 'message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_assembly_operations($id)
    {
        // Fetch the assembly operation by internal ID
        $operation = AssemblyOperationModel::where('id', $id)->first();

        if ($operation && $operation->company_id === Auth::user()->company_id) {
            // Delete associated products using assembly_operations_id
            $productsDeleted = AssemblyOperationProductsModel::where('assembly_operations_id', $id)->delete();

            // Delete the main assembly operation using internal ID
            $operationDeleted = $operation->delete();

            return ($operationDeleted || $productsDeleted)
                ? response()->json([
                    'code' => 200,
                    'success' => true,
                    'message' => 'Assembly operation and associated products deleted successfully!'
                ], 200)
                : response()->json([
                    'code' => 400,
                    'success' => false,
                    'message' => 'Failed to delete assembly operation or its products.'
                ], 400);
        } else {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Assembly operation not found or unauthorized.'
            ], 404);
        }
    }

    public function importAssemblyOperations()
    {
        set_time_limit(300);

        // Clear the tables
        AssemblyOperationModel::truncate();
        AssemblyOperationProductsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/assembly_operation.php';

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

        $userCompanyId = Auth::user()->company_id;

        $assemblyInsertData = [];
        $productInsertData = [];
        $errors = [];
        $successfulInserts = 0;
        $randomCodeMap = []; // [generated_code => array of items]

        foreach ($data as $record) {

            // Generate unique assembly_operations_id (random string only for temp map)
            do {
                $generated_code = rand(1111111111, 9999999999);
            } while (isset($randomCodeMap[$generated_code]));

            // $assembly_operations_date = '2021-05-13';
            $assembly_operations_date = $record['log_date'];

            $type = ($record['operation'] === 'Disassembled') ? 'de-assemble' : 'assemble';

            $compositeProduct = ProductsModel::where('name', $record['composite'])->first();

            if (!$compositeProduct) {
                $errors[] = [
                    'record' => $record,
                    'error' => "Composite product '{$record['composite']}' not found."
                ];
                continue;
            }

            $quantity = (float)$record['quantity'];
            $rate = (float)$record['rate'];
            $amount = $quantity * $rate;

            $godownName = $record['place'] ?? 'Unknown';
            $godownId = GodownModel::where('name', $godownName)->value('id') ?? 0;

            $assemblyInsertData[] = [
                'assembly_operations_id'   => $generated_code, // temp ID
                'assembly_operations_date' => $assembly_operations_date,
                'company_id'               => $userCompanyId,
                'type'                     => $type,
                'product_id'               => $compositeProduct->id,
                'product_name'             => $compositeProduct->name,
                'quantity'                 => $quantity,
                'godown'                   => $godownId,
                'rate'                     => $rate,
                'amount'                   => $amount,
                'log_user'                 => $record['log_user'] ?? 'Unknown',
                'created_at'               => now(),
                'updated_at'               => now(),
            ];

            // Store items temporarily under the same generated code
            $randomCodeMap[$generated_code] = $record;
            $successfulInserts++;
        }

        // Batch insert parent assembly operations
        AssemblyOperationModel::insert($assemblyInsertData);

        // Fetch inserted IDs and map them using the generated temp ID
        $realIdMap = AssemblyOperationModel::whereIn('assembly_operations_id', array_keys($randomCodeMap))
            ->pluck('id', 'assembly_operations_id') // [generated_code => id]
            ->toArray();

        // Now prepare child product records using real auto-increment IDs
        foreach ($realIdMap as $generated_code => $realId) {
            $record = $randomCodeMap[$generated_code];
            $itemsData = json_decode($record['items'], true);

            if (is_array($itemsData) && isset($itemsData['product'], $itemsData['quantity'], $itemsData['rate'], $itemsData['place'])) {
                foreach ($itemsData['product'] as $index => $productName) {
                    $itemProduct = ProductsModel::where('name', $productName)->first();

                    if (!$itemProduct) {
                        $errors[] = [
                            'record' => $record,
                            'error' => "Item product '{$productName}' not found."
                        ];
                        continue;
                    }

                    $itemQuantity = (float)$itemsData['quantity'][$index];
                    $itemRate = (float)$itemsData['rate'][$index];
                    $itemAmount = $itemQuantity * $itemRate;

                    $itemGodownName = $itemsData['place'][$index] ?? 'Unknown';
                    $itemGodownId = GodownModel::where('name', $itemGodownName)->value('id') ?? 0;

                    $productInsertData[] = [
                        'assembly_operations_id' => $realId,
                        'company_id'             => $userCompanyId,
                        'product_id'             => $itemProduct->id,
                        'product_name'           => $itemProduct->name,
                        'quantity'               => $itemQuantity,
                        'rate'                   => $itemRate,
                        'godown'                 => $itemGodownId,
                        'amount'                 => $itemAmount,
                        'created_at'             => now(),
                        'updated_at'             => now(),
                    ];
                }
            }
        }

        // Final batch insert products
        if (!empty($productInsertData)) {
            AssemblyOperationProductsModel::insert($productInsertData);
        }

        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => "Import completed with $successfulInserts successful inserts.",
            'errors'  => $errors,
        ], 200);
    }

    // fetch by product id
    public function fetchAssemblyByProduct(Request $request, $productId)
    {
        try {
            $companyId = Auth::user()->company_id;

            // Inputs
            $sortField = $request->input('sort_field', 'date');
            $sortOrder = $request->input('sort_order', 'asc');
            $limit = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);

            // Search Input (Single search variable for filtering order, operation, or user)
            $search = $request->input('search'); // This is the new single search variable

            $validSortFields = ['date', 'quantity', 'operation', 'user'];
            if (!in_array($sortField, $validSortFields)) {
                return response()->json([
                    'code' => 422,
                    'success' => false,
                    'message' => 'Invalid sort field.',
                    'data' => [],
                    'count' => 0,
                    'total_records' => 0
                ], 422);
            }

            // Fetch all assembly operations
            $operations = AssemblyOperationModel::where('company_id', $companyId)
                ->with(['products' => function ($query) use ($productId) {
                    $query->where('product_id', $productId);
                }])
                ->get();

            $records = [];

            foreach ($operations as $operation) {
                foreach ($operation->products as $product) {
                    $records[] = [
                        'order'     => $operation->assembly_operations_id,
                        'date'      => $operation->assembly_operations_date,
                        'quantity'  => (float) $product->quantity,
                        'operation' => $operation->type,
                        'user'      => $operation->log_user,
                    ];
                }
            }

            // Apply filter using the $search variable
            if (!empty($search)) {
                $records = array_filter($records, function ($item) use ($search) {
                    return stripos($item['order'], $search) !== false || 
                        stripos($item['operation'], $search) !== false || 
                        stripos($item['user'], $search) !== false;
                });
            }
            // Sort
            usort($records, function ($a, $b) use ($sortField, $sortOrder) {
                return $sortOrder === 'asc'
                    ? $a[$sortField] <=> $b[$sortField]
                    : $b[$sortField] <=> $a[$sortField];
            });

            // Totals
            $totalQty = array_sum(array_column($records, 'quantity'));

            // Pagination
            $totalRecords = count($records);
            $paginated = array_slice($records, $offset, $limit);

            // Subtotal
            $subQty = array_sum(array_column($paginated, 'quantity'));

            $subTotalRow = [
                'order' => '',
                'date' => 'SubTotal - ',
                'quantity' => $subQty,
                'operation' => '',
                'user' => '',
            ];

            $totalRow = [
                'order' => '',
                'date' => 'Total - ',
                'quantity' => $totalQty,
                'operation' => '',
                'user' => '',
            ];

            $paginated[] = $subTotalRow;
            $paginated[] = $totalRow;

            // Response
            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $paginated,
                'count' => count($paginated),
                'total_records' => $totalRecords,
                // 'sub_total' => [
                //     'quantity' => $subQty
                // ],
                // 'total' => [
                //     'quantity' => $totalQty
                // ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => [],
                'count' => 0,
                'total_records' => 0
            ], 500);
        }
    }
}
