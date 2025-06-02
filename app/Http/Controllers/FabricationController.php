<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FabricationModel;
use App\Models\FabricationProductsModel;
use Auth;

class FabricationController extends Controller
{
    //
    //create
    public function add(Request $request)
    {
        $request->validate([
            'fb_date'                  => 'required|date',
            'vendor_id'                => 'nullable|integer|exists:t_vendors,id', // Can be null
            'invoice_no'               => 'nullable|string|max:255',
            'remarks'                  => 'nullable|string',
            'fb_amount'                => 'nullable|numeric',
            // Products validation
            'products'                 => 'required|array|min:1',
            'products.*.product_id'    => 'required|integer|exists:t_products,id',
            'products.*.quantity'      => 'required|numeric|min:0',
            'products.*.rate'          => 'required|numeric|min:0',
            'products.*.amount'        => 'required|numeric|min:0',
            'products.*.godown_id'     => 'required|integer|exists:t_godown,id',
            'products.*.remarks'       => 'nullable|string',
            'products.*.type'          => 'required|in:raw,finished',
            'products.*.wastage'       => 'nullable|numeric|min:0',
        ]);

        try {
            \DB::beginTransaction();

            $fabrication = FabricationModel::create([
                'company_id'   => Auth::user()->company_id,
                'vendor_id'    => $request->input('vendor_id'),
                'fb_date'      => $request->input('fb_date'),
                'invoice_no'   => $request->input('invoice_no'),
                'remarks'      => $request->input('remarks'),
                'fb_amount'    => $request->input('fb_amount')
            ]);

            foreach ($request->input('products') as $product) {
                FabricationProductsModel::create([
                    'company_id'  => Auth::user()->company_id,
                    'fb_id'       => $fabrication->id,
                    'product_id'  => $product['product_id'],
                    'quantity'    => $product['quantity'],
                    'rate'        => $product['rate'],
                    'amount'      => $product['amount'],
                    'godown_id'   => $product['godown_id'],
                    'remarks'     => $product['remarks'] ?? null,
                    'type'        => $product['type'],
                    'wastage'     => $product['wastage'] ?? null
                ]);
            }

            \DB::commit();

            return response()->json([
                'code'    => 201,
                'success' => true,
                'message' => 'Fabrication registered successfully!',
                'data'    => $fabrication
            ], 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to register Fabrication record',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // public function view_fabrication(Request $request)
    // {
    //     // Get filter inputs
    //     $fabricationDate = $request->input('fabrication_date');
    //     $productId = $request->input('product_id');
    //     $productName = $request->input('product_name');
    //     $type = $request->input('type');
    //     $limit = $request->input('limit', 10); // Default limit to 10
    //     $offset = $request->input('offset', 0); // Default offset to 0

    //     // Build the query
    //     $query = FabricationModel::select('id', 'fabrication_date', 'product_id', 'product_name', 'type', 'quantity', 'godown', 'rate', 'amount', 'description', 'log_user')
    //         ->where('company_id', Auth::user()->company_id);

    //     // Apply filters
    //     if ($fabricationDate) {
    //         $query->whereDate('fabrication_date', $fabricationDate);
    //     }
    //     if ($productId) {
    //         $query->where('product_id', $productId);
    //     }
    //     if ($productName) {
    //         $query->where('product_name', 'LIKE', '%' . $productName . '%');
    //     }
    //     if ($type) {
    //         $query->where('type', $type);
    //     }

    //     // Apply limit and offset
    //     $query->offset($offset)->limit($limit);

    //     // Fetch data
    //     $get_fabrication = $query->get();

    //     // Return response
    //     return $get_fabrication->isNotEmpty()
    //         ? response()->json([
    //             'code' => 200,
    //             'success' => true,
    //             'message' => 'Fabrication data fetched successfully!',
    //             'data' => $get_fabrication,
    //             'count' => $get_fabrication->count(),
    //         ], 200)
    //         : response()->json([
    //             'code' => 404,
    //             'success' => false,
    //             'message' => 'No Fabrication data found!',
    //         ], 404);
    // }
    public function view(Request $request, $id = null)
    {
        try {
            $company_id    = Auth::user()->company_id;
            $fb_date       = $request->input('fb_date');
            $vendor_id     = $request->input('vendor_id');
            $invoice_no    = $request->input('invoice_no');
            $product_name  = $request->input('product_name');
            $limit         = $request->input('limit', 10);
            $offset        = $request->input('offset', 0);

            // Build the base query
            $query = FabricationModel::where('company_id', $company_id);

            // 🔹 Fetch by ID
            if ($id) {
                $fabrication = $query->with(['products'])->find($id);
                if (!$fabrication) {
                    return response()->json([
                        'code'    => 404,
                        'success' => false,
                        'message' => 'Fabrication record not found!',
                    ], 404);
                }

                // Hide timestamps and other not needed fields
                $fabrication->makeHidden(['created_at', 'updated_at']);

                // Hide timestamps from products relation if loaded
                if ($fabrication->relationLoaded('products')) {
                    $fabrication->products->makeHidden(['created_at', 'updated_at']);
                }

                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'Fabrication record fetched successfully!',
                    'data'    => $fabrication,
                ], 200);
            }

            // 🔹 Filters for listing
            if ($fb_date) {
                $query->whereDate('fb_date', $fb_date);
            }
            if ($vendor_id) {
                $query->where('vendor_id', $vendor_id);
            }
            if ($invoice_no) {
                $query->where('invoice_no', 'LIKE', '%' . $invoice_no . '%');
            }
            if ($product_name) {
                // Filter related products by product_name
                $query->whereHas('products', function($q) use ($product_name) {
                    $q->whereHas('product', function($qp) use ($product_name) {
                        $qp->where('name', 'LIKE', '%' . $product_name . '%');
                    });
                });
            }

            $total = $query->count();

            $fabrications = $query
                ->with(['products' => function($q) {
                    $q->select('id', 'fb_id', 'product_id', 'quantity', 'rate', 'amount', 'godown_id', 'remarks', 'type', 'wastage');
                    // Optionally eager load product details here as well
                }])
                ->select('id', 'company_id', 'vendor_id', 'fb_date', 'invoice_no', 'remarks', 'fb_amount')
                ->orderBy('fb_date', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Hide timestamps
            $fabrications->makeHidden(['created_at', 'updated_at']);
            foreach ($fabrications as $fab) {
                $fab->makeHidden(['created_at', 'updated_at']);
                if ($fab->relationLoaded('products')) {
                    $fab->products->makeHidden(['created_at', 'updated_at']);
                }
            }

            if ($fabrications->isEmpty()) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'No Fabrication data found!',
                ], 404);
            }

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Fabrication data fetched successfully!',
                'data'    => $fabrications,
                'count'   => $fabrications->count(),
                'total_records' => $total,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // update
    // public function edit(Request $request, $id)
    // {
    //     $request->validate([
    //         'fabrication_date' => 'required|date',
    //         'product_id' => 'required|integer|exists:t_products,id',
    //         'product_name' => 'required|string|exists:t_products,name',
    //         'type' => 'required|in:wastage,sample',
    //         'quantity' => 'required|integer',
    //         'godown' => 'required|integer',
    //         'rate' => 'required|numeric',
    //         'amount' => 'required|numeric',
    //         'description' => 'nullable|string',
    //         'log_user' => 'required|string'
    //     ]);

    //     $update_fabrication = FabricationModel::where('id', $id)
    //     ->update([
    //         'fabrication_date' => $request->input('fabrication_date'),
    //         'product_id' => $request->input('product_id'),
    //         'product_name' => $request->input('product_name'),
    //         'type' => $request->input('type'),
    //         'quantity' => $request->input('quantity'),
    //         'godown' => $request->input('godown'),
    //         'rate' => $request->input('rate'),
    //         'amount' => $request->input('amount'),
    //         'description' => $request->input('description'),
    //         'log_user' => $request->input('log_user')
    //     ]);
        
    //     return $update_fabrication
    //     ? response()->json(['code' => 201,'success' => true, 'message' => 'Fabrication updated successfully!', 'data' => $update_fabrication], 201)
    //     : response()->json(['code' => 204,'success' => false, 'message' => 'No changes detected'], 204);
    // }
    public function edit(Request $request, $id)
    {
        $request->validate([
            // Fabrication header fields
            'vandor_id'   => 'nullable|integer|exists:t_vendors,id',
            'fb_date'     => 'required|date',
            'invoice_no'  => 'nullable|string|max:255',
            'remarks'     => 'nullable|string',
            'fb_amount'   => 'nullable|numeric',

            // Products
            'products'                    => 'required|array|min:1',
            'products.*.product_id'       => 'required|integer|exists:t_products,id',
            'products.*.quantity'         => 'required|numeric|min:0',
            'products.*.rate'             => 'required|numeric|min:0',
            'products.*.amount'           => 'required|numeric|min:0',
            'products.*.godown_id'        => 'required|integer|exists:t_godown,id',
            'products.*.remarks'          => 'nullable|string',
            'products.*.type'             => 'required|in:raw,finished',
            'products.*.wastage'          => 'nullable|numeric|min:0',
        ]);

        try {
            $company_id = Auth::user()->company_id;
            $fabrication = FabricationModel::where('company_id', $company_id)->where('id', $id)->first();

            if (!$fabrication) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Fabrication record not found!'
                ], 404);
            }

            // Update parent table (t_fabrications)
            $fabrication->vandor_id   = $request->input('vandor_id');
            $fabrication->fb_date     = $request->input('fb_date');
            $fabrication->invoice_no  = $request->input('invoice_no');
            $fabrication->remarks     = $request->input('remarks');
            $fabrication->fb_amount   = $request->input('fb_amount');
            $fabrication->save();

            // Gather all product_ids from the request
            $products = $request->input('products');
            $requestProductIDs = [];

            foreach ($products as $prod) {
                $requestProductIDs[] = $prod['product_id'];

                $existingProduct = FabricationProductsModel::where('fb_id', $id)
                    ->where('product_id', $prod['product_id'])
                    ->first();

                if ($existingProduct) {
                    // Update existing (fb_id is NOT editable)
                    $existingProduct->quantity   = $prod['quantity'];
                    $existingProduct->rate       = $prod['rate'];
                    $existingProduct->amount     = $prod['amount'];
                    $existingProduct->godown_id  = $prod['godown_id'];
                    $existingProduct->remarks    = $prod['remarks'] ?? null;
                    $existingProduct->type       = $prod['type'];
                    $existingProduct->wastage    = $prod['wastage'] ?? null;
                    $existingProduct->save();
                } else {
                    // Create new (with fb_id)
                    FabricationProductsModel::create([
                        'company_id' => $company_id,
                        'fb_id'      => $id,
                        'product_id' => $prod['product_id'],
                        'quantity'   => $prod['quantity'],
                        'rate'       => $prod['rate'],
                        'amount'     => $prod['amount'],
                        'godown_id'  => $prod['godown_id'],
                        'remarks'    => $prod['remarks'] ?? null,
                        'type'       => $prod['type'],
                        'wastage'    => $prod['wastage'] ?? null,
                    ]);
                }
            }

            // Delete products for this fb_id that were *not* in the request
            FabricationProductsModel::where('fb_id', $id)
                ->whereNotIn('product_id', $requestProductIDs)
                ->delete();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Fabrication and associated products updated successfully!',
                'data'    => $fabrication
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'    => 422,
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // delete
    // public function delete_fabrication($id)
    // {
    //     // Delete the fabrication
    //     $delete_fabrication = FabricationModel::where('id', $id)
    //                                             ->where('company_id', Auth::user()->company_id)
    //                                             ->delete();

    //     // Return success response if deletion was successful
    //     return $delete_fabrication
    //     ? response()->json(['code' => 204,'success' => true, 'message' => 'Delete Fabrication successfully!'], 204)
    //     : response()->json(['code' => 400,'success' => false, 'message' => 'Sorry, Fabrication not found'], 400);
    // }

    public function delete($id)
    {
        try {
            $company_id = Auth::user()->company_id;

            // Find the fabrication record
            $fabrication = FabricationModel::where('id', $id)
                ->where('company_id', $company_id)
                ->first();

            if (!$fabrication) {
                return response()->json([
                    'code'    => 404,
                    'success' => false,
                    'message' => 'Fabrication not found.'
                ], 404);
            }

            // Delete all associated fabrication products first
            FabricationProductsModel::where('fb_id', $id)->delete();

            // Delete the fabrication record
            $fabrication->delete();

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Fabrication and all associated products deleted successfully!'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to delete fabrication.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
