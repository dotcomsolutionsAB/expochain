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
}
