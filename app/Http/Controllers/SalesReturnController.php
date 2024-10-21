<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesReturnModel;
use App\Models\SalesReturnProductsModel;

class SalesReturnController extends Controller
{
    //
    // create
    public function add_sales_retrun(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'name' => 'required|string',
            'sales_return_no' => 'required|string',
            'sales_return_date' => 'required|date',
            'sales_invoice_no' => 'required|string',
            'cgst' => 'required|numeric',
            'sgst' => 'required|numeric',
            'igst' => 'required|numeric',
            'total' => 'required|numeric',
            'currency' => 'required|string',
            'template' => 'required|integer',
            'status' => 'required|integer',
            'products' => 'required|array', // For validating array of products
            'products.*.sales_return_id' => 'required|integer',
            'products.*.product_id' => 'required|integer',
            'products.*.product_name' => 'required|string',
            'products.*.description' => 'nullable|string',
            'products.*.brand' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.unit' => 'required|integer',
            'products.*.price' => 'required|numeric',
            'products.*.discount' => 'nullable|numeric',
            'products.*.hsn' => 'required|string',
            'products.*.tax' => 'required|numeric',
            'products.*.cgst' => 'required|numeric',
            'products.*.sgst' => 'required|numeric',
            'products.*.igst' => 'required|numeric',
            'products.*.godown' => 'required|integer'
        ]);
    
    
        $register_sales_return = SalesReturnModel::create([
            'client_id' => $request->input('client_id'),
            'name' => $request->input('name'),
            'sales_return_no' => $request->input('sales_return_no'),
            'sales_return_date' => $request->input('sales_return_date'),
            'sales_invoice_no' => $request->input('sales_invoice_no'),
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
            SalesReturnProductsModel::create([
                'sales_return_id' => $register_sales_return['id'],
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'description' => $product['description'],
                'brand' => $product['brand'],
                'quantity' => $product['quantity'],
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

        unset($register_sales_return['id'], $register_sales_return['created_at'], $register_sales_return['updated_at']);
    
        return isset($register_sales_return) && $register_sales_return !== null
        ? response()->json(['Sales Retrun registered successfully!', 'data' => $register_sales_return], 201)
        : response()->json(['Failed to register Sales Return record'], 400);
    }
}
