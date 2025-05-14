<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductsModel;
use App\Models\FinancialYearModel;
use App\Models\PdfTemplateModel;
use App\Models\GodownModel;
use App\Models\CategoryModel;
use App\Models\SubCategoryModel;
use App\Models\GroupModel;
use App\Models\OpeningStockModel;
use App\Models\ClosingStockModel;
use App\Models\UploadsModel;
use App\Models\CustomerVisitModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Auth;
use DB;
use Illuminate\Validation\Rule;

class MastersController extends Controller
{
    // products table
    //create
    public function add_products(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|integer',
            'name' => 'required|string|unique:t_products,name',
            'alias' => 'nullable|string',
            'description' => 'nullable|string',
            'type' => 'required|string',
            'group' => 'required|integer|exists:t_group,id',
            'category' => 'required|integer|exists:t_category,id',
            'sub_category' => 'nullable|integer|exists:t_sub_category,id',
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'hsn' => 'required|string',
            'tax' => 'required|numeric|min:0|max:100',
        ]);

        $register_products = ProductsModel::create([
            'serial_number' => $request->input('serial_number'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'alias' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'group' => $request->input('group'),
            'category' => $request->input('category'),
            'sub_category' => $request->input('sub_category'),
            'cost_price' => $request->input('cost_price'),
            'sale_price' => $request->input('sale_price'),
            'unit' => $request->input('unit'),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),
        ]);
        
        unset($register_products['id'], $register_products['created_at'], $register_products['updated_at']);

        return isset($register_products) && $register_products !== null
        ? response()->json(['code' => 200, 'success' => true, 'message' => 'Products registered successfully!', 'data' => $register_products], 200)
        : response()->json(['code' => 200, 'success' => false, 'message' => 'Failed to register Products record'], 200);
    }

    // fetch
    public function view_products(Request $request, $id = null)
    {
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $productName = $request->input('product_name');
        $group = $request->input('group_id') ? explode(',', $request->input('group_id')) : null;
        $category = $request->input('category_id') ? explode(',', $request->input('category_id')) : null;
        $subCategory = $request->input('sub_category_id') ? explode(',', $request->input('sub_category_id')) : null;

        // Query Products
        $query = ProductsModel::with(['groupRelation', 'categoryRelation', 'subCategoryRelation'])
            ->select(
                'id',
                'serial_number',
                'company_id',
                'name',
                'alias',
                'description',
                'type',
                'group',
                'category',
                'sub_category',
                'cost_price',
                'sale_price',
                'unit',
                'hsn',
                'tax'
            )
            ->where('company_id', Auth::user()->company_id);

        // 🔹 **Fetch Single Product by ID**
        if ($id) {
            $product = $query->where('serial_number', $id)->first();
            if (!$product) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'Product not found!',
                ], 404);
            }

            // Add stock details
            $stockDetails = "Opening Stock as on 01-04-2024 : Office - 30 SETS | Kushtia - 10 SETS | ANK - 25 SETS";
            $product->stock_details = $stockDetails;

            // Extract names for group, category, and sub-category
            $product->group_name = $product->groupRelation->name ?? null;
            $product->category_name = $product->categoryRelation->name ?? null;
            $product->sub_category_name = $product->subCategoryRelation->name ?? null;

            // Remove relationship objects
            unset($product->groupRelation, $product->categoryRelation, $product->subCategoryRelation);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Product fetched successfully!',
                'data' => $product,
            ], 200);
        }

        // 🔹 **Apply Filters for Listing**
        if ($productName) {
            $normalizedInput = strtolower(preg_replace('/[^a-zA-Z0-9]/', ' ', $productName));
            $tokens = preg_split('/\s+/', $normalizedInput);

            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->where(function ($subQuery) use ($token) {
                        $subQuery->whereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), 'x', '')) LIKE ?",
                            ['%' . $token . '%']
                        )
                        ->orWhereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE(alias, ' ', ''), '-', ''), 'x', '')) LIKE ?",
                            ['%' . $token . '%']
                        );
                    });
                }
            });
        }

        if ($group) {
            $query->whereIn('group', $group);
        }
        if ($category) {
            $query->whereIn('category', $category);
        }
        if ($subCategory) {
            $query->whereIn('sub_category', $subCategory);
        }

        // Get total record count before applying limit
        $product_count = $query->count();
        $query->offset($offset)->limit($limit);

        // Fetch paginated results
        $get_products = $query->get();

        if ($get_products->isEmpty()) {
            return response()->json([
                'code' => 200,
                'success' => false,
                'message' => 'No products found!',
                'date' => [],
            ], 200);
        }

        // Add Stock Details & Transform Data
        $stockDetails = "Opening Stock as on 01-04-2024 : Office - 30 SETS | Kushtia - 10 SETS | ANK - 25 SETS";
        $get_products->transform(function ($product) use ($stockDetails) {
            $product->stock_details = $stockDetails;
            $product->group_name = $product->group->name ?? null;
            $product->category_name = $product->category->name ?? null;
            $product->sub_category_name = $product->subCategory->name ?? null;
            unset($product->group, $product->category, $product->subCategory);
            return $product;
        });

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Fetch data successfully!',
            'data' => $get_products,
            'count' => $get_products->count(),
            'total_records' => $product_count,
        ], 200);
    }

    /**
     * Get an array of tax values
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_tax()
    {
        $taxValues = [0, 5, 12, 18, 28];
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Tax array fetched successfully!',
            'data' => $taxValues,
        ], 200);
    }

    public function get_unit()
    {
        $unitValues = ['NOS', 'PCS', 'SETS', 'KGS'];
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Tax array fetched successfully!',
            'data' => $unitValues,
        ], 200);
    }

    // update
    public function edit_products(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|integer',
            'name' => [
            'required',
            'string',
            Rule::unique('t_products')->ignore($id, 'id') // ✅ Check uniqueness except this product
        ],
            'alias' => 'nullable|string',
            'description' => 'nullable|string',
            'type' => 'required|string',
            'group' => 'required|integer|exists:t_group,id',
            'category' => 'required|integer|exists:t_category,id',
            'sub_category' => 'nullable|integer|exists:t_sub_category,id',
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'hsn' => 'required|string',
            'tax' => 'required|numeric|min:0|max:100',
        ]);

        $update_products = ProductsModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'alias' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'group' => $request->input('group'),
            'category' => $request->input('category'),
            'sub_category' => $request->input('sub_category'),
            'cost_price' => $request->input('cost_price'),
            'sale_price' => $request->input('sale_price'),
            'unit' => $request->input('unit'),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),
        ]);
        
        return $update_products
        ? response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Products were updated successfully!',
            'data' => $update_products
        ], 200)
        : response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'No changes detected.',
            'data' => []
        ], 200);
    }

    // delete
    public function delete_products($id)
    {
        // Delete the client
        $delete_products = ProductsModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_products
        ? response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Deleted Product successfully!'
        ], 200)
        : response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Sorry, product not found.'
        ], 200);
    }

    // import products
    public function importProducts()
    {
        ini_set('max_execution_time', 1200); // Increase execution time
        ini_set('memory_limit', '2048M');   // Increase memory limit

        // Truncate all related tables
        GroupModel::truncate();
        CategoryModel::truncate();
        SubCategoryModel::truncate();
        ProductsModel::truncate();

        $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

        try {
            // Fetch data from the external URL
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');
        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        $batchSize = 1000; // Define a batch size for inserting products
        $batchData = [];
        $successfulInserts = 0;
        $errors = [];

        $existingGroups = [];
        $existingCategories = [];
        $existingSubCategories = [];

        foreach ($data as $record) {
            try {
                // Handle and create Group
                $groupId = null;
                if (!empty($record['group_name'])) {
                    if (!isset($existingGroups[$record['group_name']])) {
                        $group = GroupModel::create([
                            'name' => $record['group_name'],
                            'company_id' => Auth::user()->company_id,
                            'serial_number' => random_int(10000, 99999),
                            'logo' => random_int(10000, 99999),
                        ]);
                        $existingGroups[$record['group_name']] = $group->id;
                    }
                    $groupId = $existingGroups[$record['group_name']];
                }

                // Handle and create Category
                $categoryId = null;
                if (!empty($record['category'])) {
                    if (!isset($existingCategories[$record['category']])) {
                        $category = CategoryModel::create([
                            'name' => $record['category'],
                            'company_id' => Auth::user()->company_id,
                            'serial_number' => random_int(10000, 99999),
                        ]);
                        $existingCategories[$record['category']] = $category->id;
                    }
                    $categoryId = $existingCategories[$record['category']];
                }

                // Handle and create Sub-Category
                $subCategoryId = null;
                if (!empty($record['sub_category'])) {
                    if (!isset($existingSubCategories[$record['sub_category']])) {
                        $subCategory = SubCategoryModel::create([
                            'name' => $record['sub_category'],
                            'category_id' => $categoryId,
                            'company_id' => Auth::user()->company_id,
                            'serial_number' => random_int(10000, 99999),
                        ]);
                        $existingSubCategories[$record['sub_category']] = $subCategory->id;
                    }
                    $subCategoryId = $existingSubCategories[$record['sub_category']];
                }

                // Sanitize numeric fields
                $costPrice = is_numeric($record['cost_price']) ? $record['cost_price'] : 0;
                $salePrice = is_numeric($record['sale_price']) ? $record['sale_price'] : 0;
                $tax = is_numeric($record['tax']) ? $record['tax'] : 0;

                // Prepare product data
                $productData = [
                    'serial_number' => $record['sn'],
                    'company_id' => Auth::user()->company_id,
                    'name' => $record['name'],
                    'alias' => $record['alias'],
                    'description' => $record['description'] ?? 'No description available',
                    'type' => $record['type'],
                    'group' => $groupId,
                    'category' => $categoryId,
                    'sub_category' => $subCategoryId,
                    'cost_price' => $costPrice,
                    'sale_price' => $salePrice,
                    'unit' => $record['unit'] ?? 'N/A',
                    'hsn' => $record['hsn'] ?? 'N/A',
                    'tax' => $tax,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Add to batch
                $batchData[] = $productData;

                // Insert batch when batch size is reached
                if (count($batchData) >= $batchSize) {
                    ProductsModel::insert($batchData);
                    $successfulInserts += count($batchData);
                    $batchData = []; // Reset batch
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'record' => $record,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Insert remaining products in batch
        if (!empty($batchData)) {
            ProductsModel::insert($batchData);
            $successfulInserts += count($batchData);
        }

        // Return response
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Product data import completed. Successful inserts: $successfulInserts.",
            'errors' => $errors,
        ], 200);
    }

    // public function importOpeningStock()
    // {
    //     ini_set('max_execution_time', 1200); // Increase execution time
    //     ini_set('memory_limit', '2048M');    // Increase memory limit

    //     $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

    //     try {
    //         // Fetch data from external API
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');
    //     if (empty($data)) {
    //         return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
    //     }

    //     $godownMapping = [
    //         'OFFICE' => 1,
    //         'KUSHTIA' => 2,
    //         'ANKURHATI' => 3,
    //     ];

    //     $companyId = 1;
    //     $year = 6;
    //     $batchSize = 1000;
    //     $batchData = [];
    //     $successfulInserts = 0;
    //     $errors = [];

    //     foreach ($data as $record) {
    //         try {
    //             if (empty($record['name']) || empty($record['current_stock'])) {
    //                 continue;
    //             }

    //             // Find product_id from products table
    //             $product = ProductsModel::where('name', $record['name'])->first();

    //             if (!$product) {
    //                 $errors[] = [
    //                     'name' => $record['name'],
    //                     'error' => 'Product not found',
    //                 ];
    //                 continue;
    //             }

    //             $currentStock = json_decode($record['current_stock'], true);

    //             if (empty($currentStock) || !is_array($currentStock)) {
    //                 continue;
    //             }

    //             foreach ($godownMapping as $godownName => $godownId) {
    //                 if (isset($currentStock[$godownName])) {
    //                     $quantity = (float) $currentStock[$godownName];

    //                     if ($quantity > 0) {
    //                         $batchData[] = [
    //                             'company_id' => $companyId,
    //                             'year' => $year,
    //                             'godown_id' => $godownId,
    //                             'product_id' => $product->id,
    //                             'quantity' => $quantity,
    //                             'value' => 0, // If needed you can calculate based on cost price
    //                             'sold' => 0,
    //                             'created_at' => now(),
    //                             'updated_at' => now(),
    //                         ];
    //                     }
    //                 }
    //             }

    //             if (count($batchData) >= $batchSize) {
    //                 OpeningStockModel::insert($batchData);
    //                 $successfulInserts += count($batchData);
    //                 $batchData = [];
    //             }
    //         } catch (\Exception $e) {
    //             $errors[] = [
    //                 'aid' => $record['aid'] ?? null,
    //                 'error' => $e->getMessage(),
    //             ];
    //         }
    //     }

    //     // Insert remaining batch
    //     if (!empty($batchData)) {
    //         OpeningStockModel::insert($batchData);
    //         $successfulInserts += count($batchData);
    //     }

    //     return response()->json([
    //         'code' => 200,
    //         'success' => true,
    //         'message' => "Opening stock import completed. Successful inserts: $successfulInserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    public function importOpeningStock()
    {
        ini_set('max_execution_time', 1200);
        ini_set('memory_limit', '2048M');

        $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');
        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        // Truncate the opening stock table
        OpeningStockModel::truncate();

        $godownMapping = [
            'OFFICE' => 1,
            'KUSHTIA' => 2,
            'ANKURHATI' => 3,
        ];

        $companyId = 1;
        $year = 6;
        $batchSize = 1000;
        $batchData = [];
        $successfulInserts = 0;
        $errors = [];

        foreach ($data as $record) {
            try {
                if (empty($record['name']) || empty($record['stock'])) {
                    continue;
                }

                $product = ProductsModel::where('name', $record['name'])->first();
                if (!$product) {
                    $errors[] = ['name' => $record['name'], 'error' => 'Product not found'];
                    continue;
                }

                $stockData = json_decode($record['stock'], true);
                if (!isset($stockData['place'], $stockData['quantity'])) {
                    continue;
                }

                foreach ($godownMapping as $godownName => $godownId) {
                    $index = array_search($godownName, $stockData['place']);
                    if ($index !== false) {
                        $quantity = (float) $stockData['quantity'][$index];
                        if ($quantity > 0) {
                            $batchData[] = [
                                'company_id' => $companyId,
                                'year' => $year,
                                'godown_id' => $godownId,
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'value' => $product->cost_price,
                                'sold' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                if (count($batchData) >= $batchSize) {
                    OpeningStockModel::insert($batchData);
                    $successfulInserts += count($batchData);
                    $batchData = [];
                }

            } catch (\Exception $e) {
                $errors[] = ['aid' => $record['aid'] ?? null, 'error' => $e->getMessage()];
            }
        }

        // Final insert
        if (!empty($batchData)) {
            OpeningStockModel::insert($batchData);
            $successfulInserts += count($batchData);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Opening stock import completed. Successful inserts: $successfulInserts.",
            'errors' => $errors,
        ], 200);
    }


    public function importFinancialYears()
    {
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('memory_limit', '1024M');   // Increase memory limit

        $url = 'https://expo.egsm.in/assets/custom/migrate/financial_years.php';

        try {
            // Fetch data from external API
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');

        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }

        $companyId = 1;
        $batchData = [];
        $successfulInserts = 0;
        $errors = [];

        foreach ($data as $record) {
            try {
                $batchData[] = [
                    'name' => $record['year'],
                    'company_id' => $companyId,
                    'start_date' => $record['start'],
                    'end_date' => $record['end'],
                    'opening_stock' => is_numeric($record['opening']) ? (float) $record['opening'] : 0,
                    'closing_stock' => is_numeric($record['closing']) ? (float) $record['closing'] : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'year' => $record['year'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (!empty($batchData)) {
            FinancialYearModel::insert($batchData);
            $successfulInserts += count($batchData);
        }

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "Financial year data import completed. Successful inserts: $successfulInserts.",
            'errors' => $errors,
        ], 200);
    }



    // export products
    public function export_products(Request $request)
    {
        // Check for comma-separated IDs first
        $ids = $request->input('id') ? explode(',', $request->input('id')) : null;
        $group = $request->input('group_id') ? explode(',', $request->input('group_id')) : null;
        $category = $request->input('category_id') ? explode(',', $request->input('category_id')) : null;
        $subCategory = $request->input('sub_category_id') ? explode(',', $request->input('sub_category_id')) : null;
        $productName = $request->input('product_name');

        // Base Query
        $query = ProductsModel::with(['groupRelation:id,name', 'categoryRelation:id,name', 'subCategoryRelation:id,name'])
            ->select(
                'serial_number',
                'name',
                'alias',
                'description',
                'type',
                'group',
                'category',
                'sub_category',
                'cost_price',
                'sale_price',
                'unit',
                'hsn',
                'tax'
            );

        // 🔹 If IDs are provided, prioritize them
        if ($ids) {
            $query->whereIn('id', $ids);
        } else {
            // Apply filters only if IDs are not provided
            if ($productName) {
                $normalizedInput = strtolower(preg_replace('/[^a-zA-Z0-9]/', ' ', $productName));
                $tokens = preg_split('/\s+/', $normalizedInput);

                $query->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $q->where(function ($subQuery) use ($token) {
                            $subQuery->whereRaw(
                                "LOWER(REPLACE(REPLACE(REPLACE(name, ' ', ''), '-', ''), 'x', '')) LIKE ?",
                                ['%' . $token . '%']
                            )
                            ->orWhereRaw(
                                "LOWER(REPLACE(REPLACE(REPLACE(alias, ' ', ''), '-', ''), 'x', '')) LIKE ?",
                                ['%' . $token . '%']
                            );
                        });
                    }
                });
            }

            if ($group) {
                $query->whereIn('group', $group);
            }
            if ($category) {
                $query->whereIn('category', $category);
            }
            if ($subCategory) {
                $query->whereIn('sub_category', $subCategory);
            }
        }

        // Fetch Data
        $products = $query->get();

        if ($products->isEmpty()) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Sorry! no products found to export!',
            ], 404);
        }

        // Format data for export
        $exportData = $products->map(function ($product) {
            return [
                'Serial Number' => $product->serial_number,
                'Product Name' => $product->name,
                'Alias' => $product->alias,
                'Description' => $product->description,
                'Type' => $product->type,
                'Group' => optional($product->Group)->name,
                'Category' => optional($product->Category)->name,
                'Sub Category' => optional($product->SubCategory)->name,
                'Cost Price' => $product->cost_price,
                'Sale Price' => $product->sale_price,
                'Unit' => $product->unit,
                'HSN' => $product->hsn,
                'Tax' => $product->tax,
            ];
        })->toArray();

        // File path
        $fileName = 'products_export_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'uploads/products_excel/' . $fileName;

        // Store Excel file in storage
        Excel::store(new class($exportData) implements FromCollection, WithHeadings {
            private $data;

            public function __construct(array $data)
            {
                $this->data = collect($data);
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Serial Number',
                    'Product Name',
                    'Alias',
                    'Description',
                    'Type',
                    'Group',
                    'Category',
                    'Sub Category',
                    'Cost Price',
                    'Sale Price',
                    'Unit',
                    'HSN',
                    'Tax',
                ];
            }
        }, $filePath, 'public');

        // Get file details
        $fileUrl = asset('storage/' . $filePath);
        $fileSize = Storage::disk('public')->size($filePath);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'File available for download',
            'data' => [
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'content_type' => "Excel",
            ],
        ], 200);
    }

    public function get_product()
    {
        $get_user_company_id = Auth::user()->company_id;
        
        $get_product_details = ProductsModel::select('serial_number','name','alias','description','type','group','category','sub_category','cost_price','sale_price', 'unit', 'hsn', 'tax')
                                                ->where('company_id', $get_user_company_id)
                                                ->get();


        return isset($get_product_details) && $get_product_details !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_product_details, 'count' => count($get_product_details)], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // finacial year table
    //create
    public function add_f_year(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'start_date' => 'required',
            'end_date' => 'required|string',
            'opening_stock' => 'required',
            'closing_stock' => 'required',
        ]);

        $register_f_year = FinancialYearModel::create([
            'name' => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'opening_stock' => $request->input('opening_stock'),
            'closing_stock' => $request->input('closing_stock'),
        ]);
        
        unset($register_f_year['id'], $register_f_year['created_at'], $register_f_year['updated_at']);

        return isset($register_f_year) && $register_f_year !== null
        ? response()->json(['Finacial year added successfully!', 'data' => $register_f_year], 201)
        : response()->json(['Failed to add fiancial year'], 400);
    }

    //view
    public function view_f_year()
    {        
        $get_f_year = FinancialYearModel::select('name','start_date','end_date','opening_stock','closing_stock')
        ->where('company_id',Auth::user()->company_id)
        ->get();
        
        return isset($get_f_year) && $get_f_year !== null
        ? response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Fetch data successfully!',
            'data' => $get_f_year,
        ], 200)
        : response()->json([
            'code' => 404,
            'success' => false,
            'message' => 'Failed to fetch data',
            'data' => [],
        ], 404); 
    }

    // update
    public function edit_f_year(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'start_date' => 'required',
            'end_date' => 'required|string',
            'opening_stock' => 'required',
            'closing_stock' => 'required',
        ]);

        $update_f_year = FinancialYearModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'opening_stock' => $request->input('opening_stock'),
            'closing_stock' => $request->input('closing_stock'),
        ]);
        
        return $update_f_year
        ? response()->json(['Fianacial year updated successfully!', 'data' => $update_f_year], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_f_year($id)
    {
        // Delete the client
        $delete_f_year = FinancialYearModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_f_year
        ? response()->json(['message' => 'Delete Financial Year successfully!'], 204)
        : response()->json(['message' => 'Sorry, Financial Year not found'], 400);
    }

    // pdf setup table
    //create
    public function add_pdf_template(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'phone_number' => 'required|string',
                'mobile' => 'required|string',
                'email' => 'required|string',
                'address_line_1' => 'required|string',
                'address_line_2' => 'required|string',
                'city' => 'required|string',
                'pincode' => 'required|string',
                'state' => 'required|string',
                'country' => 'required|string',
                'gstin' => 'required|string',
                'bank_number' => 'required|string',
                'bank_account_name' => 'required|string',
                'bank_account_number' => 'required|string',
                'bank_ifsc' => 'required|string',
                'header' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'footer' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            $uploadIds = [];

            foreach (['header', 'footer'] as $key) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $ext = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(20) . '.' . $ext;
                    $relativePath = 'uploads/pdf_template/' . $filename;

                    // $file->storeAs('uploads/pdf_template', $filename);
                    Storage::disk('public')->putFileAs('uploads/pdf_template', $file, $filename);

                    $upload = UploadsModel::create([
                        'company_id' => Auth::user()->company_id,
                        'file_ext' => $ext,
                        'file_url' => $relativePath,
                        'file_size' => $file->getSize(),
                        'file_name' => $originalName,
                    ]);

                    $uploadIds[$key] = $upload->id;
                } else {
                    return response()->json([
                        'code' => 422,
                        'success' => false,
                        'message' => "The {$key} file is missing or invalid.",
                    ], 422);
                }
            }

            $register_pdf_template = PdfTemplateModel::create([
                'company_id' => Auth::user()->company_id,
                'name' => $validated['name'],
                'phone_number' => $validated['phone_number'],
                'mobile' => $validated['mobile'],
                'email' => $validated['email'],
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'],
                'city' => $validated['city'],
                'pincode' => $validated['pincode'],
                'state' => $validated['state'],
                'country' => $validated['country'],
                'gstin' => $validated['gstin'],
                'bank_number' => $validated['bank_number'],
                'bank_account_name' => $validated['bank_account_name'],
                'bank_account_number' => $validated['bank_account_number'],
                'bank_ifsc' => $validated['bank_ifsc'],
                'header' => $uploadIds['header'],
                'footer' => $uploadIds['footer'],
            ]);

            unset($register_pdf_template['id'], $register_pdf_template['company_id'], $register_pdf_template['created_at'], $register_pdf_template['updated_at']);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'PDF Template registered successfully!',
                'data' => $register_pdf_template
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while registering PDF template.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //view
    public function pdf_template()
    {        
        $get_pdf_template = PdfTemplateModel::select('name','phone_number','mobile','email','address_line_1', 'address_line_2','city','pincode','state','country', 'gstin', 'bank_number', 'bank_account_name', 'bank_account_number', 'bank_ifsc','header', 'footer')
        ->where('company_id', Auth::user()->company_id)
        ->get();

        // Add file URLs for header and footer
        $get_pdf_template->transform(function ($template) {
            $template->header = $template->header
                ? asset('storage/' . optional(UploadsModel::find($template->header))->file_url)
                : null;

            $template->footer = $template->footer
                ? asset('storage/' . optional(UploadsModel::find($template->footer))->file_url)
                : null;

            return $template;
        });

        return isset($get_pdf_template) && $get_pdf_template !== null
        ? response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Fetch data successfully!',
            'data' => $get_pdf_template,
        ], 200)
        : response()->json([
            'code' => 404,
            'success' => false,
            'message' => 'Failed to fetch data',
            'data' => [],
        ], 404); 
    }

    // update
    public function edit_pdf_template(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'phone_number' => 'required|string',
                'mobile' => 'required|string',
                'email' => 'required|string',
                'address_line_1' => 'required|string',
                'address_line_2' => 'required|string',
                'city' => 'required|string',
                'pincode' => 'required',
                'state' => 'required',
                'country' => 'required',
                'gstin' => 'required|string',
                'bank_number' => 'required',
                'bank_account_name' => 'required',
                'bank_account_number' => 'required|string',
                'bank_ifsc' => 'required|string',
                'header' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'footer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            $template = PdfTemplateModel::findOrFail($id);

        $uploadPath = 'uploads/pdf_template/';
        $uploadIds = [];

        foreach (['header', 'footer'] as $key) {
            if ($request->hasFile($key)) {
                // Delete old file if exists
                $oldUploadId = $template->$key;
                if ($oldUploadId) {
                    $oldUpload = UploadsModel::find($oldUploadId);
                    if ($oldUpload) {
                        $filePath = storage_path('app/public/' . $oldUpload->file_url);
                        if (File::exists($filePath)) File::delete($filePath);
                        $oldUpload->delete();
                    }
                }

                // Upload new file
                $file = $request->file($key);
                $ext = $file->getClientOriginalExtension();
                $filename = Str::random(20) . '.' . $ext;
                $relativePath = $uploadPath . $filename;
                // $file->storeAs('public/' . dirname($relativePath), basename($relativePath));
                 Storage::disk('public')->putFileAs('uploads/pdf_template', $file, $filename);

                $upload = UploadsModel::create([
                    'company_id' => Auth::user()->company_id,
                    'file_ext' => $ext,
                    'file_url' => $relativePath,
                    'file_size' => $file->getSize(),
                    'file_name' => $file->getClientOriginalName(),
                ]);

                $uploadIds[$key] = $upload->id;
            } else {
                // Keep existing if not uploaded again
                $uploadIds[$key] = $template->$key;
            }
        }

            $template->update([
                'name' => $request->input('name'),
                'phone_number' => $request->input('phone_number'),
                'mobile' => $request->input('mobile'),
                'email' => $request->input('email'),
                'address_line_1' => $request->input('address_line_1'),
                'address_line_2' => $request->input('address_line_2'),
                'city' => $request->input('city'),
                'pincode' => $request->input('pincode'),
                'state' => $request->input('state'),
                'country' => $request->input('country'),
                'gstin' => $request->input('gstin'),
                'bank_number' => $request->input('bank_number'),
                'bank_account_name' => $request->input('bank_account_name'),
                'bank_account_number' => $request->input('bank_account_number'),
                'bank_ifsc' => $request->input('bank_ifsc'),
                'header' => $uploadIds['header'],
                'footer' => $uploadIds['footer'],
            ]);
            
             unset($template['id'], $template['created_at'], $template['updated_at']);

            return $template
            ? response()->json(['code' => 200, 'success' => true, 'message' => 'Products updated successfully!', 'data' => $template], 200)
            : response()->json(['code' => 200, 'success' => false, 'message' => 'No changes detected'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while updating the PDF template.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // delete
    public function delete_pdf_template($id)
    {
        try {
            $template = PdfTemplateModel::where('id', $id)
                ->where('company_id', Auth::user()->company_id)
                ->first();

            if (!$template) {
                return response()->json([
                    'code' => 404,
                    'success' => false,
                    'message' => 'PDF Template not found or access denied.'
                ], 404);
            }

            // Delete header & footer files if they exist
            foreach (['header', 'footer'] as $key) {
                $uploadId = $template->$key;
                if ($uploadId) {
                    $upload = UploadsModel::find($uploadId);
                    if ($upload) {
                        $filePath = storage_path('app/public/' . $upload->file_url);
                        if (File::exists($filePath)) {
                            File::delete($filePath);
                        }
                        $upload->delete();
                    }
                }
            }

            // Delete the PDF template
            $template->delete();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'PDF Template and associated files deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Something went wrong while deleting the PDF template.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // migrate
    public function importPdfTemplates()
    {
        PdfTemplateModel::truncate();

        $successfulInserts = 0;
        $errors = [];
    
        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/pdf_template.php';
    
        // Fetch data from the external URL
        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data from the external source.'], 500);
        }
    
        // Check if fetching failed
        if ($response->failed()) {
            return response()->json(['code' => 500, 'success' => false, 'error' => 'Failed to fetch data.'], 500);
        }
    
        $data = $response->json();
    
        if (empty($data)) {
            return response()->json(['code' => 404, 'success' => false, 'message' => 'No data found'], 404);
        }
    
        $successfulInserts = 0;
        $errors = [];

        $footerUploadId = null;
        $externalFooterImageUrl = 'https://expo.egsm.in/assets/media/pdf/quot_bottom.jpg';

        try {
            $imageContent = file_get_contents($externalFooterImageUrl);

            if ($imageContent) {
                $extension = pathinfo(parse_url($externalFooterImageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = Str::random(20) . '.' . $extension;
                $relativePath = 'uploads/pdf_template/' . $filename;
                $storagePath = storage_path('app/public/' . $relativePath);

                // Save the image to local storage
                file_put_contents($storagePath, $imageContent);

                // Store in UploadsModel
                $upload = UploadsModel::create([
                    'company_id' => Auth::user()->company_id,
                    'file_ext' => $extension,
                    'file_url' => $relativePath,
                    'file_size' => strlen($imageContent),
                    'file_name' => basename($externalFooterImageUrl),
                ]);

                $footerUploadId = $upload->id;

                if (!$footerUploadId) 
                {
                    return response()->json([
                        'code' => 500,
                        'success' => false,
                        'message' => 'Footer image upload failed. No templates imported.'
                    ], 500);
                }

            }
        } catch (\Exception $e) {
            $errors[] = [
                'footer_image_url' => $externalFooterImageUrl,
                'download_error' => $e->getMessage()
            ];
        }

        foreach ($data['data'] as $pdf_record)
        {    
            // Decode nested JSON strings for address and bank details
            $address = isset($pdf_record['address']) ? json_decode($pdf_record['address'], true) : [];
            $bank_details = isset($pdf_record['bank_details']) && json_decode($pdf_record['bank_details'], true) !== null 
            ? json_decode($pdf_record['bank_details'], true) 
            : [];

            // Replace empty values with null
            $bank_details = array_map(function($value) {
                return $value === '' ? null : $value;
            }, $bank_details);

            // Prepare PDF template data
            $pdfTemplateData = [
                'name' => $pdf_record['name'],
                'company_id' => Auth::user()->company_id,
                'phone_number' => $pdf_record['phone'],
                'mobile' => $pdf_record['mobile'],
                'email' => $pdf_record['email'],
                'address_line_1' => $address['address1'] ?? 'No Address1 Provided',
                'address_line_2' => $address['address2'] ?? 'No Address2 Provided',
                'city' => $address['city'] ?? 'No City Provided',
                'state' => $address['state'] ?? 'No State Provided',
                'pincode' => $address['pincode'] ?? 'No Pincode Provided',
                'country' => 'India', // Default value or customize as needed
                'gstin' => $pdf_record['gstin'],
                'bank_number' => $bank_details['bank_name'] ?? 'No Bank Name Provided',
                'bank_account_name' => $bank_details['branch'] ?? 'No Branch Provided',
                'bank_account_number' => $bank_details['ac_no'] ?? 'No Account Number Provided',
                'bank_ifsc' => $bank_details['ifsc'] ?? 'No IFSC Provided',
                'header' => null, //'No Header Provided' to null
                'footer' => $footerUploadId,
            ];

            // Validate PDF template data
            $validator = Validator::make($pdfTemplateData, [
                'name' => 'required|string',
                'phone_number' => 'required|string',
                'mobile' => 'required|string',
                'email' => 'required|string|email',
                'address_line_1' => 'required|string',
                'address_line_2' => 'required|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'pincode' => 'required|string',
                'country' => 'required|string',
                'gstin' => 'required|string',
                'bank_number' => 'required|string',
                'bank_account_name' => 'required|string',
                'bank_account_number' => 'required|string',
                'bank_ifsc' => 'required|string',
                'footer' => 'required|integer'
            ]);

            if ($validator->fails()) {
                $errors[] = ['record' => $pdfTemplateData, 'validation_errors' => $validator->errors()];
            } else {
                // Insert PDF template record if validation passes
                try {
                    PdfTemplateModel::create($pdfTemplateData);
                    $successfulInserts++;
                } catch (\Exception $e) {
                    $errors[] = ['record' => $pdfTemplateData, 'insert_error' => 'Failed to create PDF template: ' . $e->getMessage()];
                }
            }
        }

        // Return summary of the operation
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => "PDF template import completed. Successful inserts: $successfulInserts.",
            'errors' => $errors,
        ], 200);
    }

    // godown setup table
    //create
    public function add_godown(Request $request)
    {
        $request->validate([
            'name' => 'required|string',

        ]);

        $register_godown = GodownModel::create([
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'mobile' => $request->input('email'),
            'email' => $request->input('email'),
        ]);
        
        unset($register_godown['id'], $register_godown['created_at'], $register_godown['updated_at']);

        return isset($register_godown) && $register_godown !== null
        ? response()->json(['code' => 200, 'success' => true, 'message' => 'Godown registered successfully!', 'data' => $register_godown], 200)
        : response()->json(['code' => 404, 'success' => false, 'message' => 'Failed to register Godown record'], 404);
    }

    //view
    public function view_godown()
    {        
        $get_godown = GodownModel::select('id','name')->get();

        return isset($get_godown) && $get_godown !== null
        ? response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Fetch data successfully!',
            'data' => $get_godown,
        ], 200)
        : response()->json([
            'code' => 404,
            'success' => false,
            'message' => 'Failed to fetch data',
            'data' => [],
        ], 404); 
    }

    // update
    public function edit_godown(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'mobile' => 'required|string',
            'email' => 'required|string',
        ]);

        $update_godown = GodownModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'mobile' => $request->input('email'),
            'email' => $request->input('email'),
        ]);
        
        return $update_godown
        ? response()->json(['Products updated successfully!', 'data' => $update_godown], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_godown($id)
    {
        // Delete the client
        $delete_godown = GodownModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_godown
        ? response()->json(['message' => 'Delete Godown successfully!'], 204)
        : response()->json(['message' => 'Sorry, Godown not found'], 400);
    }

    // category table
    //create
    public function add_category(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $register_category = CategoryModel::create([
            'serial_number' => $request->input('serial_number'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        unset($register_category['id'], $register_category['created_at'], $register_category['updated_at']);

        return isset($register_category) && $register_category !== null
        ? response()->json(['code' => 201, 'success' => true, 'Category registered successfully!', 'data' => $register_category], 201)
        : response()->json(['code' => 400, 'success' => false, 'Failed to register Category record'], 400);
    }

    //view
    public function view_category(Request $request)
    {
        $groupIds = $request->input('group_id'); // Accept group_id parameter

        if ($groupIds) {
            // Convert group_id to an array if it is a comma-separated string
            $groupIdsArray = explode(',', $groupIds);

            // Fetch categories associated with products in the given group(s)
            $get_category = CategoryModel::select('id', 'name')
                ->whereIn('id', function ($query) use ($groupIdsArray) {
                    $query->select('category')
                        ->from('t_products')
                        ->whereIn('group', $groupIdsArray);
                })
                ->orderBy('serial_number', 'asc') // Sort by serial_number
                ->get();
        } else {
            // Fetch all categories if no group_id is passed
            $get_category = CategoryModel::select('id', 'name')
                ->orderBy('serial_number', 'asc') // Sort by serial_number
                ->get();
        }

        // Return the response
        return isset($get_category) && !$get_category->isEmpty()
            ? response()->json(['code' => 200, 'success' => true, 'message' => 'Fetch data successfully!', 'data' => $get_category, 'count' => $get_category->count()], 200)
            : response()->json(['code' => 200, 'success' => false, 'message' => 'Failed to fetch data'], 200);
    }


    // update
    public function edit_category(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_category = CategoryModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_category
        ? response()->json(['code' => 200, 'success' => true, 'Category updated successfully!', 'data' => $update_category], 200)
        : response()->json(['code' => 204, 'success' => false, 'No changes detected'], 204);
    }

    // delete
    public function delete_category($id)
    {
        // Delete the client
        $delete_category = CategoryModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_category
        ? response()->json(['code' => 204, 'success' => true,'message' => 'Delete category successfully!'], 204) 
        : response()->json(['code' => 400, 'success' => false, 'message' => 'Sorry, category not found'], 400);
    }

    // sub-category table
    //create
    public function add_sub_category(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
            'category_id' => 'required|exists:t_category,id'
        ]);

        $register_sub_category = SubCategoryModel::create([
            'serial_number' => $request->input('serial_number'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
            'category_id' => $request->input('category_id')
        ]);
        
        unset($register_sub_categoryy['id'], $register_sub_category['created_at'], $register_category['updated_at']);

        return isset($register_sub_category) && $register_sub_category !== null
        ? response()->json(['code' => 201, 'success' => true, 'message' => 'Sub Category registered successfully!', 'data' => $register_sub_category], 201)
        : response()->json(['code' => 400, 'success' => false, 'message' => 'Failed to register Sub Category record'], 400);
    }

    public function view_sub_category(Request $request)
    {
        // Check if category_id is provided
        $categoryId = $request->input('category_id');
        if (!$categoryId) {
            return response()->json([
                'code' => 200,
                'success' => false,
                'data' => [],
                'message' => 'category_id is required'
            ], 200);
        }

        // Check if group_id is provided
        $groupIds = $request->input('group_id');

        if ($groupIds) {
            // Convert group_id to an array if it is a comma-separated string
            $groupIdsArray = explode(',', $groupIds);

            // Fetch subcategories filtered by category_id and group_id
            $get_sub_category = SubCategoryModel::where('category_id', $categoryId)
                ->whereIn('id', function ($query) use ($groupIdsArray) {
                    $query->select('sub_category')
                        ->from('t_products')
                        ->whereIn('group', $groupIdsArray);
                })
                ->select('id', 'name')
                ->orderBy('serial_number', 'asc')
                ->get();
        } else {
            // Fetch subcategories filtered only by category_id
            $get_sub_category = SubCategoryModel::where('category_id', $categoryId)
                ->select('id', 'name')
                ->orderBy('serial_number', 'asc')
                ->get();
        }

        // Return the response
        return $get_sub_category->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $get_sub_category,
                'count' => $get_sub_category->count()
            ], 200)
            : response()->json([
                'code' => 200,
                'success' => false,
                'message' => 'No subcategories found for the given criteria'
            ], 200);
    }

    // update
    public function edit_sub_category(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_sub_category = SubCategoryModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_sub_category
        ? response()->json(['code' => 200, 'success' => true, 'Sub-Category updated successfully!', 'data' => $update_sub_category], 200)
        : response()->json(['code' => 204, 'success' => false, 'No changes detected'], 204);
    }

    // delete
    public function delete_sub_category($id)
    {
        // Delete the client
        $delete_sub_category = SubCategoryModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_category
        ? response()->json(['code' => 204, 'success' => true, 'message' => 'Delete sub-category successfully!'], 204)
        : response()->json(['code' => 400, 'success' => false, 'message' => 'Sorry, sub-category not found'], 400);
    }

    // Group table
    //create
    public function add_group(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $register_group = GroupModel::create([
            'serial_number' => $request->input('serial_number'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        unset($register_group['id'], $register_group['created_at'], $register_group['updated_at']);

        return isset($register_group) && $register_group !== null
        ? response()->json(['code' => 201, 'success' => true, 'Category registered successfully!', 'data' => $register_group], 201)
        : response()->json(['code' => 400, 'success' => false, 'Failed to register Category record'], 400);
    }

    //view
    public function view_group()
    {        
        $get_group = GroupModel::select('id','name')->get();

        return isset($get_group) && $get_group!== null
        ? response()->json(['code' => 200, 'success' => true, 'message' => 'Fetch data successfully!', 'data' => $get_group, 'count' => count($get_group)], 200)
        : response()->json(['code' => 200, 'success' => false, 'message' => 'Failed to fetch data'], 200); 
    }

    // update
    public function edit_group(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_group = GroupModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_group
        ? response()->json(['code' => 200, 'success' => true, 'Group updated successfully!', 'data' => $update_group], 200)
        : response()->json(['code' => 204, 'success' => false, 'No changes detected'], 204);
    }

    // delete
    public function delete_group($id)
    {
        // Delete the client
        $delete_group = GroupModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_group
        ? response()->json(['code' => 204, 'success' => true, 'message' => 'Delete group successfully!'], 204)
        : response()->json(['code' => 400, 'success' => false, 'message' => 'Sorry, category not found'], 400);
    }

    public function add_company(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $register_company = CompanyModel::create([
            'name' => $request->input('name'),
        ]);
        
        unset($register_company['id'], $register_company['created_at'], $register_company['updated_at']);

        return isset($register_company) && $register_company !== null
        ? response()->json(['Company registered successfully!', 'data' => $register_company], 201)
        : response()->json(['Failed to register Products record'], 400);
    }

    // create
    public function add_opening_stock(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'year' => 'required|string',
            'godown_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer',
            'value' => 'required|numeric',
            'sold' => 'required|integer',
        ]);

        // Insert into the database
        $register_opening_stock =  OpeningStockModel::create([
            'company_id' => Auth::user()->company_id,
            'year' => $validatedData['year'],
            'godown_id' => $validatedData['godown_id'],
            'product_id' => $validatedData['product_id'],
            'quantity' => $validatedData['quantity'],
            'value' => $validatedData['value'],
            'sold' => $validatedData['sold'],
        ]);

        unset($register_opening_stock['id'], $register_opening_stock['created_at'], $register_opening_stock['updated_at']);

        return isset($register_opening_stock) && $register_opening_stock !== null
            ? response()->json(['message' => 'Opening Stock Record registered successfully!',  'data' => $register_opening_stock], 201)
            : response()->json(['message' => 'Failed to register Opening Stock Record'], 400);
    }

    public function view_opening_stock()
    {
        // Fetch records with only the required columns
        $get_opening_stock = OpeningStockModel::select('company_id', 'year', 'godown_id', 'product_id', 'quantity', 'value', 'sold')
                                    ->get();

        return isset($get_opening_stock) && $get_opening_stock->isNotEmpty()
        ? response()->json(['Opening Stock data successfully!', 'data' => $get_opening_stock, 'count' => count($get_opening_stock)], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // create
    public function add_closing_stock(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'year' => 'required|string',
            'godown_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer',
            'value' => 'required|numeric',
            'sold' => 'required|integer',
        ]);

        // Insert into the database
        $register_closing_stock =  ClosingStockModel::create([
            'company_id' => Auth::user()->company_id,
            'year' => $validatedData['year'],
            'godown_id' => $validatedData['godown_id'],
            'product_id' => $validatedData['product_id'],
            'quantity' => $validatedData['quantity'],
            'value' => $validatedData['value'],
            'sold' => $validatedData['sold'],
        ]);

        unset($register_closing_stock['id'], $register_closing_stock['created_at'], $register_closing_stock['updated_at']);

        return isset($register_closing_stock) && $register_closing_stock !== null
            ? response()->json(['message' => 'Closing Stock Record registered successfully!',  'data' => $register_closing_stock], 201)
            : response()->json(['message' => 'Failed to register Closing Stock Record'], 400);
    }

    public function view_closing_stock()
    {
        // Fetch records with only the required columns
        $get_closing_stock = ClosingStockModel::select('company_id', 'year', 'godown_id', 'product_id', 'quantity', 'value', 'sold')
                                    ->get();

        return isset($get_closing_stock) && $get_closing_stock->isNotEmpty()
        ? response()->json(['Closing Stock data successfully!', 'data' => $get_closing_stock, 'count' => count($get_closing_stock)], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    public function getClientsTypes()
    {
        $clietsTypes = [
            'END USER',
            'OEM',
            'TRADER',
            'PROJECT',
        ];

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Clients types fetched successfully.',
            'data' => $clietsTypes,
        ], 200);
    }

    public function getClientsCategories()
    {
        $clietsCategories = [
           'AIR HANDLING UNIT',
            'COMPRESSOR',
            'CEMENT',
            'CERAMIC',
            'CHEMICAL',
            'CRUSHER - MINES',
            'CRUSHER - INFRA',
            'FOOD & AGRO',
            'FOOD & AGRICULTURE',
            'FOUNDRY',
            'INFRA',
            'MATERIAL HANDELING EQUIPMENT',
            'MINING',
            'OIL & GAS',
            'PACKAGING',
            'PHARMACEUTICALS',
            'PAPER INDUSTRY',
            'PLASTIC',
            'POWER PLANTS',
            'PROJECTS DIVISION',
            'PUMP',
            'RAILWAY',
            'STEEL',
            'STEEL & POWER',
            'SUGAR INDUSTRIES',
            'TEXTILE',
            'JUTE MILL',
            'TRADER A',
            'TRADER B',
        ];

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Clients categories fetched successfully.',
            'data' => $clietsCategories,
        ], 200);
    }

    // add
    public function register_customer_visit(Request $request)
    {
        try {
            // Step 1: Validate input
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'customer' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'contact_person_name' => 'nullable|string|max:255',
                'designation' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'champion' => 'nullable|numeric',
                'fenner' => 'nullable|numeric',
                'details' => 'nullable|string',
                'growth' => 'nullable|string',
                'expense' => 'nullable|string|max:255',
                'amount_expense' => 'nullable|numeric',
                'uploads.*' => 'nullable|file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 200);
            }

            $validated = $validator->validated();

            // Step 2: Handle uploads
            $uploadedIds = [];

            if ($request->hasFile('uploads')) {
                foreach ($request->file('uploads') as $file) {
                    $ext = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(20) . '.' . $ext;

                    $relativePath = 'uploads/customer_visit/' . $filename;
                    // $file->storeAs('public/' . dirname($relativePath), basename($relativePath));
                    Storage::disk('public')->putFileAs('uploads/customer_visit', $file, $filename);

                    $upload = UploadsModel::create([
                        'company_id' => Auth::user()->company_id,
                        'file_ext' => $ext,
                        'file_url' => $relativePath, // relative path only
                        'file_size' => $file->getSize(),
                        'file_name' => $originalName,
                    ]);

                    $uploadedIds[] = $upload->id;

                    unset($upload['id'], $upload['created_at'], $upload['updated_at']);
                }
            }

            // Step 3: Store customer visit record
            $register_visit = CustomerVisitModel::create([
                'company_id' => Auth::user()->company_id,
                'date' => $validated['date'],
                'customer' => $validated['customer'],
                'location' => $validated['location'] ?? null,
                'contact_person_name' => $validated['contact_person_name'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'champion' => $validated['champion'] ?? 0,
                'fenner' => $validated['fenner'] ?? 0,
                'details' => $validated['details'] ?? null,
                'growth' => $validated['growth'] ?? null,
                'expense' => $validated['expense'] ?? null,
                'amount_expense' => $validated['amount_expense'] ?? 0,
                'upload' => !empty($uploadedIds)
                    ? implode(',', array_filter($uploadedIds))
                    : null,
            ]);

            unset($register_visit['id'], $register_visit['company_id'], $register_visit['created_at'], $register_visit['updated_at']);

            return response()->json([
                'code' => 201,
                'success' => true,
                'message' => 'Customer visit created successfully.',
                'data' => $register_visit,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while creating the customer visit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // fetch
    public function fetch(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);
            $search = $request->input('search', '');

             $query = CustomerVisitModel::where('company_id', Auth::user()->company_id); // Filter by company_id

            // Apply search filters
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer', 'like', "%$search%")
                    ->orWhere('location', 'like', "%$search%")
                    ->orWhere('contact_person_name', 'like', "%$search%")
                    ->orWhere('expense', 'like', "%$search%");
                });
            }

            $totalRecords = $query->count();

            $visits = $query
                ->orderByDesc('date')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Convert upload IDs to actual file URLs
            foreach ($visits as $visit) {
                $uploadIds = array_filter(explode(',', $visit->upload));
                $uploadUrls = [];

                if (!empty($uploadIds)) {
                    $uploads = UploadsModel::whereIn('id', $uploadIds)->get();
                    foreach ($uploads as $upload) {
                        $uploadUrls[] = asset('storage/' . $upload->file_url);
                    }
                }

                $visit->upload_urls = $uploadUrls;
            }

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Customer visits fetched successfully.',
                'count' => count($visits),
                'total_records' => $totalRecords,
                'data' => $visits,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'Failed to fetch customer visits.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // update
    public function edit(Request $request, $id)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'customer' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'contact_person_name' => 'nullable|string|max:255',
                'designation' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'champion' => 'nullable|numeric',
                'fenner' => 'nullable|numeric',
                'details' => 'nullable|string',
                'growth' => 'nullable|string',
                'expense' => 'nullable|string|max:255',
                'amount_expense' => 'nullable|numeric',
                'uploads.*' => 'nullable|file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 200);
            }

            $validated = $validator->validated();

            // Find the existing record
            $visit = CustomerVisitModel::find($id);
            $existingUploadIds = array_filter(explode(',', $visit->upload));

            // Process new uploads if provided
            $newUploadIds = [];

            if ($request->hasFile('uploads')) {
                foreach ($request->file('uploads') as $file) {
                    $ext = $file->getClientOriginalExtension();
                    $originalName = $file->getClientOriginalName();
                    $filename = Str::random(20) . '.' . $ext;
                    $relativePath = 'uploads/customer_visit/' . $filename;

                    $file->storeAs('public/' . dirname($relativePath), basename($relativePath));

                    $upload = UploadsModel::create([
                        'company_id' => Auth::user()->company_id,
                        'file_ext' => $ext,
                        'file_url' => $relativePath, // relative only
                        'file_size' => $file->getSize(),
                        'file_name' => $originalName,
                    ]);

                    $newUploadIds[] = $upload->id;
                    
                    unset($upload['id'], $upload['created_at'], $upload['updated_at']);
                }
            }

            $allUploadIds = array_merge($existingUploadIds, $newUploadIds);

            // Update fields
            $visit->company_id = Auth::user()->company_id;
            $visit->date = $validated['date'];
            $visit->customer = $validated['customer'];
            $visit->location = $validated['location'] ?? null;
            $visit->contact_person_name = $validated['contact_person_name'] ?? null;
            $visit->designation = $validated['designation'] ?? null;
            $visit->mobile = $validated['mobile'] ?? null;
            $visit->email = $validated['email'] ?? null;
            $visit->champion = $validated['champion'] ?? 0;
            $visit->fenner = $validated['fenner'] ?? 0;
            $visit->details = $validated['details'] ?? null;
            $visit->growth = $validated['growth'] ?? null;
            $visit->expense = $validated['expense'] ?? null;
            $visit->amount_expense = $validated['amount_expense'] ?? 0;
            $visit->upload = count($allUploadIds) > 0 ? implode(',', $allUploadIds) : null;
            $visit->save();

            // Generate resolved file URLs
            $uploadUrls = [];
            if (!empty($allUploadIds)) {
                $uploads = UploadsModel::whereIn('id', $allUploadIds)->get();
                foreach ($uploads as $u) {
                    $uploadUrls[] = asset('storage/' . $u->file_url);
                }
            }

            unset($visit['id'], $visit['created_at'], $visit['updated_at']);

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Customer visit updated successfully.',
                'data' => $visit,
                'upload_urls' => $uploadUrls,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while updating the customer visit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // delete specific uploads
    public function deleteUploads(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'delete_ids' => 'required|string' // e.g. "12,14"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 200);
            }

            $visit = CustomerVisitModel::find($id);
            $deleteIds = array_filter(explode(',', $request->delete_ids));
            $existingIds = array_filter(explode(',', $visit->upload));

            // Filter IDs that actually exist in current upload
            $toDelete = array_intersect($existingIds, $deleteIds);

            if (empty($toDelete)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid upload IDs found to delete.',
                ], 200);
            }

            // Delete files from server and DB
            $uploads = UploadsModel::whereIn('id', $toDelete)->get();

            foreach ($uploads as $upload) {
                $filePath = storage_path('app/public/' . $upload->file_url);
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
                $upload->delete();
            }

            // Update visit upload column
            $remainingIds = array_diff($existingIds, $toDelete);
            $visit->upload = count($remainingIds) > 0 ? implode(',', $remainingIds) : null;
            $visit->save();

            // Get updated upload URLs
            $updatedUrls = [];
            if (!empty($remainingIds)) {
                $remainingUploads = UploadsModel::whereIn('id', $remainingIds)->get();
                foreach ($remainingUploads as $file) {
                    $updatedUrls[] = asset('storage/' . $file->file_url);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Selected files deleted successfully.',
                'upload_ids' => $remainingIds,
                'upload_urls' => $updatedUrls
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting files.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // delete
    public function delete($id)
    {
        try {
            $visit = CustomerVisit::find($request->id);
            $uploadIds = array_filter(explode(',', $visit->upload));

            // Delete upload files and records
            if (!empty($uploadIds)) {
                $uploads = UploadsModel::whereIn('id', $uploadIds)->get();
                foreach ($uploads as $upload) {
                    $filePath = storage_path('app/public/' . $upload->file_url);
                    if (File::exists($filePath)) {
                        File::delete($filePath);
                    }
                    $upload->delete();
                }
            }

            // Delete customer visit record
            $visit->delete();

            return response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Customer visit and associated files deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'success' => false,
                'message' => 'An error occurred while deleting the visit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
