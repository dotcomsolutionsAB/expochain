<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseReturnModel;
use App\Models\PurchaseReturnProductsModel;


class PurchaseReturnController extends Controller
{
    //
    // create
    public function add_purchase_return(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'purchase_return_no' => 'required|string',
            'purchase_return_date' => 'required|date',
            'purchase_invoice_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // Validating array of products
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.godown' => 'required|integer'
        ]);
    
    
        $register_purchase_return = PurchaseReturnModel::create([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'purchase_return_no' => $request->input('purchase_return_no'),
            'purchase_return_date' => $request->input('purchase_return_date'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);
        
        $products = $request->input('products');

        // Iterate over the products array and insert each contact
        foreach ($products as $product) 
        {
            PurchaseReturnProductsModel::create([
                'purchase_return_number' => $register_purchase_return['id'],
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'brand' => $product['brand'],
                'quantity' => $product['quantity'],
                'brand' => $product['brand'],
                'unit' => $product['unit'],
                'price' => $product['price'],
                'discount' => $product['discount'],
                'hsn' => $product['hsn'],
                'tax' => $product['tax'],
                'cgst' => $product['cgst'],
                'sgst' => $product['sgst'],
                'igst' => $product['igst'],
                'godown' => $product['godown'],
            ]);
        }

        unset($register_purchase_return['id'], $register_purchase_return['created_at'], $register_purchase_return['updated_at']);
    
        return isset($register_purchase_return) && $register_purchase_return !== null
        ? response()->json(['Purchase Return registered successfully!', 'data' => $register_purchase_return], 201)
        : response()->json(['Failed to register Purchase Return record'], 400);
    }

    // view
    public function view_purchase_return()
    {
        $get_purchase_returns = PurchaseReturnModel::with(['products' => function ($query) {
            $query->select('purchase_return_number', 'product_id', 'product_name', 'description', 'brand', 'quantity', 'unit', 'price', 'discount', 'hsn', 'tax', 'cgst', 'sgst', 'igst', 'godown');
        }])
        ->select('id', 'supplier_id', 'name', 'purchase_return_no', 'purchase_return_date', 'purchase_invoice_no', 'cgst', 'sgst', 'igst', 'total', 'currency', 'template', 'status')
        ->get();

        return isset($get_purchase_returns) && $get_purchase_returns !== null
            ? response()->json(['Purchase Returns fetched successfully!', 'data' => $get_purchase_returns], 200)
            : response()->json(['Failed to fetch Purchase Return data'], 404);
    }

    // update
    public function update_purchase_return(Request $request)
    {
        $request->validate([
            'purchase_return_number' => 'required|integer',
            'supplier_id' => 'required|integer',
            'name' => 'required|string',
            'purchase_return_no' => 'required|string',
            'purchase_return_date' => 'required|date',
            'purchase_invoice_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|string',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.godown' => 'required|integer',
        ]);

        $purchaseReturn = PurchaseReturnModel::where('id', $request->input('purchase_return_number'))->first();

        $purchaseReturnUpdated = $purchaseReturn->update([
            'supplier_id' => $request->input('supplier_id'),
            'name' => $request->input('name'),
            'purchase_return_no' => $request->input('purchase_return_no'),
            'purchase_return_date' => $request->input('purchase_return_date'),
            'purchase_invoice_no' => $request->input('purchase_invoice_no'),
            'cgst' => $request->input('cgst'),
            'sgst' => $request->input('sgst'),
            'igst' => $request->input('igst'),
            'total' => $request->input('total'),
            'currency' => $request->input('currency'),
            'template' => $request->input('template'),
            'status' => $request->input('status'),
        ]);

        $products = $request->input('products');
        $requestProductIDs = [];

        foreach ($products as $productData) {
            $requestProductIDs[] = $productData['product_id'];

            $existingProduct = PurchaseReturnProductsModel::where('purchase_return_number', $request->input('purchase_return_number'))
                                                        ->where('product_id', $productData['product_id'])
                                                        ->first();

            if ($existingProduct) {
                $existingProduct->update([
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'brand' => $productData['brand'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                    'godown' => $productData['godown'],
                ]);
            } else {
                PurchaseReturnProductsModel::create([
                    'purchase_return_number' => $request->input('purchase_return_number'),
                    'product_id' => $productData['product_id'],
                    'product_name' => $productData['product_name'],
                    'description' => $productData['description'],
                    'brand' => $productData['brand'],
                    'quantity' => $productData['quantity'],
                    'unit' => $productData['unit'],
                    'price' => $productData['price'],
                    'discount' => $productData['discount'],
                    'hsn' => $productData['hsn'],
                    'tax' => $productData['tax'],
                    'cgst' => $productData['cgst'],
                    'sgst' => $productData['sgst'],
                    'igst' => $productData['igst'],
                    'godown' => $productData['godown'],
                ]);
            }
        }

        $productsDeleted = PurchaseReturnProductsModel::where('purchase_return_number', $request->input('purchase_return_number'))
                                                    ->whereNotIn('product_id', $requestProductIDs)
                                                    ->delete();

        return ($purchaseReturnUpdated || $productsDeleted)
            ? response()->json(['message' => 'Purchase Return and products updated successfully!', 'data' => $purchaseReturn], 200)
            : response()->json(['message' => 'No changes detected.'], 304);
    }

    // delete
    public function delete_purchase_return($id)
    {
        $get_purchase_return_id = PurchaseReturnModel::select('id')->where('id', $id)->first();

        if ($get_purchase_return_id) {
            $delete_purchase_return = PurchaseReturnModel::where('id', $id)->delete();

            $delete_purchase_return_products = PurchaseReturnProductsModel::where('purchase_return_number', $get_purchase_return_id->id)->delete();

            return $delete_purchase_return && $delete_purchase_return_products
                ? response()->json(['message' => 'Purchase Return and associated products deleted successfully!'], 200)
                : response()->json(['message' => 'Failed to delete Purchase Return or products.'], 400);
        } else {
            return response()->json(['message' => 'Purchase Return not found.'], 404);
        }
    }
}
