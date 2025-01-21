<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductsModel;
use App\Models\FinancialYearModel;
use App\Models\PdfTemplateModel;
use App\Models\GodownModel;
use App\Models\CategoryModel;
use App\Models\SubCategoryModel;
use App\Models\BrandModel;
use App\Models\OpeningStockModel;
use App\Models\ClosingStockModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Auth;


class MastersController extends Controller
{
    // products table
    //create
    public function add_products(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|integer',
            'name' => 'required|string',
            'alias' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'brand' => 'required|string',
            'category' => 'required|string',
            'sub_category' => 'required|string',
            'cost_price' => 'required|numeric',
            'sale_price' => 'required|numeric',
            'unit' => 'required|string',
            'hsn' => 'required|string',
            'tax' => 'required|numeric',
        ]);

        $register_products = ProductsModel::create([
            'serial_number' => $request->input('serial_number'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'alias' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'brand' => $request->input('brand'),
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
        ? response()->json(['Products registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Products record'], 400);
    }

    // public function view_products()
    // {        
    //     $get_products = ProductsModel::select('serial_number','company_id','name','alias','description','type','brand','category','sub_category','cost_price','sale_price', 'unit', 'hsn', 'tax')
    //                                     ->get();
        

    //     return isset($get_products) && $get_products !== null && count($get_products) > 0
    //     ? response()->json(['Fetch data successfully!', 'data' => $get_products, 'count' => count($get_products)], 200)
    //     : response()->json(['Sorry, No products found!'], 404); 
    // }

    public function view_products(Request $request)
    {
        // Get the input parameters
        $offset = $request->input('offset', 0); // Default to 0 if not provided
        $limit = $request->input('limit', 10); // Default to 10 if not provided
        $productName = $request->input('product_name'); // Optional product name
        $brand = $request->input('brand') ? explode(',', $request->input('brand')) : null; // Comma-separated brands
        $category = $request->input('category') ? explode(',', $request->input('category')) : null; // Comma-separated categories
        $subCategory = $request->input('sub_category') ? explode(',', $request->input('sub_category')) : null; // Comma-separated subcategories

        // Build the query
        $query = ProductsModel::select('serial_number','company_id','name','alias','description','type','brand','category','sub_category','cost_price','sale_price', 'unit', 'hsn', 'tax');
        
        // Apply filters
        if ($productName) {
            $query->where('name', 'LIKE', '%' . $productName . '%');
        }
        if ($brand) {
            $query->whereIn('brand', $brand);
        }
        if ($category) {
            $query->whereIn('category', $category);
        }
        if ($subCategory) {
            $query->whereIn('sub_category', $subCategory);
        }

        // Apply offset and limit
        $query->offset($offset)->limit($limit);

        // Fetch the products
        $get_products = $query->get();

        // Return the response
        return $get_products->isNotEmpty()
            ? response()->json([
                'code' => 200,
                'success' => true,
                'message' => 'Fetch data successfully!',
                'data' => $get_products,
                'count' => $get_products->count(),
            ], 200)
            : response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Sorry, No products found!',
            ], 404);
    }



    // update
    public function edit_products(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|integer',
            'name' => 'required|string',
            'alias' => 'required|string',
            'description' => 'required|string',
            'type' => 'required|string',
            'brand' => 'required|string',
            'category' => 'required|string',
            'sub_category' => 'required|string',
            'cost_price' => 'required|numeric',
            'sale_price' => 'required|numeric',
            'unit' => 'required||string',
            'hsn' => 'required|string',
            'tax' => 'required|numeric',
        ]);

        $update_products = ProductsModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'alias' => $request->input('alias'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'brand' => $request->input('brand'),
            'category' => $request->input('category'),
            'sub_category' => $request->input('sub_category'),
            'cost_price' => $request->input('cost_price'),
            'sale_price' => $request->input('sale_price'),
            'unit' => $request->input('unit'),
            'hsn' => $request->input('hsn'),
            'tax' => $request->input('tax'),
        ]);
        
        return $update_products
        ? response()->json(['Products updated successfully!', 'data' => $update_products], 201)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_products($id)
    {
        // Delete the client
        $delete_products = ProductsModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_products
        ? response()->json(['message' => 'Delete Product successfully!'], 204)
        : response()->json(['message' => 'Sorry, products not found'], 400);
    }

    // migrate
    // public function importProducts()
    // {
    //     ProductsModel::truncate();

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

    //     // Fetch data from the external URL
    //     try {
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');

    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $successfulInserts = 0;
    //     $errors = [];

    //     foreach ($data as $record) {

    //         // Generate a random 5-digit ID for the category if it doesn't exist
    //         $category = CategoryModel::firstOrCreate(
    //             [
    //                 'name' => $record['category'],
    //                 'company_id' => Auth::user()->company_id // Ensure this is part of the matching attributes
    //             ],
    //             [
    //                 'serial_number' => random_int(10000, 99999)
    //             ]
    //         );

    //         // Generate a random 5-digit ID for the sub-category if it doesn't exist
    //         $subCategory = SubCategoryModel::firstOrCreate(
    //             [
    //                 'name' => $record['sub_category'],
    //                 'category_id' => $category->id,
    //             ],
    //             [
    //                 'company_id' => Auth::user()->company_id, // Ensure this is part of the matching attributes
    //                 'serial_number' => random_int(10000, 99999)
    //             ]
    //         );

    //          // Generate a random 5-digit ID for the sub-category if it doesn't exist
    //          $brand = BrandModel::firstOrCreate(
    //             [
    //                 'name' => $record['group_name'],
    //                 'company_id' => Auth::user()->company_id // Ensure this is part of the matching attributes
    //             ],
    //             [
    //                 'serial_number' => random_int(10000, 99999),
    //                 'logo' => random_int(10000, 99999)
    //             ]
    //         );

    //         // Prepare purchase order data
    //         $purchaseData = [
    //             'serial_number' => $record['sn'],
    //             'company_id' => Auth::user()->company_id,
    //             'name' => $record['name'],
    //             'alias' => $record['alias'], 
    //             'description' => $record['description'] ?? 'No description available',
    //             'type' => $record['type'],
    //             'brand' => $brand->id,
    //             'category' => $category->id,
    //             'sub_category' => $subCategory->id,
    //             'cost_price' => $record['cost_price'],
    //             'sale_price' => !empty($record['sale_price']) ? $record['sale_price'] : 0,
    //             'unit' => !empty($record['unit']) ? $record['unit'] : 'N/A',
    //             'hsn' => !empty($record['hsn']) ? $record['hsn'] : 'N/A',
    //             'tax' => $record['tax'],
    //         ];

    //         // Validate record data
    //         $validator = Validator::make($purchaseData, [
    //             'serial_number' => 'required|integer',
    //             'company_id' => 'required|integer',
    //             'name' => 'required|string',
    //             'alias' => 'required|string',
    //             'description' => 'nullable|string',
    //             'type' => 'required|string',
    //             'brand' => 'required|integer|exists:t_brand,id',
    //             'category' => 'required|integer|exists:t_category,id',
    //             'sub_category' => 'required|integer|exists:t_sub_category,id',
    //             'cost_price' => 'required|numeric',
    //             'sale_price' => 'required|numeric',
    //             'unit' => 'required|string',
    //             'hsn' => 'required|string',
    //             'tax' => 'required|integer',
    //         ]);

    //         if ($validator->fails()) {
    //             $errors[] = ['record' => $record, 'errors' => $validator->errors()];
    //             continue;
    //         }

    //         // Insert Product
    //         try {
    //             ProductsModel::create($purchaseData);
    //             $successfulInserts++;
    //         } catch (\Exception $e) {
    //             $errors[] = ['record' => $record, 'error' => 'Failed to insert product: ' . $e->getMessage()];
    //         }
    //     }

    //     // Return summary of the operation
    //     return response()->json([
    //         'message' => "Product data import completed. Successful inserts: $successfulInserts.",
    //         'errors' => $errors,
    //     ], 200);

    // }

    // public function importProducts()
    // {
    //     ini_set('max_execution_time', 300); // Increase execution time
    //     ini_set('memory_limit', '1024M');   // Increase memory limit

    //     // Truncate the products table before import
    //     ProductsModel::truncate();
    //     CategoryModel::truncate();
    //     BrandModel::truncate();
    //     BrandModel::truncate();

    //     // Define the external URL
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

    //     try {
    //         // Fetch data from the external URL
    //         $response = Http::get($url);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
    //     }

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'Failed to fetch data.'], 500);
    //     }

    //     $data = $response->json('data');
    //     if (empty($data)) {
    //         return response()->json(['message' => 'No data found'], 404);
    //     }

    //     $batchSize = 1000; // Batch size for processing
    //     $batchData = [];
    //     $successfulInserts = 0;
    //     $errors = [];

    //     foreach ($data as $record) {
    //         try {
    //             // Handle blank `category`, `sub_category`, or `brand_name`
    //             $categoryName = $record['category'] ?? null;
    //             $subCategoryName = $record['sub_category'] ?? null;
    //             $brandName = $record['group_name'] ?? null;

    //             $category = $categoryName 
    //                 ? CategoryModel::firstOrCreate(
    //                     [
    //                         'name' => $categoryName,
    //                         'company_id' => Auth::user()->company_id
    //                     ],
    //                     [
    //                         'serial_number' => random_int(10000, 99999)
    //                     ]
    //                 )
    //                 : null;

    //             $subCategory = $subCategoryName 
    //                 ? SubCategoryModel::firstOrCreate(
    //                     [
    //                         'name' => $subCategoryName,
    //                         'category_id' => $category ? $category->id : null,
    //                         'company_id' => Auth::user()->company_id
    //                     ],
    //                     [
    //                         'serial_number' => random_int(10000, 99999)
    //                     ]
    //                 )
    //                 : null;

    //             $brand = $brandName 
    //                 ? BrandModel::firstOrCreate(
    //                     [
    //                         'name' => $brandName,
    //                         'company_id' => Auth::user()->company_id
    //                     ],
    //                     [
    //                         'serial_number' => random_int(10000, 99999),
    //                         'logo' => random_int(10000, 99999)
    //                     ]
    //                 )
    //                 : null;

    //             // Prepare product data
    //             $purchaseData = [
    //                 'serial_number' => $record['sn'],
    //                 'company_id' => Auth::user()->company_id,
    //                 'name' => $record['name'],
    //                 'alias' => $record['alias'],
    //                 'description' => $record['description'] ?? 'No description available',
    //                 'type' => $record['type'],
    //                 'brand' => $brand ? $brand->id : null,
    //                 'category' => $category ? $category->id : null,
    //                 'sub_category' => $subCategory ? $subCategory->id : null,
    //                 'cost_price' => $record['cost_price'],
    //                 'sale_price' => $record['sale_price'] ?? 0,
    //                 'unit' => $record['unit'] ?? 'N/A',
    //                 'hsn' => $record['hsn'] ?? 'N/A',
    //                 'tax' => $record['tax'],
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ];

    //             // Add to batch
    //             $batchData[] = $purchaseData;

    //             // Insert in batches
    //             if (count($batchData) >= $batchSize) {
    //                 ProductsModel::insert($batchData);
    //                 $successfulInserts += count($batchData);
    //                 $batchData = []; // Reset batch
    //             }
    //         } catch (\Exception $e) {
    //             $errors[] = [
    //                 'record' => $record,
    //                 'error' => 'Error: ' . $e->getMessage()
    //             ];
    //         }
    //     }

    //     // Insert remaining records
    //     if (count($batchData) > 0) {
    //         ProductsModel::insert($batchData);
    //         $successfulInserts += count($batchData);
    //     }

    //     // Return response
    //     return response()->json([
    //         'message' => "Product data import completed. Successful inserts: $successfulInserts.",
    //         'errors' => $errors,
    //     ], 200);
    // }

    public function importProducts()
    {
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('memory_limit', '2048M');   // Increase memory limit

        // Truncate the products table before import
        ProductsModel::truncate();
        CategoryModel::truncate();
        SubCategoryModel::truncate();
        BrandModel::truncate();

        // Define the external URL
        $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

        try {
            // Fetch data from the external URL
            $response = Http::get($url);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }

        $data = $response->json('data');
        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }

        $batchSize = 500; // Batch size for processing
        $batchData = [];
        $successfulInserts = 0;
        $errors = [];

        // Preload existing categories, subcategories, and brands
        $existingCategories = CategoryModel::pluck('id', 'name')->toArray();
        $existingSubCategories = SubCategoryModel::pluck('id', 'name')->toArray();
        $existingBrands = BrandModel::pluck('id', 'name')->toArray();

        foreach ($data as $record) {
            try {
                // Handle blank `category`, `sub_category`, or `brand_name`
                $categoryId = null;
                if (!empty($record['category'])) {
                    $categoryId = $existingCategories[$record['category']] ?? null;

                    if (!$categoryId) {
                        $category = CategoryModel::create([
                            'name' => $record['category'],
                            'company_id' => Auth::user()->company_id,
                            'serial_number' => random_int(10000, 99999)
                        ]);
                        $existingCategories[$record['category']] = $category->id;
                        $categoryId = $category->id;
                    }
                }

                $subCategoryId = null;
                if (!empty($record['sub_category'])) {
                    $subCategoryId = $existingSubCategories[$record['sub_category']] ?? null;

                    if (!$subCategoryId) {
                        $subCategory = SubCategoryModel::create([
                            'name' => $record['sub_category'],
                            'category_id' => $categoryId,
                            'company_id' => Auth::user()->company_id,
                            'serial_number' => random_int(10000, 99999)
                        ]);
                        $existingSubCategories[$record['sub_category']] = $subCategory->id;
                        $subCategoryId = $subCategory->id;
                    }
                }

                $brandId = null;
                if (!empty($record['group_name'])) {
                    $brandId = $existingBrands[$record['group_name']] ?? null;

                    if (!$brandId) {
                        $brand = BrandModel::create([
                            'name' => $record['group_name'],
                            'company_id' => Auth::user()->company_id,
                            'serial_number' => random_int(10000, 99999),
                            'logo' => random_int(10000, 99999)
                        ]);
                        $existingBrands[$record['group_name']] = $brand->id;
                        $brandId = $brand->id;
                    }
                }

                // Sanitize numeric fields to prevent invalid values
                $costPrice = is_numeric($record['cost_price']) ? $record['cost_price'] : 0;
                $salePrice = is_numeric($record['sale_price']) ? $record['sale_price'] : 0;
                $tax = is_numeric($record['tax']) ? $record['tax'] : 0;

                // Prepare product data
                $purchaseData = [
                    'serial_number' => $record['sn'],
                    'company_id' => Auth::user()->company_id,
                    'name' => $record['name'],
                    'alias' => $record['alias'],
                    'description' => $record['description'] ?? 'No description available',
                    'type' => $record['type'],
                    'brand' => $brandId,
                    'category' => $categoryId,
                    'sub_category' => $subCategoryId,
                    'cost_price' => $costPrice,
                    'sale_price' => $salePrice,
                    'unit' => $record['unit'] ?? 'N/A',
                    'hsn' => $record['hsn'] ?? 'N/A',
                    'tax' => $record['tax'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Add to batch
                $batchData[] = $purchaseData;

                // Insert in batches
                if (count($batchData) >= $batchSize) {
                    ProductsModel::insert($batchData);
                    $successfulInserts += count($batchData);
                    $batchData = []; // Reset batch
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'record' => $record,
                    'error' => 'Error: ' . $e->getMessage()
                ];
            }
        }

        // Insert remaining records
        if (count($batchData) > 0) {
            ProductsModel::insert($batchData);
            $successfulInserts += count($batchData);
        }

        // Return response
        return response()->json([
            'message' => "Product data import completed. Successful inserts: $successfulInserts.",
            'errors' => $errors,
        ], 200);
    }


    // public function importProducts(Request $request)
    // {
    //     ini_set('max_execution_time', 600); // Extend execution time
    //     ini_set('memory_limit', '2048M');   // Increase memory limit

    //     $limit = 500; // Number of products to import in one request
    //     $offset = $request->input('offset', 0); // Starting point for the batch
    //     $url = 'https://expo.egsm.in/assets/custom/migrate/products.php';

    //     try {
    //         // Fetch data from the external API
    //         $response = Http::get($url);

    //         if ($response->failed()) {
    //             return response()->json(['error' => 'Failed to fetch data.'], 500);
    //         }

    //         $data = $response->json('data');

    //         if (empty($data)) {
    //             return response()->json(['message' => 'No data found.'], 404);
    //         }

    //         // Apply limit and offset
    //         $batchData = array_slice($data, $offset, $limit);

    //         if (empty($batchData)) {
    //             return response()->json([
    //                 'message' => 'No more products to import.',
    //                 'next_offset' => null,
    //             ], 200);
    //         }

    //         $successfulInserts = 0;
    //         $errors = [];

    //         foreach ($batchData as $record) {
    //             try {
    //                 // Handle blank category, sub_category, or brand
    //                 $category = !empty($record['category'])
    //                     ? CategoryModel::firstOrCreate(
    //                         ['name' => $record['category'],
    //                         'company_id' => Auth::user()->company_id],
    //                         ['serial_number' => random_int(10000, 99999)]
    //                     )
    //                     : null;

    //                 $subCategory = !empty($record['sub_category'])
    //                     ? SubCategoryModel::firstOrCreate(
    //                         ['name' => $record['sub_category'], 'category_id' => $category ? $category->id : null],
    //                         ['company_id' => Auth::user()->company_id,
    //                         'serial_number' => random_int(10000, 99999)]
    //                     )
    //                     : null;

    //                 $brand = !empty($record['group_name'])
    //                     ? BrandModel::firstOrCreate(
    //                         ['name' => $record['group_name'],
    //                         'company_id' => Auth::user()->company_id],
    //                         ['serial_number' => random_int(10000, 99999), 'logo' => random_int(10000, 99999)]
    //                     )
    //                     : null;

    //                 // Sanitize numeric fields to prevent invalid values
    //                 $costPrice = is_numeric($record['cost_price']) ? $record['cost_price'] : 0;
    //                 $salePrice = is_numeric($record['sale_price']) ? $record['sale_price'] : 0;
    //                 $tax = is_numeric($record['tax']) ? $record['tax'] : 0;

    //                 // Prepare product data
    //                 $productData = [
    //                     'serial_number' => $record['sn'],
    //                     'company_id' => Auth::user()->company_id,
    //                     'name' => $record['name'],
    //                     'alias' => $record['alias'],
    //                     'description' => $record['description'] ?? 'No description available',
    //                     'type' => $record['type'],
    //                     'brand' => $brand ? $brand->id : null,
    //                     'category' => $category ? $category->id : null,
    //                     'sub_category' => $subCategory ? $subCategory->id : null,
    //                     'cost_price' => $costPrice,
    //                     'sale_price' => $salePrice,
    //                     'unit' => $record['unit'] ?? 'N/A',
    //                     'hsn' => $record['hsn'] ?? 'N/A',
    //                     'tax' => $record['tax'],
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ];

    //                 // Insert the product
    //                 ProductsModel::create($productData);
    //                 $successfulInserts++;
    //             } catch (\Exception $e) {
    //                 $errors[] = [
    //                     'record' => $record,
    //                     'error' => $e->getMessage(),
    //                 ];
    //             }
    //         }

    //         // Calculate the next offset
    //         $nextOffset = $offset + $limit;

    //         return response()->json([
    //             'message' => "Imported $successfulInserts products successfully.",
    //             'errors' => $errors,
    //             'next_offset' => $nextOffset,
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }


    public function get_product()
    {
        $get_user_company_id = Auth::user()->company_id;
        
        $get_product_details = ProductsModel::select('serial_number','name','alias','description','type','brand','category','sub_category','cost_price','sale_price', 'unit', 'hsn', 'tax')
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
        ? response()->json(['Fetch data successfully!', 'data' => $get_f_year], 200)
        : response()->json(['Failed to fetch data'], 404); 
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
            'bank_account_number' => 'required',
            'bank_ifsc' => 'required',
            'header' => 'required',
            'footer' => 'required',

        ]);

        $register_pdf_template = PdfTemplateModel::create([
            'name' => $request->input('name'),
            'phone_number' => $request->input('phone_number'),
            'email	' => $request->input('email'),
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
            'header' => $request->input('header'),
            'footer' => $request->input('footer'),
        ]);
        
        unset($register_pdf_template['id'], $register_pdf_template['created_at'], $register_pdf_template['updated_at']);

        return isset($register_pdf_template) && $register_pdf_template !== null
        ? response()->json(['Pdf Template registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Pdf Template record'], 400);
    }

    //view
    public function pdf_template()
    {        
        $get_pdf_template = PdfTemplateModel::select('name','phone_number','mobile','email','address_line_1', 'address_line_2','city','pincode','state','country', 'gstin', 'bank_number', 'bank_account_name', 'bank_account_number', 'bank_ifsc','header', 'footer')->get();
        

        return isset($get_pdf_template) && $get_pdf_template !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_pdf_template], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_pdf_template(Request $request, $id)
    {
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
            'bank_account_number' => 'required',
            'bank_ifsc' => 'required',
            'header' => 'required',
            'footer' => 'required',
        ]);

        $update_pdf_template = PdfTemplateModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'phone_number' => $request->input('phone_number'),
            'mobile	' => $request->input('mobile'),
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
            'header' => $request->input('header'),
            'footer' => $request->input('footer'),
        ]);
        
        return $update_pdf_template
        ? response()->json(['Products updated successfully!', 'data' => $update_pdf_template], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_pdf_template($id)
    {
        // Delete the client
        $delete_pdf_template = PdfTemplateModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_pdf_template
        ? response()->json(['message' => 'Delete Pdf template successfully!'], 204)
        : response()->json(['message' => 'Sorry, Pdf Template not found'], 400);
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
            return response()->json(['error' => 'Failed to fetch data from the external source.'], 500);
        }
    
        // Check if fetching failed
        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch data.'], 500);
        }
    
        $data = $response->json();
    
        if (empty($data)) {
            return response()->json(['message' => 'No data found'], 404);
        }
    
        $successfulInserts = 0;
        $errors = [];

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
                'header' => 'No Header Provided',
                'footer' => $pdf_record['footer']
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
                'footer' => 'required|string'
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
            'message' => "PDF template import completed. Successful inserts: $successfulInserts.",
            'errors' => $errors,
        ], 200);
    }

    public function get_pdf_template()
    {        
        $pdf_template = PdfTemplateModel::select('name','phone_number','mobile','email','address_line_1', 'address_line_2','city','pincode','state','country', 'gstin', 'bank_number', 'bank_account_name', 'bank_account_number', 'bank_ifsc','header', 'footer')
                                            ->where('company_id', Auth::user()->company_id)
                                            ->get();


        return isset($pdf_template) && $pdf_template !== null
        ? response()->json(['Fetch data successfully!', 'data' => $pdf_template, 'count' => count($pdf_template)], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // uploads setup table
    //create
    // public function add_pdf_template(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'phone_number' => 'required|string',
    //         'mobile' => 'required|string',
    //         'email' => 'required|string',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required',
    //         'state' => 'required',
    //         'country' => 'required',
    //         'gstin' => 'required|string',
    //         'bank_number' => 'required',
    //         'bank_account_name' => 'required',
    //         'bank_account_number' => 'required',
    //         'bank_ifsc' => 'required',
    //         'header' => 'required',
    //         'footer' => 'required',

    //     ]);

    //     $register_pdf_template = PdfTemplateModel::create([
    //         'name' => $request->input('name'),
    //         'phone_number' => $request->input('phone_number'),
    //         'email	' => $request->input('email'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'gstin' => $request->input('gstin'),
    //         'bank_number' => $request->input('bank_number'),
    //         'bank_account_name' => $request->input('bank_account_name'),
    //         'bank_account_number' => $request->input('bank_account_number'),
    //         'bank_ifsc' => $request->input('bank_ifsc'),
    //         'header' => $request->input('header'),
    //         'footer' => $request->input('footer'),
    //     ]);
        
    //     unset($register_pdf_template['id'], $register_pdf_template['created_at'], $register_pdf_template['updated_at']);

    //     return isset($register_pdf_template) && $register_pdf_template !== null
    //     ? response()->json(['Pdf Template registered successfully!', 'data' => $register_products], 201)
    //     : response()->json(['Failed to register Pdf Template record'], 400);
    // }

    // //view
    // public function pdf_template()
    // {        
    //     $get_pdf_template = PdfTemplateModel::select('name','phone_number','mobile','email','address_line_1', 'address_line_2','city','pincode','state','country', 'gstin', 'bank_number', 'bank_account_name', 'bank_account_number', 'bank_ifsc','header', 'footer')->get();
        

    //     return isset($get_pdf_template) && $get_pdf_template !== null
    //     ? response()->json(['Fetch data successfully!', 'data' => $get_pdf_template], 201)
    //     : response()->json(['Failed to fetch data'], 400); 
    // }

    // // update
    // public function edit_pdf_template(Request $request, $id)
    // {
    //     $request->validate([
    //         'name' => 'required|string',
    //         'phone_number' => 'required|string',
    //         'mobile' => 'required|string',
    //         'email' => 'required|string',
    //         'address_line_1' => 'required|string',
    //         'address_line_2' => 'required|string',
    //         'city' => 'required|string',
    //         'pincode' => 'required',
    //         'state' => 'required',
    //         'country' => 'required',
    //         'gstin' => 'required|string',
    //         'bank_number' => 'required',
    //         'bank_account_name' => 'required',
    //         'bank_account_number' => 'required',
    //         'bank_ifsc' => 'required',
    //         'header' => 'required',
    //         'footer' => 'required',
    //     ]);

    //     $update_pdf_template = PdfTemplateModel::where('id', $id)
    //     ->update([
    //         'name' => $request->input('name'),
    //         'phone_number' => $request->input('phone_number'),
    //         'mobile	' => $request->input('mobile'),
    //         'email' => $request->input('email'),
    //         'address_line_1' => $request->input('address_line_1'),
    //         'address_line_2' => $request->input('address_line_2'),
    //         'city' => $request->input('city'),
    //         'pincode' => $request->input('pincode'),
    //         'state' => $request->input('state'),
    //         'country' => $request->input('country'),
    //         'gstin' => $request->input('gstin'),
    //         'bank_number' => $request->input('bank_number'),
    //         'bank_account_name' => $request->input('bank_account_name'),
    //         'bank_account_number' => $request->input('bank_account_number'),
    //         'bank_ifsc' => $request->input('bank_ifsc'),
    //         'header' => $request->input('header'),
    //         'footer' => $request->input('footer'),
    //     ]);
        
    //     return $update_pdf_template
    //     ? response()->json(['Products updated successfully!', 'data' => $update_pdf_template], 200)
    //     : response()->json(['No changes detected'], 204);
    // }

    // // delete
    // public function delete_pdf_template($id)
    // {
    //     // Delete the client
    //     $delete_pdf_template = PdfTemplateModel::where('id', $id)->delete();

    //     // Return success response if deletion was successful
    //     return $delete_pdf_template
    //     ? response()->json(['message' => 'Delete Pdf template successfully!'], 204)
    //     : response()->json(['message' => 'Sorry, Pdf Template not found'], 400);
    // }

    // godown setup table
    //create
    public function add_godown(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'mobile' => 'required|string',
            'email' => 'required|string',

        ]);

        $register_godown = GodownModel::create([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'mobile' => $request->input('email'),
            'email' => $request->input('email'),
        ]);
        
        unset($register_godown['id'], $register_godown['created_at'], $register_godown['updated_at']);

        return isset($register_godown) && $register_godown !== null
        ? response()->json(['Godown registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Godown record'], 400);
    }

    //view
    public function pdf_godown()
    {        
        $get_godown = GodownModel::select('name','address','mobile','email')->get();
        

        return isset($get_godown) && $get_godown !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_godown], 200)
        : response()->json(['Failed to fetch data'], 404); 
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

        $update_pdf_template = GodownModel::where('id', $id)
        ->update([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'mobile' => $request->input('email'),
            'email' => $request->input('email'),
        ]);
        
        return $update_pdf_template
        ? response()->json(['Products updated successfully!', 'data' => $update_pdf_template], 200)
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
        ? response()->json(['Category registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Category record'], 400);
    }

    //view
    public function view_category()
    {        
        $get_category = CategoryModel::select('serial_number','name','logo')->get();
        

        return isset($get_category) && $get_category !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_category], 200)
        : response()->json(['Failed to fetch data'], 404); 
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
        ? response()->json(['Category updated successfully!', 'data' => $update_category], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_category($id)
    {
        // Delete the client
        $delete_category = CategoryModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_category
        ? response()->json(['message' => 'Delete category successfully!'], 204)
        : response()->json(['message' => 'Sorry, category not found'], 400);
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
        ? response()->json(['Sub Category registered successfully!', 'data' => $register_sub_category], 201)
        : response()->json(['Failed to register Sub Category record'], 400);
    }

    //view
    public function view_sub_category()
    {        
        $get_sub_category = SubCategoryModel::select('serial_number','name','logo')->get();
        

        return isset($get_sub_category) && $get_sub_category !== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_sub_category], 200)
        : response()->json(['Failed to fetch data'], 404); 
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
        ? response()->json(['Sub-Category updated successfully!', 'data' => $update_sub_category], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_sub_category($id)
    {
        // Delete the client
        $delete_sub_category = SubCategoryModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_category
        ? response()->json(['message' => 'Delete sub-category successfully!'], 204)
        : response()->json(['message' => 'Sorry, sub-category not found'], 400);
    }

    // Brand table
    //create
    public function add_brand(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $register_category = BrandModel::create([
            'serial_number' => $request->input('serial_number'),
            'company_id' => Auth::user()->company_id,
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        unset($register_category['id'], $register_category['created_at'], $register_category['updated_at']);

        return isset($register_category) && $register_category !== null
        ? response()->json(['Category registered successfully!', 'data' => $register_products], 201)
        : response()->json(['Failed to register Category record'], 400);
    }

    //view
    public function view_brand()
    {        
        $get_brand = BrandModel::select('serial_number','name','logo')->get();

        return isset($get_brand) && $get_brand!== null
        ? response()->json(['Fetch data successfully!', 'data' => $get_brand], 200)
        : response()->json(['Failed to fetch data'], 404); 
    }

    // update
    public function edit_brand(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
            'name' => 'required|string',
            'logo' => 'required|string',
        ]);

        $update_brand = BrandModel::where('id', $id)
        ->update([
            'serial_number' => $request->input('serial_number'),
            'name' => $request->input('name'),
            'logo' => $request->input('logo'),
        ]);
        
        return $update_brand
        ? response()->json(['Brand updated successfully!', 'data' => $update_brand], 200)
        : response()->json(['No changes detected'], 204);
    }

    // delete
    public function delete_brand($id)
    {
        // Delete the client
        $delete_brand = BrandModel::where('id', $id)->delete();

        // Return success response if deletion was successful
        return $delete_brand
        ? response()->json(['message' => 'Delete brand successfully!'], 204)
        : response()->json(['message' => 'Sorry, category not found'], 400);
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
            'company_id' => 'required|integer',
            'year' => 'required|string',
            'godown_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer',
            'value' => 'required|numeric',
            'sold' => 'required|integer',
        ]);

        // Insert into the database
        $register_opening_stock =  OpeningStockModel::create([
            'company_id' => $validatedData['company_id'],
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
            'company_id' => 'required|integer',
            'year' => 'required|string',
            'godown_id' => 'required|integer',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer',
            'value' => 'required|numeric',
            'sold' => 'required|integer',
        ]);

        // Insert into the database
        $register_closing_stock =  ClosingStockModel::create([
            'company_id' => $validatedData['company_id'],
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
}
